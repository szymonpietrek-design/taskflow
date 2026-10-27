<?php

namespace App\Http\Controllers\Settings;

use App\Support\I18n\LanguageCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LocaleUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocaleController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Language', [
            'locales' => app(LanguageCatalog::class)->options(),
            'selectedLocale' => app()->getLocale(),
        ]);
    }

    public function update(LocaleUpdateRequest $request): JsonResponse|RedirectResponse
    {
        $locale = (string) $request->validated('locale');

        $request->user()->update(['locale' => $locale]);

        app()->setLocale($locale);

        $localeCookie = cookie('locale', $locale, 60 * 24 * 365);

        // Account dropdown submits a JSON request. Avoid the Inertia redirect
        // round-trip: the browser reloads its original page after verifying
        // that the account setting was actually saved.
        if ($request->expectsJson()) {
            return response()->json([
                'locale' => $locale,
                'accountLocale' => $request->user()->fresh()?->locale,
            ])->withCookie($localeCookie)->header('Cache-Control', 'no-store');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Language updated.'),
        ]);

        // A choice made from the account menu keeps the current page open.
        // The standalone settings page retains its original redirect.
        $redirect = $request->boolean('from_menu')
            ? redirect()->back(302, [], route('language.edit'))
            : to_route('language.edit');

        return $redirect->withCookie($localeCookie);
    }
}
