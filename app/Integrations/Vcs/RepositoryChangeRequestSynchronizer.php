<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Independently synchronizes PR/MR metadata; it never changes the commit SHA cursor.
 * Fetch and validate the complete bounded batch before writing any rows.
 */
final class RepositoryChangeRequestSynchronizer
{
    public function __construct(
        private readonly RepositoryChangeRequestProviders $providers,
        private readonly RepositoryChangeRequestSnapshotValidator $validator,
    ) {}

    /**
     * Revalidate an existing PR by its exact number even if GitHub's updated_at
     * has not changed. Normal incremental sync cannot refresh unchanged PRs.
     * Never import a new PR here or advance the repository commit cursor.
     */
    public function refreshOne(RepositoryConnection $repository, int $number): RepositoryChangeRequest
    {
        if ($number < 1) {
            throw new RuntimeException('Invalid pull request number.');
        }
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        if (! $lock->get()) {
            throw new RuntimeException('A repository operation is already in progress.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $stored = $repository->changeRequests()->where('number', $number)->firstOrFail();
            $snapshot = $this->providers->forRepository($repository)->forNumber($repository, $number);
            $this->validator->assertValid($snapshot);
            if ((int) $snapshot['number'] !== $number
                || (string) $snapshot['external_change_request_id'] !== (string) $stored->external_change_request_id) {
                throw new RuntimeException('Pull request identity changed during refresh.');
            }

            return DB::transaction(function () use ($repository, $stored, $snapshot): RepositoryChangeRequest {
                RepositoryConnection::query()->whereKey($repository->id)->lockForUpdate()->firstOrFail();
                $pull = $repository->changeRequests()->whereKey($stored->id)->lockForUpdate()->firstOrFail();
                if ((string) $pull->external_change_request_id !== (string) $snapshot['external_change_request_id']) {
                    throw new RuntimeException('Pull request identity changed during refresh.');
                }
                if ($pull->remote_updated_at !== null
                    && $pull->remote_updated_at->greaterThan(Carbon::parse($snapshot['remote_updated_at']))) {
                    throw new RuntimeException('GitHub returned an older pull request snapshot.');
                }
                $pull->fill($snapshot);
                if ($pull->isDirty()) {
                    $pull->reviews_checked_at = null;
                }
                $pull->synced_at = now();
                $pull->save();
                return $pull;
            });
        } finally {
            $lock->release();
        }
    }

    /** @return array{fetched:int,created:int,updated:int} */
    public function synchronize(RepositoryConnection $repository): array
    {
        // Use the same lock as commit sync and disconnect to avoid orphaned writes.
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        if (! $lock->get()) {
            throw new RuntimeException('A repository operation is already in progress.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $highWatermark = RepositoryChangeRequest::query()
                ->where('repository_connection_id', $repository->id)
                ->max('remote_updated_at');
            $since = $highWatermark === null ? null : Carbon::parse($highWatermark)->utc()->subMinutes(5);
            $provider = $this->providers->forRepository($repository);
            $snapshots = $provider->updatedSince($repository, $since);
            if (! is_array($snapshots) || ! array_is_list($snapshots)) {
                throw new RuntimeException('Invalid provider change request response.');
            }
            if (count($snapshots) > 500) {
                throw new RuntimeException('Pull request batch exceeded the safety limit.');
            }
            $externalIds = [];
            $numbers = [];
            foreach ($snapshots as $snapshot) {
                $this->validator->assertValid($snapshot);
                if (isset($externalIds[$snapshot['external_change_request_id']]) || isset($numbers[$snapshot['number']])) {
                    throw new RuntimeException('Duplicated pull request in provider batch.');
                }
                $externalIds[$snapshot['external_change_request_id']] = true;
                $numbers[$snapshot['number']] = true;
            }

            return DB::transaction(function () use ($repository, $snapshots): array {
                RepositoryConnection::query()->lockForUpdate()->findOrFail($repository->id);
                $created = 0;
                $updated = 0;
                foreach ($snapshots as $snapshot) {
                    $existing = RepositoryChangeRequest::query()
                        ->where('repository_connection_id', $repository->id)
                        ->where('external_change_request_id', $snapshot['external_change_request_id'])
                        ->first();
                    // Reject provider mistakes that reuse the same public number with another external ID.
                    $conflict = RepositoryChangeRequest::query()
                        ->where('repository_connection_id', $repository->id)
                        ->where('number', $snapshot['number'])
                        ->first();
                    if ($conflict !== null && $conflict->external_change_request_id !== $snapshot['external_change_request_id']) {
                        throw new RuntimeException('Conflicting pull request number for repository.');
                    }
                    // Never overwrite a newer stored snapshot with an older page from an API race.
                    if ($existing !== null && $existing->remote_updated_at !== null
                        && $existing->remote_updated_at->greaterThan(Carbon::parse($snapshot['remote_updated_at']))) {
                        continue;
                    }
                    if ($existing !== null) {
                        $existing->fill($snapshot);
                        $changed = $existing->isDirty();
                        if ($changed) {
                            // Refetch reviews after any newly observed PR update (dismissals, merge, etc.).
                            $existing->reviews_checked_at = null;
                        }
                        $existing->synced_at = now();
                        $existing->save();
                        if ($changed) {
                            $updated++;
                        }
                    } else {
                        $repository->changeRequests()->create($snapshot + ['synced_at' => now()]);
                        $created++;
                    }
                }

                return ['fetched' => count($snapshots), 'created' => $created, 'updated' => $updated];
            });
        } finally {
            $lock->release();
        }
    }


}