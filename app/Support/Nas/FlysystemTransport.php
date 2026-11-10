<?php

namespace App\Support\Nas;

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
 * paths are absolute on the server (Settings > NAS > main folder, e.g. "/ERP" or "/volume1/ERP").
 */
class FlysystemTransport implements NasTransport
{
    public function __construct(protected FilesystemOperator $filesystem, protected string $label) {}

    public static function ftpFromSettings(): self
    {
        $host = (string) settings()->get('nas.host');
        self::requireHost($host);

        return new self(new Filesystem(new FtpAdapter(FtpConnectionOptions::fromArray([
            'host' => $host,
            'root' => '/',
            'username' => (string) settings()->get('nas.username'),
            'password' => NasTransports::secret('nas.password'),
            'port' => (int) (settings()->get('nas.port') ?: 21),
            'ssl' => (bool) settings()->get('nas.ftps', true),
            'passive' => (bool) settings()->get('nas.passive', true),
            'timeout' => 60,
            'utf8' => true,
        ]))), 'ftp://'.$host);
    }

    public static function sftpFromSettings(): self
    {
        $host = (string) settings()->get('nas.host');
        self::requireHost($host);
        $key = NasTransports::secret('nas.private_key');

        return new self(new Filesystem(new SftpAdapter(new SftpConnectionProvider(
            $host,
            (string) settings()->get('nas.username'),
            NasTransports::secret('nas.password') ?: null,
            $key !== '' ? $key : null,
            null,
            (int) (settings()->get('nas.port') ?: 22),
            false,
            60,
        ), '/')), 'sftp://'.$host);
    }

    public function upload(string $folder, string $name, string $contents): void
    {
        $this->filesystem->write(trim($folder, '/').'/'.$name, $contents);
    }

    public function test(): string
    {
        try {
            $root = '/'.trim((string) settings()->get('nas.root', '/ERP'), '/');
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
