<?php

namespace App\Http\Controllers;

use App\Models\BatteryDossier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The floor plan editor of a battery dossier as a page for the mobile app's web view, opened through
 * a signed link the API gives to the signed-in technician. The page draws with the platform's editor
 * and hands the drawing back to the app, which saves it through the API.
 */
class MobileFloorPlanController
{
    public function __invoke(Request $request, BatteryDossier $dossier): View
    {
        $user = User::query()->find($request->integer('user'));
        abort_unless($user && $user->is_active && Gate::forUser($user)->allows('view', $dossier), 403);

        if (in_array($request->query('lang'), array_keys(config('app.locales')), true)) {
            App::setLocale($request->query('lang'));
        }

        return view('batteries.floor-plan.mobile', [
            'dossier' => $dossier,
            'editable' => Gate::forUser($user)->allows('update', $dossier) && $dossier->isEditableBy($user),
        ]);
    }
}
