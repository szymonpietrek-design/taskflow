<?php

namespace App\Integrations\Vcs;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use App\Models\UserVcsAccount;
use Illuminate\Support\Collection;
use RuntimeException;

/** Resolve verified TaskFlow people on demand, without caching user ownership on PR rows. */
final class RepositoryChangeRequestParticipantResolver
{
    /**
     * @param Collection<int, RepositoryChangeRequest> $requests
     * @return array<int, array{author:?array{id:int,name:string},merged_by:?array{id:int,name:string}}>
     */
    public function resolve(RepositoryConnection $repository, Collection $requests): array
    {
        $repository->loadMissing('connection.instance');
        $project = Project::query()->findOrFail($repository->project_id);
        foreach ($requests as $request) {
            if ((int) $request->repository_connection_id !== (int) $repository->id) {
                throw new RuntimeException('Cannot resolve participants from another repository.');
            }
        }

        $ids = $requests->flatMap(fn (RepositoryChangeRequest $request): array => [
            $request->author_external_user_id,
            $request->state === RepositoryChangeRequest::STATE_MERGED ? $request->merged_by_external_user_id : null,
        ])->filter(fn ($value) => $value !== null && $value !== '')->unique()->values()->all();

        $accounts = $ids === [] ? collect() : UserVcsAccount::query()
            ->where('vcs_instance_id', $repository->connection->vcs_instance_id)
            ->whereIn('external_user_id', $ids)
            ->whereHas('user', function ($query) use ($project): void {
                $query->whereNull('disabled_at')->where(function ($eligible) use ($project): void {
                    $eligible->whereHas('projectMemberships', fn ($membership) => $membership->where('project_id', $project->id))
                        ->orWhereHas('workspaceMemberships', fn ($membership) => $membership
                            ->where('workspace_id', $project->workspace_id)
                            ->whereNull('suspended_at')
                            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value]));
                });
            })->with('user:id,name')->get()->keyBy('external_user_id');

        $person = static function (?string $id) use ($accounts): ?array {
            $user = $id === null ? null : $accounts->get($id)?->user;
            return $user ? ['id' => (int) $user->id, 'name' => $user->name] : null;
        };

        $resolved = [];
        foreach ($requests as $request) {
            $resolved[(int) $request->id] = [
                'author' => $person($request->author_external_user_id),
                'merged_by' => $request->state === RepositoryChangeRequest::STATE_MERGED
                    ? $person($request->merged_by_external_user_id) : null,
            ];
        }
        return $resolved;
    }
}
