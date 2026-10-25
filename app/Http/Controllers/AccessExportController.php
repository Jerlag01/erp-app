<?php

namespace App\Http\Controllers;

use App\Filament\Resources\OnboardingSignatures\OnboardingSignatureResource;
use App\Models\AccessBadge;
use App\Models\OnboardingSignature;
use App\Models\SecurityTag;
use App\Support\PdfAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF exports in landscape, in the reader's language: the badge & electronic key list and the security
 * tag list (for the tab that is open), and the onboarding signatures (with the filters of the list).
 */
class AccessExportController extends Controller
{
    public function badges(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', AccessBadge::class) && PdfAccess::canGenerate($request->user(), 'access_control'), 403);

        $badges = $this->tab(AccessBadge::query()->with('employee'), (string) $request->query('tab', 'active'))->get()
            ->sortBy(fn (AccessBadge $badge): string => (string) $badge->employee?->last_name.' '.$badge->employee?->first_name)->values();

        return $this->pdf('access.badges-pdf', ['badges' => $badges], 'badges-elektronische-sleutels.pdf');
    }

    public function tags(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', SecurityTag::class) && PdfAccess::canGenerate($request->user(), 'access_control'), 403);

        $tags = $this->tab(SecurityTag::query()->with('employee'), (string) $request->query('tab', 'active'))->orderBy('tag_number')->get();

        return $this->pdf('access.tags-pdf', ['tags' => $tags], 'beveiligingstags.pdf');
    }

    public function onboarding(Request $request): Response
    {
        abort_unless(Gate::allows('viewAny', OnboardingSignature::class) && PdfAccess::canGenerate($request->user(), 'onboarding'), 403);

        $signatures = OnboardingSignatureResource::filteredQuery((array) $request->query('filters', []))->get()
            ->sortBy(fn ($signature): string => (string) $signature->user?->name.' '.$signature->document?->sort)->values();

        return $this->pdf('onboarding.signatures-pdf', ['signatures' => $signatures], 'onboarding-handtekeningen.pdf');
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    protected function tab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'deactivated' => $query->active(false),
            'all' => $query,
            default => $query->active(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function pdf(string $view, array $data, string $name): Response
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
