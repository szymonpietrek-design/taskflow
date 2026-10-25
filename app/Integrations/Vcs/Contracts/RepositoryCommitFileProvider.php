<?php

namespace App\Integrations\Vcs\Contracts;

use App\Models\RepositoryConnection;

/** A provider-neutral, complete, bounded file list for immutable stored commit SHAs. */
interface RepositoryCommitFileProvider
{
    /**
     * @param list<string> $shas At most five SHAs already stored for this repository.
     * @return array<string,list<array{path:string,path_hash:string,previous_path:?string,status:string,additions:int,deletions:int,changes:int}>>
     */
    public function filesForStoredCommits(RepositoryConnection $repository, array $shas): array;
}
