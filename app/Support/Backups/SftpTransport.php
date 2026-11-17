<?php

namespace App\Support\Backups;

use App\Models\BackupTarget;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\StorageAttributes;
use RuntimeException;

/**
 * Backups over SFTP (also a Synology or other NAS with SFTP switched on).
 */
class SftpTransport implements BackupTransport
{
    protected ?Filesystem $filesystem = null;

    public function __construct(protected BackupTarget $target) {}

    protected function filesystem(): Filesystem
    {
        return $this->filesystem ??= new Filesystem(new SftpAdapter(
            new SftpConnectionProvider($this->target->host, (string) $this->target->username, $this->target->password, null, null, $this->target->port ?: 22, false, 30),
            '/'.trim((string) $this->target->path, '/'),
        ));
    }

    public function test(): void
    {
        $name = '.power-erp-test-'.bin2hex(random_bytes(4));
        $this->filesystem()->write($name, 'test');

        if ($this->filesystem()->read($name) !== 'test') {
            throw new RuntimeException('The test file could not be read back.');
        }

        $this->filesystem()->delete($name);
    }

    public function put(string $localPath, string $name): void
    {
        $stream = fopen($localPath, 'rb');
        $this->filesystem()->writeStream($name, $stream);
        is_resource($stream) && fclose($stream);
    }

    public function list(): array
    {
        return collect($this->filesystem()->listContents('', false))
            ->filter(fn (StorageAttributes $item): bool => $item->isFile())
            ->map(fn ($item): array => ['name' => basename($item->path()), 'size' => $item->fileSize(), 'modified' => $item->lastModified()])
            ->sortByDesc('name')->values()->all();
    }

    public function get(string $name, string $localPath): void
    {
        $source = $this->filesystem()->readStream($name);
        $target = fopen($localPath, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($target);
        is_resource($source) && fclose($source);
    }

    public function delete(string $name): void
    {
        $this->filesystem()->delete($name);
    }
}
