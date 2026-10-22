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
    public function __construct(private readonly RepositoryChangeRequestProviders $providers) {}

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
            if (count($snapshots) > 500) {
                throw new RuntimeException('Pull request batch exceeded the safety limit.');
            }
            $externalIds = [];
            $numbers = [];
            foreach ($snapshots as $snapshot) {
                if (! is_array($snapshot) || ! isset($snapshot['external_change_request_id'], $snapshot['number'], $snapshot['remote_updated_at'])) {
                    throw new RuntimeException('Invalid change request batch.');
                }
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
