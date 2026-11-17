<?php

namespace App\Support\Backups;

use App\Models\Backup;
use App\Models\BackupTarget;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Backups of the platform to the backup targets (separate from the NAS systems that keep a copy of the
 * documents, see NasSync), and restoring from any backup still on a target. A backup is one zip:
 * - code/…: the whole application (without node_modules, logs, caches and the uploaded files);
 * - env/.env: the configuration (only put back on request);
 * - database.sql: the database;
 * - files/private/…, files/public/…: all uploaded files and documents kept on the server;
 * - backup.json: what is in it.
 */
class BackupManager
{
    public const PREFIX = 'power-erp-backup-';

    /** Never in the code part. */
    public const EXCLUDE = ['node_modules', '.git', 'storage/app', 'storage/backups', 'storage/logs', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/testing', '.env'];

    public function __construct(protected DatabaseDumper $dumper) {}

    public static function folder(): string
    {
        $path = storage_path('backups');

        if (! is_dir($path)) {
            mkdir($path, 0750, true);
        }

        return $path;
    }

    /**
     * Makes a backup and sends it to the target (old ones beyond "keep" are removed there).
     */
    public function run(BackupTarget $target, ?User $user = null): Backup
    {
        @set_time_limit(0);
        $parts = array_values(array_intersect(BackupTarget::PARTS, (array) $target->parts)) ?: BackupTarget::PARTS;
        $backup = Backup::create([
            'backup_target_id' => $target->id, 'target_name' => $target->name, 'kind' => 'backup', 'parts' => $parts,
            'status' => 'running', 'user_id' => $user?->id, 'started_at' => now(),
        ]);
        $zip = null;

        try {
            $zip = $this->build($parts);
            $name = basename($zip);
            $target->transport()->put($zip, $name);
            $this->rotate($target);

            $backup->update(['status' => 'done', 'file_name' => $name, 'size' => filesize($zip), 'finished_at' => now()]);
            $target->update(['last_run_at' => now(), 'last_status' => 'done', 'last_message' => $name]);
        } catch (Throwable $exception) {
            report($exception);
            $backup->update(['status' => 'failed', 'message' => Str::limit($exception->getMessage(), 1000), 'finished_at' => now()]);
            $target->update(['last_run_at' => now(), 'last_status' => 'failed', 'last_message' => Str::limit($exception->getMessage(), 1000)]);
        } finally {
            $zip !== null && @unlink($zip);
        }

        return $backup;
    }

    /**
     * Builds the zip in storage/backups and returns its path.
     *
     * @param  list<string>  $parts
     */
    public function build(array $parts): string
    {
        $path = self::folder().'/'.self::PREFIX.now()->format('Y-m-d-His').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The backup file could not be made.');
        }

        $sql = null;

        if (in_array('code', $parts, true)) {
            $this->addFolder($zip, base_path(), 'code', self::EXCLUDE);

            if (is_file(base_path('.env'))) {
                $zip->addFile(base_path('.env'), 'env/.env');
            }
        }

        if (in_array('database', $parts, true)) {
            $sql = self::folder().'/database-'.Str::random(8).'.sql';
            $this->dumper->dump($sql);
            $zip->addFile($sql, 'database.sql');
        }

        if (in_array('files', $parts, true)) {
            $this->addFolder($zip, storage_path('app/private'), 'files/private');
            $this->addFolder($zip, storage_path('app/public'), 'files/public');
        }

        $zip->addFromString('backup.json', json_encode([
            'type' => 'power-erp-backup',
            'created_at' => now()->toIso8601String(),
            'parts' => $parts,
            'app_url' => config('app.url'),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();
        $sql !== null && @unlink($sql);

        return $path;
    }

    /**
     * Puts back the chosen parts of a backup on the target: "database" (tables replaced; a safety dump of
     * the current database is kept in storage/backups first), "files" (server files) and "code" (the
     * application; the .env only with $withEnv).
     *
     * @param  list<string>  $parts
     */
    public function restore(BackupTarget $target, string $name, array $parts, ?User $user = null, bool $withEnv = false): Backup
    {
        @set_time_limit(0);
        $backup = Backup::create([
            'backup_target_id' => $target->id, 'target_name' => $target->name, 'kind' => 'restore', 'file_name' => $name, 'parts' => $parts,
            'status' => 'running', 'user_id' => $user?->id, 'started_at' => now(),
        ]);
        $local = self::folder().'/restore-'.Str::random(8).'.zip';

        try {
            if (! str_starts_with($name, self::PREFIX) || ! str_ends_with($name, '.zip') || str_contains($name, '/')) {
                throw new RuntimeException('This is not a backup of the platform.');
            }

            $target->transport()->get($name, $local);
            $this->restoreFile($local, $parts, $withEnv);

            // The restore row may itself have been replaced by the database restore: write it again.
            Backup::query()->updateOrCreate(['id' => $backup->id], [...$backup->only(['backup_target_id', 'target_name', 'kind', 'file_name', 'parts', 'user_id', 'started_at']), 'status' => 'done', 'finished_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            Backup::query()->updateOrCreate(['id' => $backup->id], [...$backup->only(['backup_target_id', 'target_name', 'kind', 'file_name', 'parts', 'user_id', 'started_at']), 'status' => 'failed', 'message' => Str::limit($exception->getMessage(), 1000), 'finished_at' => now()]);
        } finally {
            @unlink($local);
        }

        return Backup::query()->find($backup->id) ?? $backup;
    }

    /**
     * Restores from a local backup zip (also used by the command when the platform itself is broken).
     *
     * @param  list<string>  $parts
     */
    public function restoreFile(string $zipPath, array $parts, bool $withEnv = false): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true || $zip->locateName('backup.json') === false) {
            throw new RuntimeException('This is not a backup of the platform.');
        }

        if (in_array('database', $parts, true) && $zip->locateName('database.sql') !== false) {
            $this->dumper->dump(self::folder().'/before-restore-'.now()->format('Y-m-d-His').'.sql');
            $sql = self::folder().'/restore-'.Str::random(8).'.sql';
            copy('zip://'.$zipPath.'#database.sql', $sql);

            try {
                $this->dumper->import($sql);
            } finally {
                @unlink($sql);
            }
        }

        $prefixes = array_filter([
            in_array('files', $parts, true) ? ['files/private/', storage_path('app/private')] : null,
            in_array('files', $parts, true) ? ['files/public/', storage_path('app/public')] : null,
            in_array('code', $parts, true) ? ['code/', base_path()] : null,
        ]);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = (string) $zip->getNameIndex($index);

            if (str_ends_with($entry, '/') || str_contains($entry, '..')) {
                continue;
            }

            foreach ($prefixes as [$prefix, $destination]) {
                if (str_starts_with($entry, $prefix)) {
                    $this->extract($zip, $entry, $destination.'/'.substr($entry, strlen($prefix)));
                }
            }

            if ($withEnv && $entry === 'env/.env') {
                $this->extract($zip, $entry, base_path('.env'));
            }
        }

        $zip->close();

        if (in_array('code', $parts, true) || $withEnv) {
            rescue(fn () => Artisan::call('optimize:clear'), null, false);
        }
    }

    /**
     * Removes the oldest backups on the target beyond the number to keep.
     */
    public function rotate(BackupTarget $target): void
    {
        $backups = collect($target->transport()->list())
            ->filter(fn (array $file): bool => str_starts_with($file['name'], self::PREFIX) && str_ends_with($file['name'], '.zip'))
            ->sortByDesc('name')->values();

        $backups->slice(max(1, (int) $target->keep))->each(fn (array $file) => $target->transport()->delete($file['name']));
    }

    /**
     * @param  list<string>  $exclude  paths relative to $folder
     */
    protected function addFolder(ZipArchive $zip, string $folder, string $prefix, array $exclude = []): void
    {
        if (! is_dir($folder)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));

            foreach ($exclude as $skip) {
                if ($relative === $skip || str_starts_with($relative, $skip.'/')) {
                    continue 2;
                }
            }

            $zip->addFile($file->getPathname(), $prefix.'/'.$relative);
        }
    }

    protected function extract(ZipArchive $zip, string $entry, string $destination): void
    {
        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $source = $zip->getStream($entry);
        $target = fopen($destination, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($target);
        is_resource($source) && fclose($source);
    }
}
