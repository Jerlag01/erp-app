<?php

namespace App\Support\Nas;

use App\Enums\AbsenceType;
use App\Enums\ProjectFileSection;
use App\Http\Controllers\ToolboxPdfController;
use App\Models\Absence;
use App\Models\AsbuiltFile;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use App\Models\EmployeeMedicalCheck;
use App\Models\Incident;
use App\Models\NasSyncItem;
use App\Models\NasTarget;
use App\Models\ProjectFileVersion;
use App\Models\TimesheetWeek;
use App\Models\ToolboxMeeting;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The copy of the platform's files on the NAS, for redundancy (Synology File Station API, SMB, FTP(S) or
 * SFTP: see NasTransports). Uploads keep going through the platform;
 * every file (and a few PDFs made from the data) is also written on the NAS, below the root folder
 * of every active NAS (Admin > NAS systems, e.g. "/ERP"; several NAS systems each get every file):
 *
 *   {category}/{project reference}/{upload zone}/…        project files, and As-built/{category}/…
 *   Personeel/{first last}/…                               driving licence, ID card, certificates, medical,
 *                                                          the personal details (PDF), Prestaties/ (weekly
 *                                                          PDFs), Medische attesten/ (absence certificates)
 *   Wagenpark/{plate}/{plate} - fiche.pdf                  the vehicle with its fuel passes (never a PIN)
 *   Incidenten/{date} - incident {id} - {name}.pdf
 *   Toolboxen/{month} {title}/Aanwezigheidslijst.pdf, Inhoud.pdf, Bijlagen/…
 *
 * Changes are put in an outbox (nas_sync_items) and sent after the response; "nas:sync" retries what
 * failed (e.g. while the NAS was offline) and "nas:sync --all" writes everything once. Nothing is ever
 * removed from the NAS.
 */
class NasSync
{
    public const EMPLOYEES = 'Personeel';

    public const FLEET = 'Wagenpark';

    public const INCIDENTS = 'Incidenten';

    public const TOOLBOX = 'Toolboxen';

    /** @var list<int> items queued in this request, sent after the response */
    protected array $queued = [];

    protected bool $deferred = false;

    /** Only this NAS (while copying everything to one new NAS). */
    protected ?NasTarget $only = null;

    /** While queueing everything: only the queue, the command (or the schedule) sends it. */
    protected bool $bulk = false;

    /**
     * Whether at least one NAS is active.
     */
    public function enabled(): bool
    {
        return NasTarget::query()->active()->exists();
    }

    /**
     * The active NAS systems a new copy goes to.
     *
     * @return EloquentCollection<int, NasTarget>
     */
    public function targets(): EloquentCollection
    {
        return $this->only !== null
            ? new EloquentCollection([$this->only])
            : NasTarget::query()->active()->orderBy('id')->get();
    }

    /* What goes where */

    public function projectFile(ProjectFileVersion $version): void
    {
        $file = $version->file;
        $project = $file?->project;

        if ($project === null) {
            return;
        }

        $section = ProjectFileSection::tryFrom((string) $file->section);
        $this->put([(string) $project->category?->code, $project->reference, $section?->getLabel() ?? (string) $file->section, $this->fileName($file->name, $version->original_name)],
            ['disk' => 'local', 'path' => $version->path]);
    }

    public function asbuiltFile(AsbuiltFile $file): void
    {
        $project = $file->asbuilt?->project;
        $path = $file->storagePath();

        if ($project === null || $path === null) {
            return;
        }

        $this->put([(string) $project->category?->code, $project->reference, 'As-built', $file->categoryEnum()?->folder() ?? 'Algemeen', $file->fileName()],
            ['disk' => 'local', 'path' => $path]);
    }

    /**
     * Everything of one employee: the personal details (PDF), driving licence, ID card, certificates and
     * medical checks.
     */
    public function employee(Employee $employee): void
    {
        $folder = [self::EMPLOYEES, $employee->fullName()];

        $this->put([...$folder, $employee->fullName().' - persoonsgegevens.pdf'], ['pdf' => 'employee', 'id' => $employee->id]);

        foreach ((array) $employee->driver_license_files as $path) {
            $this->put([...$folder, 'Rijbewijs', (string) (((array) $employee->driver_license_file_names)[$path] ?? basename($path))], ['disk' => Employee::DISK, 'path' => $path]);
        }

        foreach ((array) $employee->id_card_files as $path) {
            $this->put([...$folder, 'Identiteitskaart', (string) (((array) $employee->id_card_file_names)[$path] ?? basename($path))], ['disk' => Employee::DISK, 'path' => $path]);
        }

        $employee->certificates->each(fn (EmployeeCertificate $certificate) => $this->certificate($certificate));
        $employee->medicalChecks->each(fn (EmployeeMedicalCheck $check) => $this->medicalCheck($check));
    }

    public function certificate(EmployeeCertificate $certificate): void
    {
        if (blank($certificate->document) || $certificate->employee === null) {
            return;
        }

        $type = $certificate->type?->getLabel() ?? (string) $certificate->getRawOriginal('type');
        $this->put([self::EMPLOYEES, $certificate->employee->fullName(), 'Certificaten', trim($type.' - '.($certificate->document_name ?: basename($certificate->document)))],
            ['disk' => Employee::DISK, 'path' => $certificate->document]);
    }

    public function medicalCheck(EmployeeMedicalCheck $check): void
    {
        if ($check->employee === null) {
            return;
        }

        foreach ((array) $check->documents as $path) {
            $this->put([self::EMPLOYEES, $check->employee->fullName(), 'Medisch', trim(($check->checked_on?->format('Y-m-d') ?? '').' '.$check->documentName($path))],
                ['disk' => Employee::DISK, 'path' => $path]);
        }
    }

    public function timesheetWeek(TimesheetWeek $week): void
    {
        $employee = $week->user?->employee;

        if (blank($week->pdf_path) || $employee === null) {
            return;
        }

        $this->put([self::EMPLOYEES, $employee->fullName(), 'Prestaties', $week->week_start->format('o').'-W'.$week->week_start->format('W').'.pdf'],
            ['disk' => TimesheetWeek::DISK, 'path' => $week->pdf_path]);
    }

    public function absence(Absence $absence): void
    {
        $employee = $absence->user?->employee;

        if (blank($absence->document) || $employee === null) {
            return;
        }

        $folder = $absence->type === AbsenceType::Medical ? 'Medische attesten' : 'Afwezigheden';
        $this->put([self::EMPLOYEES, $employee->fullName(), $folder, $absence->start_date->format('Y-m-d').' '.($absence->document_name ?: basename($absence->document))],
            ['disk' => Absence::DISK, 'path' => $absence->document]);
    }

    public function vehicle(Vehicle $vehicle): void
    {
        $this->put([self::FLEET, $vehicle->plate_number, $vehicle->plate_number.' - fiche.pdf'], ['pdf' => 'vehicle', 'id' => $vehicle->id]);
    }

    public function incident(Incident $incident): void
    {
        $this->put([self::INCIDENTS, ($incident->reported_at ?? $incident->created_at)->format('Y-m-d').' - incident '.$incident->id.' - '.($incident->reporter_name ?: $incident->user?->name).'.pdf'],
            ['pdf' => 'incident', 'id' => $incident->id]);
    }

    public function toolbox(ToolboxMeeting $meeting, bool $withContent = true): void
    {
        $folder = [self::TOOLBOX, $meeting->meeting_month->format('Y-m').' '.$meeting->title];

        $this->put([...$folder, 'Aanwezigheidslijst.pdf'], ['pdf' => 'toolbox_attendance', 'id' => $meeting->id]);

        if ($withContent) {
            $this->put([...$folder, 'Inhoud.pdf'], ['pdf' => 'toolbox_content', 'id' => $meeting->id]);

            foreach ($meeting->attachmentList() as $attachment) {
                $this->put([...$folder, 'Bijlagen', $attachment['name']], ['disk' => ToolboxMeeting::FILE_DISK, 'path' => $attachment['path']]);
            }
        }
    }

    /**
     * Everything once (the first copy, or after the NAS was replaced); returns how many items wait.
     */
    public function queueAll(?NasTarget $only = null): int
    {
        $this->only = $only;
        $this->bulk = true;

        try {
            $this->queueEverything();
        } finally {
            $this->only = null;
            $this->bulk = false;
        }

        return NasSyncItem::query()->where('status', NasSyncItem::PENDING)->when($only, fn ($query) => $query->where('nas_target_id', $only->id))->count();
    }

    protected function queueEverything(): void
    {
        ProjectFileVersion::query()->with('file.project.category')->whereIn('id', ProjectFileVersion::query()->selectRaw('max(id)')->groupBy('project_file_id'))->lazyById()->each(fn (ProjectFileVersion $version) => $this->projectFile($version));
        AsbuiltFile::query()->with(['asbuilt.project.category', 'libraryFile'])->lazyById()->each(fn (AsbuiltFile $file) => $this->asbuiltFile($file));
        Employee::query()->with(['certificates', 'medicalChecks'])->lazyById()->each(fn (Employee $employee) => $this->employee($employee));
        TimesheetWeek::query()->whereNotNull('pdf_path')->with('user.employee')->lazyById()->each(fn (TimesheetWeek $week) => $this->timesheetWeek($week));
        Absence::query()->whereNotNull('document')->with('user.employee')->lazyById()->each(fn (Absence $absence) => $this->absence($absence));
        Vehicle::query()->lazyById()->each(fn (Vehicle $vehicle) => $this->vehicle($vehicle));
        Incident::query()->lazyById()->each(fn (Incident $incident) => $this->incident($incident));
        ToolboxMeeting::query()->lazyById()->each(fn (ToolboxMeeting $meeting) => $this->toolbox($meeting));
    }

    /* The outbox */

    /**
     * Puts a file in the outbox (replacing what was waiting for the same place) and sends it after the response.
     *
     * @param  list<string>  $segments  folders and the file name, below the root folder
     * @param  array<string, mixed>  $source
     */
    public function put(array $segments, array $source): void
    {
        $targets = $this->targets();

        if ($targets->isEmpty()) {
            return;
        }

        $path = implode('/', array_map(fn (string $segment): string => self::clean($segment), $segments));

        // One copy per NAS, each with its own state.
        foreach ($targets as $nas) {
            $id = NasSyncItem::query()->updateOrCreate(
                ['target_hash' => hash('sha256', $nas->id.'|'.$path)],
                ['nas_target_id' => $nas->id, 'target' => $path, 'source' => $source, 'status' => NasSyncItem::PENDING, 'attempts' => 0, 'last_error' => null],
            )->id;

            if (! $this->bulk) {
                $this->queued[] = $id;
            }
        }

        // Everything at once (nas:export, "copy everything"): sent in batches, not all after the response.
        if ($this->bulk) {
            return;
        }

        if (! $this->deferred) {
            $this->deferred = true;
            defer(function (): void {
                $ids = $this->queued;
                $this->queued = [];
                $this->deferred = false;
                $this->send(NasSyncItem::query()->whereKey($ids)->get());
            });
        }
    }

    /**
     * Sends what is waiting (and what failed, up to MAX_ATTEMPTS), at most $limit items.
     *
     * @return array{sent: int, failed: int}
     */
    public function process(int $limit = 500, ?NasTarget $only = null): array
    {
        return $this->send(NasSyncItem::query()
            ->when($only, fn ($query) => $query->where('nas_target_id', $only->id))
            ->whereIn('status', [NasSyncItem::PENDING, NasSyncItem::FAILED])
            ->where('attempts', '<', NasSyncItem::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit($limit)
            ->get());
    }

    /**
     * @param  Collection<int, NasSyncItem>  $items
     * @return array{sent: int, failed: int}
     */
    public function send(Collection $items): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        $contents = [];

        // Per NAS: its own connection and main folder; the PDFs are made once for all of them.
        foreach ($items->groupBy('nas_target_id') as $targetId => $group) {
            $nas = NasTarget::query()->find($targetId);

            if ($nas === null || ! $nas->is_active) {
                continue;
            }

            try {
                $client = app()->bound(NasTransport::class) ? app(NasTransport::class) : NasTransports::for($nas);
            } catch (Throwable $exception) {
                // Not set up well (e.g. no host): every item waits with the reason.
                $group->each(fn (NasSyncItem $item) => $this->failed($item, $exception));
                $result['failed'] += $group->count();

                continue;
            }

            foreach ($group as $item) {
                try {
                    $key = json_encode($item->source);
                    $data = array_key_exists($key, $contents) ? $contents[$key] : ($contents[$key] = $this->contents($item->source));

                    if ($data !== null) {
                        $folder = trim(dirname($item->target), '.');
                        $client->upload(rtrim($nas->rootFolder().'/'.$folder, '/'), basename($item->target), $data);
                    }

                    $item->update(['status' => NasSyncItem::DONE, 'synced_at' => now(), 'last_error' => $data === null ? __('erp.nas.source_missing') : null]);
                    $result['sent']++;
                } catch (Throwable $exception) {
                    $this->failed($item, $exception);
                    $result['failed']++;
                    Log::warning('NAS copy failed', ['nas' => $nas->name, 'target' => $item->target, 'error' => $exception->getMessage()]);
                }
            }

            $client->close();
        }

        return $result;
    }

    protected function failed(NasSyncItem $item, Throwable $exception): void
    {
        $item->update(['status' => NasSyncItem::FAILED, 'attempts' => $item->attempts + 1, 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);
    }

    /**
     * The bytes to write: a stored file, or a PDF made now. Null when the file no longer exists.
     *
     * @param  array<string, mixed>  $source
     */
    public function contents(array $source): ?string
    {
        if (isset($source['disk'], $source['path'])) {
            $disk = Storage::disk((string) $source['disk']);

            return $disk->exists((string) $source['path']) ? (string) $disk->get((string) $source['path']) : null;
        }

        $id = (int) ($source['id'] ?? 0);

        return match ($source['pdf'] ?? null) {
            'employee' => ($employee = Employee::query()->with(['user', 'certificates', 'medicalChecks', 'country'])->find($id))
                ? Pdf::loadView('nas.employee-pdf', ['employee' => $employee])->setPaper('a4')->output() : null,
            'vehicle' => ($vehicle = Vehicle::query()->with(['team', 'driver', 'location', 'fuelPasses'])->find($id))
                ? Pdf::loadView('nas.vehicle-pdf', ['vehicle' => $vehicle])->setPaper('a4')->output() : null,
            'incident' => ($incident = Incident::query()->with(['user', 'place', 'handler'])->find($id))
                ? Pdf::loadView('incidents.pdf', ['incidents' => collect([$incident])])->setPaper('a4', 'landscape')->output() : null,
            'toolbox_attendance' => ($meeting = ToolboxMeeting::query()->find($id)) ? ToolboxPdfController::render($meeting) : null,
            'toolbox_content' => ($meeting = ToolboxMeeting::query()->find($id))
                ? Pdf::loadView('nas.toolbox-content-pdf', ['meeting' => $meeting])->setPaper('a4')->output() : null,
            default => null,
        };
    }

    /**
     * A name that is valid as a folder or file name on the NAS.
     */
    public static function clean(string $segment): string
    {
        $segment = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', '-', $segment) ?? '');
        $segment = trim($segment, '. ');

        return $segment === '' ? '-' : mb_substr($segment, 0, 200);
    }

    protected function fileName(string $name, string $original): string
    {
        $extension = pathinfo($original, PATHINFO_EXTENSION);

        return $extension !== '' && ! str_ends_with(mb_strtolower($name), '.'.mb_strtolower($extension)) ? $name.'.'.$extension : $name;
    }
}
