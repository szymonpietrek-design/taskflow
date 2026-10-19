<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetSystemAdmin extends Command
{
    protected $signature = 'users:system-admin {email : Existing account email} {--revoke : Remove administrator access}';

    protected $description = 'Grant or revoke the global TaskFlow administrator role for an existing account';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->lockForUpdate()->first();

            if (! $user) {
                $this->error('No user found with that email.');
                return self::FAILURE;
            }

            if ($this->option('revoke') && $user->is_system_admin) {
                $activeAdmins = User::query()->where('is_system_admin', true)->whereNull('disabled_at')->lockForUpdate()->get(['id'])->count();
                if ($user->disabled_at === null && $activeAdmins <= 1) {
                    $this->error('Cannot revoke the last active system administrator.');
                    return self::FAILURE;
                }
            }

            $user->is_system_admin = ! $this->option('revoke');
            $user->save();
            $this->info($user->is_system_admin ? 'System administrator role granted.' : 'System administrator role removed.');

            return self::SUCCESS;
        });
    }
}
