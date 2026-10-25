<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run local-only scheduled cleanup when the Laravel scheduler is active.
if (app()->environment('local')) {
    Schedule::command('ai:prune-report-prompts')->daily();
}
