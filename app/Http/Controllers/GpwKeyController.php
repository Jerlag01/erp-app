<?php

namespace App\Http\Controllers;

use App\Actions\Gpw\DecideGpw;
use App\Models\GpwRequestKey;
use App\Support\ClientPortal;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * The buttons "Approve" and "Refuse" in a GPW request mail: with the recipient's personal key the
 * request is decided without logging in. Both open the request with an "Approve" and a "Refuse" button;
 * opening a link decides nothing (refusing asks for the reason). A key that is used, replaced, expired or of a decided request no longer
 * works: the client then logs in to the portal (where a decided request shows its outcome).
 */
class GpwKeyController
{
    public function approve(string $token): View|RedirectResponse
    {
        return $this->page($token, 'approve');
    }

    public function reject(string $token): View|RedirectResponse
    {
        return $this->page($token, 'reject');
    }

    public function decide(Request $request, string $token, DecideGpw $decide): View|RedirectResponse
    {
        $key = GpwRequestKey::usable($token);

        if ($key === null) {
            return $this->toPortal();
        }

        App::setLocale($key->locale);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);

        // Every project of the mail still waiting (decided in the meantime: the page shows the outcome).
        $decide->decideMail($key->gpwRequest, $data['decision'] === 'approve', $key->name, $data['note'] ?? null);

        // Decided: every key of this mail is done.
        GpwRequestKey::query()->whereIn('gpw_request_id', $key->gpwRequest->batch()->pluck('id')->all())->whereNull('used_at')->update(['used_at' => now()]);

        return view('gpw.decision', ['gpw' => $key->gpwRequest->fresh(['project.workSite', 'requester']), 'mode' => null]);
    }

    protected function page(string $token, string $mode): View|RedirectResponse
    {
        $key = GpwRequestKey::usable($token);

        if ($key === null) {
            return $this->toPortal();
        }

        App::setLocale($key->locale);

        return view('gpw.decision', ['gpw' => $key->gpwRequest, 'mode' => $mode, 'action' => route('gpw.key.decide', $token), 'decider' => $key->name]);
    }

    /**
     * The link no longer works: log in to the portal, where the message says why.
     */
    protected function toPortal(): RedirectResponse
    {
        Notification::make()->title(__('erp.gpw.page.link_used'))->body(__('erp.gpw.page.link_used_body'))->warning()->persistent()->send();

        return redirect()->to(ClientPortal::loginUrl());
    }
}
