<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryChangeRequestReview;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only consistency check for provider-neutral PR/MR and review snapshots.
 * Does not call provider APIs, print secrets, fix rows or change sync cursors.
 */
final class VcsChangeRequestAuditor
{
    /** @return array{counts:array<string,int>,issues:list<string>} */
    public function inspect(?int $projectId = null): array
    {
        $pulls = RepositoryChangeRequest::query();
        $reviews = RepositoryChangeRequestReview::query();

        if ($projectId !== null) {
            $pulls->whereHas('repository', fn (Builder $query) => $query->where('project_id', $projectId));
            $reviews->whereHas('changeRequest.repository', fn (Builder $query) => $query->where('project_id', $projectId));
        }

        $counts = [
            'pull_requests' => (clone $pulls)->count(),
            'reviews' => (clone $reviews)->count(),
        ];

        $invalidStates = (clone $pulls)
            ->whereNotIn('state', [
                RepositoryChangeRequest::STATE_OPEN,
                RepositoryChangeRequest::STATE_CLOSED,
                RepositoryChangeRequest::STATE_MERGED,
            ])->count();
        $invalidMergeDates = (clone $pulls)->where(function (Builder $query): void {
            $query->where(fn (Builder $merged) => $merged
                ->where('state', RepositoryChangeRequest::STATE_MERGED)->whereNull('merged_at'))
                ->orWhere(fn (Builder $notMerged) => $notMerged
                    ->where('state', '!=', RepositoryChangeRequest::STATE_MERGED)->whereNotNull('merged_at'));
        })->count();
        $invalidReviewStates = (clone $reviews)->whereNotIn('state', [
            RepositoryChangeRequestReview::STATE_APPROVED,
            RepositoryChangeRequestReview::STATE_CHANGES_REQUESTED,
            RepositoryChangeRequestReview::STATE_COMMENTED,
            RepositoryChangeRequestReview::STATE_DISMISSED,
            RepositoryChangeRequestReview::STATE_PENDING,
        ])->count();
        $missingReviewDates = (clone $reviews)
            ->where('state', '!=', RepositoryChangeRequestReview::STATE_PENDING)
            ->whereNull('submitted_at')->count();

        $issues = [];
        foreach ([
            ['count' => $invalidStates, 'label' => 'pull requests have an unknown state'],
            ['count' => $invalidMergeDates, 'label' => 'pull requests have inconsistent merge dates'],
            ['count' => $invalidReviewStates, 'label' => 'reviews have an unknown state'],
            ['count' => $missingReviewDates, 'label' => 'submitted reviews lack a submission date'],
        ] as $problem) {
            if ($problem['count'] > 0) {
                $issues[] = "{$problem['count']} {$problem['label']}.";
            }
        }

        return ['counts' => $counts, 'issues' => $issues];
    }
}
