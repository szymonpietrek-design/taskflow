<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryChangeRequestReview;
use App\Models\RepositoryConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Synchronize bounded review histories for stored PRs without touching PR or commit cursors. */
final class RepositoryChangeRequestReviewSynchronizer
{
    private const BATCH_SIZE = 10;

    public function __construct(private readonly RepositoryChangeRequestReviewProviders $providers) {}

    /** @return array{checked:int,fetched:int,created:int,updated:int,remaining:int} */
    public function synchronize(RepositoryConnection $repository, bool $force = false): array
    {
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        if (! $lock->get()) {
            throw new RuntimeException('A repository operation is already in progress.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $eligible = fn () => RepositoryChangeRequest::query()
                ->where('repository_connection_id', $repository->id)
                ->when(! $force, function ($query): void {
                    $query->where(function ($candidates): void {
                        $candidates->whereNull('reviews_checked_at')
                            ->orWhere(function ($open): void {
                                $open->where('state', RepositoryChangeRequest::STATE_OPEN)
                                    ->where('reviews_checked_at', '<=', now()->subMinutes(30));
                            })
                            ->orWhere(function ($closed): void {
                                $closed->where('state', '!=', RepositoryChangeRequest::STATE_OPEN)
                                    ->where('reviews_checked_at', '<=', now()->subDay());
                            });
                    });
                });
            $requests = $eligible()->orderBy('reviews_checked_at')->orderBy('id')
                ->limit(self::BATCH_SIZE)->get(['id', 'number', 'repository_connection_id']);
            if ($requests->isEmpty()) {
                return ['checked' => 0, 'fetched' => 0, 'created' => 0, 'updated' => 0, 'remaining' => 0];
            }
            $numbers = $requests->pluck('number')->map(fn ($number): int => (int) $number)->all();
            $snapshots = $this->providers->forRepository($repository)->reviewsForStoredRequests($repository, $numbers);
            if (count($snapshots) !== count($numbers)) {
                throw new RuntimeException('Incomplete pull request review response.');
            }
            $fetched = 0;
            foreach ($requests as $request) {
                $items = $snapshots[$request->number] ?? null;
                if (! is_array($items) || ! array_is_list($items) || count($items) > 500) {
                    throw new RuntimeException('Invalid pull request review batch.');
                }
                $ids = [];
                foreach ($items as $item) {
                    $this->validate($item);
                    $id = $item['external_review_id'];
                    if (isset($ids[$id])) {
                        throw new RuntimeException('Duplicate review ID in provider batch.');
                    }
                    $ids[$id] = true;
                }
                $fetched += count($items);
            }

            $result = DB::transaction(function () use ($repository, $requests, $snapshots): array {
                RepositoryConnection::query()->whereKey($repository->id)->lockForUpdate()->firstOrFail();
                $created = 0;
                $updated = 0;
                foreach ($requests as $request) {
                    $stored = RepositoryChangeRequest::query()
                        ->whereKey($request->id)->where('repository_connection_id', $repository->id)
                        ->lockForUpdate()->firstOrFail();
                    foreach ($snapshots[$request->number] as $snapshot) {
                        $review = RepositoryChangeRequestReview::query()
                            ->where('repository_change_request_id', $stored->id)
                            ->where('external_review_id', $snapshot['external_review_id'])->first();
                        if ($review === null) {
                            $stored->reviews()->create($snapshot);
                            $created++;
                        } else {
                            $review->fill($snapshot);
                            if ($review->isDirty()) {
                                $review->save();
                                $updated++;
                            }
                        }
                    }
                    // An empty review list is still a successful check.
                    $stored->forceFill(['reviews_checked_at' => now()])->save();
                }
                return ['created' => $created, 'updated' => $updated];
            });

            // Forced refresh includes previously checked rows; "remaining" means not checked in this batch.
            $remaining = $force
                ? max(0, $eligible()->count() - $requests->count())
                : $eligible()->count();

            return [
                'checked' => $requests->count(), 'fetched' => $fetched,
                'created' => $result['created'], 'updated' => $result['updated'],
                'remaining' => $remaining,
            ];
        } finally {
            $lock->release();
        }
    }

    /** @param mixed $item */
    private function validate(mixed $item): void
    {
        if (! is_array($item) || ! is_string($item['external_review_id'] ?? null)
            || ! ctype_digit($item['external_review_id'])
            || $item['external_review_id'] === '0'
            || strlen($item['external_review_id']) > 190
            || ! in_array($item['state'] ?? null, [
                RepositoryChangeRequestReview::STATE_APPROVED,
                RepositoryChangeRequestReview::STATE_CHANGES_REQUESTED,
                RepositoryChangeRequestReview::STATE_COMMENTED,
                RepositoryChangeRequestReview::STATE_DISMISSED,
                RepositoryChangeRequestReview::STATE_PENDING,
            ], true)) {
            throw new RuntimeException('Invalid normalized review snapshot.');
        }
        foreach (['reviewer_external_user_id' => 190, 'reviewer_login' => 190, 'web_url' => 2048,
            'commit_sha' => 64, 'body' => 65535, 'submitted_at' => 32] as $field => $limit) {
            if (! array_key_exists($field, $item)
                || ($item[$field] !== null && (! is_string($item[$field]) || mb_strlen($item[$field]) > $limit))) {
                throw new RuntimeException('Invalid normalized review '.$field.'.');
            }
        }
        if (($item['reviewer_external_user_id'] === null) !== ($item['reviewer_login'] === null)
            || ($item['reviewer_external_user_id'] !== null && ! ctype_digit($item['reviewer_external_user_id']))) {
            throw new RuntimeException('Incomplete review identity.');
        }
        if ($item['web_url'] !== null && ! str_starts_with($item['web_url'], 'https://')) {
            throw new RuntimeException('Invalid review link.');
        }
        if ($item['submitted_at'] !== null
            && ! preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', $item['submitted_at'])) {
            throw new RuntimeException('Invalid normalized review date.');
        }
        if ($item['state'] !== RepositoryChangeRequestReview::STATE_PENDING && $item['submitted_at'] === null) {
            throw new RuntimeException('Submitted review lacks a date.');
        }
        if ($item['commit_sha'] !== null && ! preg_match('/\A[a-f0-9]{40}\z/D', $item['commit_sha'])) {
            throw new RuntimeException('Invalid normalized review SHA.');
        }
    }
}
