<?php

namespace App\Integrations\Vcs;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use App\Models\UserVcsAccount;
use Illuminate\Support\Collection;
use RuntimeException;

/** Resolve verified reviewer identities only for active members of the owning project/workspace. */
final class RepositoryChangeRequestReviewResolver
{
    /**
     * @param Collection<int, RepositoryChangeRequestReview> $reviews
     * @return array<int, ?array{id:int,name:string}>
     */
    public function resolve(RepositoryConnection $repository, Collection $reviews): array
    {
        $repository->loadMissing('connection.instance');
        $project = Project::query()->findOrFail($repository->project_id);
        foreach ($reviews as $review) {
            $review->loadMissing('changeRequest');
            if ((int) $review->changeRequest?->repository_connection_id !== (int) $repository->id) {
                throw new RuntimeException('Cannot resolve reviews belonging to another repository.');
            }
        }

        $ids = $reviews->filter(fn (RepositoryChangeRequestReview $review) => $review->state !== RepositoryChangeRequestReview::STATE_PENDING)
            ->pluck('reviewer_external_user_id')
            ->filter(fn ($id) => $id !== null && $id !== '')->unique()->values()->all();
        $accounts = $ids === [] ? collect() : UserVcsAccount::query()
            ->where('vcs_instance_id', $repository->connection->vcs_instance_id)
            ->whereIn('external_user_id', $ids)
            ->whereHas('user', function ($query) use ($project): void {
                $query->whereNull('disabled_at')->where(function ($eligible) use ($project): void {
                    $eligible->whereHas('projectMemberships', fn ($member) => $member->where('project_id', $project->id))
                        ->orWhereHas('workspaceMemberships', fn ($member) => $member
                            ->where('workspace_id', $project->workspace_id)
                            ->whereNull('suspended_at')
                            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]));
                });
            })->with('user:id,name')->get()->keyBy('external_user_id');

        $result = [];
        foreach ($reviews as $review) {
            // Draft/PENDING reviews are not submitted contributions.
            $user = $review->state === RepositoryChangeRequestReview::STATE_PENDING
                || $review->reviewer_external_user_id === null
                ? null : $accounts->get($review->reviewer_external_user_id)?->user;
            $result[(int) $review->id] = $user === null ? null : [
                'id' => (int) $user->id, 'name' => $user->name,
            ];
        }
        return $result;
    }
}
