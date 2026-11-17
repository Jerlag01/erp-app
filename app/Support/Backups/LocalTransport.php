<?php

namespace App\Support\Backups;

use App\Models\BackupTarget;
use RuntimeException;

/**
 * Backups to a folder on this server, e.g. a NAS share mounted over NFS or SMB, or a USB disk.
 */
class LocalTransport implements BackupTransport
{
    public function __construct(protected BackupTarget $target) {}

    public function test(): void
    {
        $file = $this->folder().'/.power-erp-test-'.bin2hex(random_bytes(4));

        if (@file_put_contents($file, 'test') === false) {
            throw new RuntimeException('The folder cannot be written: '.$this->folder());
        }

        unlink($file);
    }

    public function put(string $localPath, string $name): void
    {
        if (! copy($localPath, $this->folder().'/'.basename($name))) {
            throw new RuntimeException('The backup could not be copied to '.$this->folder());
        }
    }

    public function list(): array
    {
        return collect(glob($this->folder().'/*') ?: [])->filter(fn (string $path): bool => is_file($path))
            ->map(fn (string $path): array => ['name' => basename($path), 'size' => filesize($path) ?: null, 'modified' => filemtime($path) ?: null])
            ->sortByDesc('name')->values()->all();
    }

    public function get(string $name, string $localPath): void
    {
        if (! copy($this->folder().'/'.basename($name), $localPath)) {
            throw new RuntimeException('The backup could not be read.');
        }
    }

    public function delete(string $name): void
    {
        @unlink($this->folder().'/'.basename($name));
    }

    protected function folder(): string
    {
        $folder = '/'.trim((string) $this->target->path, '/');

        if (! is_dir($folder) && ! @mkdir($folder, 0750, true)) {
            throw new RuntimeException('The folder does not exist: '.$folder);
        }

        return $folder;
    }
}
