<?php

namespace App\Support\Nas;

use App\Models\NasTarget;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use RuntimeException;
use Throwable;

/**
 * FTP(S) and SFTP through Flysystem: folders are made on the way, an existing file is replaced. The
 * paths are absolute on the server (the NAS record's main folder, e.g. "/ERP" or "/volume1/ERP").
 */
class FlysystemTransport implements NasTransport
{
    public function __construct(protected FilesystemOperator $filesystem, protected string $label, protected string $root = '/ERP') {}

    public static function ftp(NasTarget $target): self
    {
        self::requireHost((string) $target->host);

        return new self(new Filesystem(new FtpAdapter(FtpConnectionOptions::fromArray([
            'host' => (string) $target->host,
            'root' => '/',
            'username' => (string) $target->username,
            'password' => (string) $target->password,
            'port' => $target->port ?: 21,
            'ssl' => (bool) $target->ftps,
            'passive' => (bool) $target->passive,
            'timeout' => 60,
            'utf8' => true,
        ]))), 'ftp://'.$target->host, $target->rootFolder());
    }

    public static function sftp(NasTarget $target): self
    {
        self::requireHost((string) $target->host);
        $key = trim((string) $target->private_key);

        return new self(new Filesystem(new SftpAdapter(new SftpConnectionProvider(
            (string) $target->host,
            (string) $target->username,
            filled($target->password) ? (string) $target->password : null,
            $key !== '' ? $key."\n" : null,
            null,
            $target->port ?: 22,
            false,
            60,
        ), '/')), 'sftp://'.$target->host, $target->rootFolder());
    }

    public function upload(string $folder, string $name, string $contents): void
    {
        $this->filesystem->write(trim($folder, '/').'/'.$name, $contents);
    }

    public function test(): string
    {
        try {
            $root = '/'.trim($this->root, '/');
            $items = collect($this->filesystem->listContents($root === '/' ? '' : ltrim($root, '/'))->toArray())->take(20)->map(fn ($item): string => basename($item->path()))->implode(', ');
        } catch (Throwable $exception) {
            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        return __('erp.nas.test_folder', ['server' => $this->label, 'items' => $items ?: '—']);
    }

    public function close(): void
    {
        // Flysystem closes the connection when it is no longer used.
    }

    protected static function requireHost(string $host): void
    {
        if ($host === '') {
            throw new RuntimeException(__('erp.nas.errors.not_configured'));
        }
    }
}
