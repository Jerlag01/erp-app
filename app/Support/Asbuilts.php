<?php

namespace App\Support;

use App\Enums\AsbuiltCategory;
use App\Enums\AsbuiltStatus;
use App\Enums\MailTemplateKey;
use App\Filament\Resources\Asbuilts\AsbuiltResource;
use App\Mail\SystemMail;
use App\Models\Asbuilt;
use App\Models\AsbuiltFile;
use App\Models\AsbuiltLibraryFile;
use App\Models\ClientUser;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

/**
 * The as-built workflow: an as-built is made when a project becomes "executed" (not for the
 * excluded categories) with the standard files of its COW-code; employees and the client add files;
 * the Normec BTV inspection is planned; uploading the BTV report closes it into a ZIP with a folder
 * per category, which the client of the project downloads (and is told about by e-mail).
 */
class Asbuilts
{
    public function enabled(): bool
    {
        return modules()->asbuilt();
    }

    /**
     * @return list<string>
     */
    public static function excludedCategories(): array
    {
        return array_values(array_map('strval', (array) settings()->get('asbuilt.excluded_categories', ['600', '604', '605', '606', '607', '608.1'])));
    }

    public function appliesTo(Project $project): bool
    {
        return $this->enabled() && $project->category !== null && ! in_array((string) $project->category->code, self::excludedCategories(), true);
    }

    /**
     * The as-built of a project, made when it does not exist yet, with the standard files: those of
     * its COW-code and the general ones (not the technical documents, which are picked by hand).
     */
    public function createFor(Project $project): ?Asbuilt
    {
        if (! $this->appliesTo($project)) {
            return null;
        }

        return DB::transaction(function () use ($project): Asbuilt {
            $existing = Asbuilt::query()->where('project_id', $project->id)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            $asbuilt = Asbuilt::query()->create(['project_id' => $project->id, 'status' => AsbuiltStatus::InProgress]);
            $this->attach($asbuilt, $this->defaultFiles($project)->pluck('id')->all());

            return $asbuilt;
        });
    }

    /**
     * @return Collection<int, AsbuiltLibraryFile>
     */
    public function defaultFiles(Project $project): Collection
    {
        $siteId = $project->work_site_id;

        return AsbuiltLibraryFile::query()
            ->where('category', '!=', AsbuiltCategory::TechnicalDocuments->value)
            ->where(fn (Builder $query) => $query
                ->when($siteId, fn (Builder $query) => $query->orWhere('work_site_id', $siteId))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('work_site_id')
                    ->whereNotIn('category', [AsbuiltCategory::EvacuationRoutes->value, AsbuiltCategory::ExternalInfluences->value])))
            ->get();
    }

    /**
     * A new standard file also goes to the as-builts still open for it: of its COW-code, or every
     * open one for a general file. Technical documents are only picked by hand.
     */
    public function spreadToOpen(AsbuiltLibraryFile $file): int
    {
        if ($file->category->isRegister() || ($file->category->perCowCode() && $file->work_site_id === null)) {
            return 0;
        }

        $asbuilts = Asbuilt::query()
            ->where('status', '!=', AsbuiltStatus::Closed)
            ->when($file->work_site_id, fn (Builder $query, $site) => $query->whereHas('project', fn (Builder $query) => $query->where('work_site_id', $site)))
            ->get();

        return $asbuilts->sum(fn (Asbuilt $asbuilt): int => $this->attach($asbuilt, [$file->id]));
    }

    /**
     * Links files of the register (once each).
     *
     * @param  list<int>  $libraryIds
     */
    public function attach(Asbuilt $asbuilt, array $libraryIds): int
    {
        $added = 0;

        foreach (AsbuiltLibraryFile::query()->whereKey($libraryIds)->get() as $library) {
            $file = AsbuiltFile::query()->firstOrCreate(
                ['asbuilt_id' => $asbuilt->id, 'asbuilt_library_file_id' => $library->id],
                ['category' => $library->category->value],
            );
            $added += (int) $file->wasRecentlyCreated;
        }

        return $added;
    }

    /**
     * Adds uploaded files (already stored on the private disk) to a category, or to the client's
     * general zone. Evacuation routes and external influences also go to the register of the COW-code,
     * technical documents to the register, so later as-builts reuse them.
     *
     * @param  list<string>  $paths
     * @param  array<string, string>  $names  original file names by path
     * @return list<AsbuiltFile>
     */
    public function addFiles(Asbuilt $asbuilt, string $category, array $paths, array $names, ?User $user = null, ?ClientUser $client = null): array
    {
        $type = AsbuiltCategory::tryFrom($category);

        if ($type === null && $category !== AsbuiltCategory::GENERAL) {
            throw ValidationException::withMessages(['category' => __('validation.in', ['attribute' => 'category'])]);
        }

        if ($asbuilt->isClosed()) {
            throw ValidationException::withMessages(['files' => __('erp.asbuilt.errors.closed')]);
        }

        $disk = Storage::disk(Asbuilt::DISK);
        $site = $asbuilt->project?->work_site_id;
        $files = [];

        foreach (array_values(array_filter($paths)) as $path) {
            $name = (string) ($names[$path] ?? basename($path));
            $size = $disk->exists($path) ? (int) $disk->size($path) : 0;
            $owner = ['uploaded_by' => $user?->id, 'uploaded_by_client_user_id' => $client?->id];

            if ($type !== null && $type->isReusable() && ($type->isRegister() || $site !== null)) {
                $library = AsbuiltLibraryFile::query()->create([
                    'category' => $type,
                    'work_site_id' => $type->perCowCode() ? $site : null,
                    'title' => pathinfo($name, PATHINFO_FILENAME),
                    'path' => $path,
                    'original_name' => $name,
                    'size' => $size,
                    ...$owner,
                ]);
                $files[] = AsbuiltFile::query()->create(['asbuilt_id' => $asbuilt->id, 'category' => $type->value, 'asbuilt_library_file_id' => $library->id]);

                continue;
            }

            $files[] = AsbuiltFile::query()->create([
                'asbuilt_id' => $asbuilt->id,
                'category' => $category,
                'path' => $path,
                'original_name' => $name,
                'size' => $size,
                ...$owner,
            ]);
        }

        if ($client !== null && $files !== []) {
            $this->notifyEmployeesOfClientUpload($asbuilt, $client, count($files));
        }

        return $files;
    }

    public function planBtv(Asbuilt $asbuilt, CarbonInterface|string|null $date): void
    {
        if ($asbuilt->isClosed()) {
            throw ValidationException::withMessages(['btv_date' => __('erp.asbuilt.errors.closed')]);
        }

        $asbuilt->update([
            'btv_date' => $date,
            'status' => $date ? AsbuiltStatus::BtvPlanned : AsbuiltStatus::InProgress,
        ]);
    }

    /**
     * The BTV report: stored in "BTV reports", after which the as-built closes.
     *
     * @param  list<string>  $paths
     * @param  array<string, string>  $names
     */
    public function uploadBtvReport(Asbuilt $asbuilt, array $paths, array $names, User $user): void
    {
        if ($asbuilt->btv_date === null) {
            throw ValidationException::withMessages(['btv_report' => __('erp.asbuilt.errors.no_btv_date')]);
        }

        $this->addFiles($asbuilt, AsbuiltCategory::BtvReports->value, $paths, $names, $user);
        $this->close($asbuilt);
    }

    /**
     * Zips every file into a folder per category, closes the as-built and tells the client.
     */
    public function close(Asbuilt $asbuilt): void
    {
        $asbuilt->loadMissing('project', 'files.libraryFile');
        $disk = Storage::disk(Asbuilt::DISK);
        $temporary = tempnam(sys_get_temp_dir(), 'asbuilt');
        $zip = new ZipArchive;
        $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $used = [];

        foreach (AsbuiltCategory::ordered() as $category) {
            $zip->addEmptyDir($category->folder());
        }

        foreach ($asbuilt->files as $file) {
            $category = $file->categoryEnum();
            $path = $file->storagePath();

            if ($category === null || $path === null || ! $disk->exists($path)) {
                continue;
            }

            $name = $this->unique($category->folder().'/'.$this->safeName($file->fileName()), $used);
            $used[] = $name;
            $zip->addFile($disk->path($path), $name);
        }

        $zip->close();

        $target = 'asbuilt/'.$asbuilt->id.'/'.Str::random(8).'-'.Str::slug($asbuilt->project?->reference ?? 'asbuilt').'-asbuilt.zip';
        $previous = $asbuilt->zip_path;
        $disk->put($target, fopen($temporary, 'rb'));
        @unlink($temporary);

        if (filled($previous) && $previous !== $target) {
            $disk->delete($previous);
        }

        $asbuilt->update(['status' => AsbuiltStatus::Closed, 'closed_at' => now(), 'zip_path' => $target]);
        $this->notifyClients($asbuilt->refresh());
    }

    /**
     * Back to work: the ZIP is removed until the as-built closes again.
     */
    public function reopen(Asbuilt $asbuilt): void
    {
        if (filled($asbuilt->zip_path)) {
            Storage::disk(Asbuilt::DISK)->delete($asbuilt->zip_path);
        }

        $asbuilt->update([
            'status' => $asbuilt->btv_date ? AsbuiltStatus::BtvPlanned : AsbuiltStatus::InProgress,
            'closed_at' => null,
            'zip_path' => null,
        ]);
    }

    /**
     * Client portal users who follow this project's as-built.
     *
     * @return Collection<int, ClientUser>
     */
    public function clients(Asbuilt $asbuilt): Collection
    {
        return ClientUser::query()->where('is_active', true)->get()
            ->filter(fn (ClientUser $client): bool => $client->may(ClientPortal::ASBUILT)
                && $client->restrictProjects(Project::query()->whereKey($asbuilt->project_id))->exists())
            ->values();
    }

    protected function notifyClients(Asbuilt $asbuilt): void
    {
        $project = $asbuilt->project;
        $sent = false;

        foreach ($this->clients($asbuilt) as $client) {
            $locale = $client->preferredLocale() ?? config('app.locale');

            try {
                Mail::to($client->email, $client->name)->send(new SystemMail(MailTemplateKey::AsbuiltAvailable, $locale, [
                    'name' => $client->name,
                    'project' => $project?->reference ?? '',
                    'cow_code' => $project?->cow_code ?? '—',
                    'file_name' => $asbuilt->zipName(),
                    'portal_url' => ClientPortal::loginUrl(),
                ]));
                $sent = true;
            } catch (Throwable $exception) {
                Log::warning('As-built mail could not be sent', ['asbuilt' => $asbuilt->id, 'client' => $client->id, 'error' => $exception->getMessage()]);
            }
        }

        if ($sent) {
            $asbuilt->forceFill(['client_notified_at' => now()])->saveQuietly();
        }
    }

    /**
     * The project leaders and the employee who made the project hear about what the client sent.
     */
    protected function notifyEmployeesOfClientUpload(Asbuilt $asbuilt, ClientUser $client, int $count): void
    {
        $project = $asbuilt->project;
        $recipients = $project->leaders()->where('is_active', true)->get();

        if ($project->creator && $project->creator->is_active) {
            $recipients->push($project->creator);
        }

        foreach ($recipients->unique('id') as $user) {
            $locale = $user->preferredLocale();

            Notification::make()
                ->title(__('erp.asbuilt.notifications.client_upload', ['name' => $client->name, 'project' => $project->reference], $locale))
                ->body(trans_choice('erp.asbuilt.notifications.files', $count, ['count' => $count], $locale))
                ->icon('heroicon-o-archive-box-arrow-down')
                ->info()
                ->actions([Action::make('open')->label(__('erp.asbuilt.open', [], $locale))->url(AsbuiltResource::getUrl('view', ['record' => $asbuilt], panel: 'app'))])
                ->sendToDatabase($user);
        }
    }

    protected function safeName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '-', $name));

        return $name === '' ? 'file' : $name;
    }

    /**
     * @param  list<string>  $used
     */
    protected function unique(string $name, array $used): string
    {
        if (! in_array($name, $used, true)) {
            return $name;
        }

        $info = pathinfo($name);
        $base = ($info['dirname'] !== '.' ? $info['dirname'].'/' : '').$info['filename'];
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';

        for ($i = 2; in_array($candidate = "{$base} ({$i}){$extension}", $used, true); $i++);

        return $candidate;
    }
}
