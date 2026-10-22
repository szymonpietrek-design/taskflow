<?php

namespace App\Integrations\Vcs;

use App\Models\RepositoryChangeRequest;
use App\Models\RepositoryConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Only enrich stored, merged PRs; no import of historic PRs or change to the commit cursor. */
final class RepositoryChangeRequestParticipantBackfiller
{
    private const BATCH_SIZE = 10;

    public function __construct(private readonly RepositoryChangeRequestParticipantProviders $providers) {}

    /** @return array{checked:int,remaining:int} */
    public function backfill(RepositoryConnection $repository): array
    {
        $lock = Cache::lock(RepositorySyncLock::key($repository->id), 120);
        if (! $lock->get()) {
            throw new RuntimeException('A repository operation is already in progress.');
        }

        try {
            $repository = RepositoryConnection::query()->with('connection.instance')->findOrFail($repository->id);
            $pending = fn () => RepositoryChangeRequest::query()
                ->where('repository_connection_id', $repository->id)
                ->where('state', RepositoryChangeRequest::STATE_MERGED)
                ->whereNotNull('merged_at')
                ->whereNull('merged_by_checked_at');

            $requests = $pending()->orderBy('id')->limit(self::BATCH_SIZE)->get(['id', 'number']);
            if ($requests->isEmpty()) {
                return ['checked' => 0, 'remaining' => 0];
            }

            $identities = $this->providers->forRepository($repository)
                ->mergedByForStoredRequests($repository, $requests->pluck('number')->map(fn ($number) => (int) $number)->all());

            // Validate the entire remote batch before changing a single database row.
            foreach ($requests as $request) {
                if (! array_key_exists($request->number, $identities)) {
                    throw new RuntimeException('Incomplete pull request participant lookup.');
                }
                $identity = $identities[$request->number];
                if (! is_array($identity) || ! array_key_exists('external_user_id', $identity)
                    || ! array_key_exists('login', $identity)) {
                    throw new RuntimeException('Invalid pull request participant data.');
                }
                $id = $identity['external_user_id'];
                $login = $identity['login'];
                if ($id !== null && (! is_string($id) || trim($id) === '' || mb_strlen($id) > 190)) {
                    throw new RuntimeException('Invalid external merging user ID.');
                }
                if ($login !== null && (! is_string($login) || trim($login) === '' || mb_strlen($login) > 190)) {
                    throw new RuntimeException('Invalid merging user login.');
                }
                if (($id === null) !== ($login === null)) {
                    throw new RuntimeException('Incomplete external merging identity.');
                }
            }

            $checked = DB::transaction(function () use ($repository, $requests, $identities): int {
                RepositoryConnection::query()->whereKey($repository->id)->lockForUpdate()->firstOrFail();
                $count = 0;
                foreach ($requests as $request) {
                    $identity = $identities[$request->number];
                    $count += RepositoryChangeRequest::query()->whereKey($request->id)
                        ->where('repository_connection_id', $repository->id)
                        ->where('state', RepositoryChangeRequest::STATE_MERGED)
                        ->whereNotNull('merged_at')->whereNull('merged_by_checked_at')
                        ->update([
                            'merged_by_external_user_id' => $identity['external_user_id'],
                            'merged_by_login' => $identity['login'],
                            'merged_by_checked_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
                return $count;
            });

            return ['checked' => $checked, 'remaining' => $pending()->count()];
        } finally {
            $lock->release();
        }
    }
}
