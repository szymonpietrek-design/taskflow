<?php

namespace App\Http\Middleware;

use App\Enums\UiLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->user()?->locale
            ?? $request->cookie('locale')
            ?? config('app.locale');

        $locale = UiLocale::tryFrom((string) $candidate)
            ?? UiLocale::ENGLISH;

        App::setLocale($locale->value);

        return $next($request);
    }
}
