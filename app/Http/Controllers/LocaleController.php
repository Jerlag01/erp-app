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

        // Back to the page the switch was used on, never to another site (the Referer header can be forged).
        $previous = url()->previous();

        return redirect()->to(parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : url('/'));
    }
}
