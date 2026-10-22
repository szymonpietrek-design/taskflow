<?php

namespace App\Providers;

use App\Contracts\Ai\ChangelogTextGenerator;
use App\Contracts\Ai\ReportTextGenerator;
use App\Contracts\Ai\PullRequestAnalysisGenerator;
use App\Services\Ai\PrismChangelogGenerator;
use App\Services\Ai\PrismReportGenerator;
use App\Services\Ai\PrismPullRequestAnalysisGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChangelogTextGenerator::class, PrismChangelogGenerator::class);
        $this->app->bind(ReportTextGenerator::class, PrismReportGenerator::class);
        $this->app->bind(PullRequestAnalysisGenerator::class, PrismPullRequestAnalysisGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-system-users', fn (User $user): bool => $user->is_system_admin && $user->disabled_at === null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
