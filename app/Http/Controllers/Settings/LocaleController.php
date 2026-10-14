<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UiLocale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LocaleUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocaleController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Language', [
            'locales' => UiLocale::options(),
            'selectedLocale' => $request->user()->locale,
        ]);
    }

    public function update(LocaleUpdateRequest $request): RedirectResponse
    {
        $locale = (string) $request->validated('locale');

        $request->user()->update(['locale' => $locale]);

        app()->setLocale($locale);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Language updated.'),
        ]);

        return to_route('language.edit')->withCookie(
            cookie('locale', $locale, 60 * 24 * 365),
        );
    }
}
