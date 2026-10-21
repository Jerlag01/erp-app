<?php

namespace App\Http\Controllers;

use App\Actions\Gpw\DecideGpw;
use App\Models\GpwRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The page behind the link in a GPW request mail: the contact person sees the request and approves
 * or refuses it with a button (opening the link alone changes nothing, so mail scanners that open
 * links cannot approve it). The link is signed and valid until the end of the requested period.
 */
class GpwDecisionController
{
    public function show(Request $request, GpwRequest $gpwRequest): View
    {
        App::setLocale($this->locale($request, $gpwRequest));
        $gpwRequest->loadMissing('project.workSite', 'requester');

        return view('gpw.decision', ['gpw' => $gpwRequest]);
    }

    public function decide(Request $request, GpwRequest $gpwRequest, DecideGpw $decide): RedirectResponse
    {
        App::setLocale($this->locale($request, $gpwRequest));
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);

        try {
            $data['decision'] === 'approve'
                ? $decide->approve($gpwRequest, $gpwRequest->contact_name, note: $data['note'] ?? null)
                : $decide->reject($gpwRequest, $gpwRequest->contact_name, note: $data['note'] ?? null);
        } catch (ValidationException) {
            // Decided in the meantime (e.g. in the client portal): the page shows the outcome.
        }

        return redirect()->to($request->fullUrl());
    }

    /**
     * The language of the contact the link was mailed to (part of the signed link).
     */
    protected function locale(Request $request, GpwRequest $gpwRequest): string
    {
        return in_array($request->query('lang'), ['nl', 'fr', 'en'], true) ? $request->query('lang') : $gpwRequest->locale;
    }
}
