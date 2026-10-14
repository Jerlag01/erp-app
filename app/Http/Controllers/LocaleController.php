<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The language switch in the top bar: stored on the user, so it follows them to every device.
 */
class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in(array_keys(config('app.locales')))]]);

        $request->user()->update(['locale' => $data['locale']]);

        return redirect()->back();
    }
}
