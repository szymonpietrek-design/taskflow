<?php

namespace App\Integrations\Vcs\Contracts;

use App\Models\RepositoryConnection;
use Illuminate\Support\Carbon;

interface RepositoryChangeRequestProvider
{
    /** @return list<array<string, mixed>> Normalized change-request snapshots */
    public function updatedSince(RepositoryConnection $repository, ?Carbon $since): array;
}
