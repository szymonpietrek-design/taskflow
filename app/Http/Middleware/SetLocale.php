<?php

namespace App\Http\Middleware;

use App\Support\I18n\LanguageCatalog;
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

        App::setLocale(app(LanguageCatalog::class)->normalize($candidate));

        return $next($request);
    }
}
