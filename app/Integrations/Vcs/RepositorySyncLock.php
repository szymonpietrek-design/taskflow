<?php

namespace App\Integrations\Vcs;

/** Every operation that mutates one repository uses the same cache lock key. */
final class RepositorySyncLock
{
    public static function key(int $repositoryId): string
    {
        return 'vcs-repo-sync-'.$repositoryId;
    }
}
