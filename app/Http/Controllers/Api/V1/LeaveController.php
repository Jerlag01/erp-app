<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\NotifyAbsenceAdministrators;
use App\Actions\ReportSickness;
use App\Actions\SubmitVacationRequest;
use App\Enums\VacationStatus;
use App\Enums\VacationType;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\User;
use App\Models\VacationRequest;
use App\Support\Timesheets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Leave in the mobile app: the vacation and overtime balances, requesting vacation or overtime as
 * paid leave (approved like on the platform), withdrawing a request, reporting sickness with the
 * certificate (a photo or PDF) and adding a certificate later.
 */
class LeaveController extends Controller
{
    public function __construct(protected Timesheets $timesheets) {}

    public function index(Request $request): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        /** @var User $user */
        $user = $request->user();
        $year = (int) $request->query('year', date('Y'));
        $year = $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');

        $requests = $user->vacationRequests()->with('reviewer')
            ->where(fn ($query) => $query->whereYear('start_date', $year)->orWhere('status', VacationStatus::Pending))
            ->orderByDesc('start_date')
            ->get();
        $absences = Absence::query()->where('user_id', $user->id)->whereYear('start_date', $year)->orderByDesc('start_date')->get();

        return response()->json(['data' => [
            'year' => $year,
            'vacation' => $user->vacationBalance($year),
            'overtime' => $this->timesheets->enabled() ? $this->timesheets->overtimeBalance($user) : null,
            'types' => array_map(fn (VacationType $type): array => ['value' => $type->value, 'label' => $type->getLabel()], $this->types()),
            'requests' => $requests->map(fn (VacationRequest $request): array => $this->request($request, $user))->values(),
            'absences' => $absences->map(fn (Absence $absence): array => $this->absence($absence, $user))->values(),
        ]]);
    }

    public function store(Request $request, SubmitVacationRequest $submit): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (VacationType $type): string => $type->value, $this->types()))],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $created = $submit->handle($request->user(), $data);

        return response()->json(['message' => __('erp.vacations.submitted'), 'data' => $this->request($created->fresh('reviewer'), $request->user())], 201);
    }

    public function cancel(Request $request, VacationRequest $vacationRequest): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        abort_unless($vacationRequest->canBeCancelledBy($request->user()), 403);
        $vacationRequest->update(['status' => VacationStatus::Cancelled]);

        return response()->json(['message' => __('erp.vacations.cancelled'), 'data' => $this->request($vacationRequest->fresh('reviewer'), $request->user())]);
    }

    public function reportSickness(Request $request, ReportSickness $report): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'document' => $this->documentRules(required: false),
        ]);

        [$path, $name] = $this->storeDocument($request->file('document'), $user);
        $absence = $report->handle($user, $data, $path, $name);

        return response()->json(['message' => __('erp.absences.reported'), 'data' => $this->absence($absence->fresh(), $user)], 201);
    }

    public function uploadDocument(Request $request, Absence $absence): JsonResponse
    {
        if ($blocked = $this->blocked()) {
            return $blocked;
        }

        /** @var User $user */
        $user = $request->user();
        abort_unless(Gate::forUser($user)->allows('uploadDocument', $absence), 403);
        $request->validate(['document' => $this->documentRules(required: true)]);

        [$path, $name] = $this->storeDocument($request->file('document'), $user);
        $absence->attachDocument((string) $path, $name, Absence::SOURCE_EMPLOYEE);
        app(NotifyAbsenceAdministrators::class)->handle($absence->load('user'), NotifyAbsenceAdministrators::UPLOADED);

        return response()->json(['message' => __('erp.absences.uploaded'), 'data' => $this->absence($absence->fresh(), $user)]);
    }

    /**
     * @return list<VacationType>
     */
    protected function types(): array
    {
        return $this->timesheets->enabled() ? VacationType::cases() : [VacationType::Vacation];
    }

    /**
     * @return list<mixed>
     */
    protected function documentRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif,image/avif,image/bmp,image/tiff,application/pdf', 'max:'.Absence::DOCUMENT_MAX_KILOBYTES];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function storeDocument(?UploadedFile $file, User $user): array
    {
        if (! $file) {
            return [null, null];
        }

        return [$file->store(Absence::directoryFor($user->id), Absence::DISK), $file->getClientOriginalName() ?: null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function request(VacationRequest $request, User $user): array
    {
        return [
            'id' => $request->id,
            'type' => $request->type->value,
            'type_label' => $request->type->getLabel(),
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'half_day' => (bool) $request->half_day,
            'days' => (float) $request->days,
            'hours' => $request->isOvertime() && $this->timesheets->enabled() ? round((float) $request->days * $this->timesheets->dayHours(), 2) : null,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'status_label' => $request->status->getLabel(),
            'review_note' => $request->review_note,
            'reviewer' => $request->reviewer?->name,
            'can_cancel' => $request->canBeCancelledBy($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function absence(Absence $absence, User $user): array
    {
        return [
            'id' => $absence->id,
            'type' => $absence->type->value,
            'type_label' => $absence->type->getLabel(),
            'start_date' => $absence->start_date->toDateString(),
            'end_date' => $absence->end_date->toDateString(),
            'half_day' => (bool) $absence->half_day,
            'days' => (float) $absence->days,
            'note' => $absence->note,
            'has_document' => $absence->hasDocument(),
            'document_name' => $absence->document_name,
            'document_by_admin' => $absence->document_source === Absence::SOURCE_ADMIN,
            'can_upload' => Gate::forUser($user)->allows('uploadDocument', $absence),
        ];
    }

    protected function blocked(): ?JsonResponse
    {
        // Users without a prestation sheet (e.g. HR) have no leave either.
        return modules()->vacations() && request()->user()?->hasTimesheet() ? null : response()->json(['message' => __('erp.mobile.errors.module_off'), 'code' => 'module_off'], 403);
    }
}
