<?php

namespace App\Support\Backups;

use App\Models\BackupTarget;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Backups to an SMB share (a Windows server or a NAS), with the smbclient program of the server. The
 * password goes in a temporary credentials file, never on the command line.
 */
class SmbTransport implements BackupTransport
{
    public function __construct(protected BackupTarget $target) {}

    public function test(): void
    {
        $local = tempnam(sys_get_temp_dir(), 'smb');
        file_put_contents($local, 'test');
        $name = '.power-erp-test-'.bin2hex(random_bytes(4));

        try {
            $this->put($local, $name);
            $this->delete($name);
        } finally {
            @unlink($local);
        }
    }

    public function put(string $localPath, string $name): void
    {
        $this->ensureFolder();
        $this->run('put "'.$localPath.'" "'.$name.'"');
    }

    public function list(): array
    {
        $output = $this->run('ls');
        $files = [];

        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^\s+(.+?)\s+([ADHNRS]*)\s+(\d+)\s+(\w{3}\s+\w{3}\s+\d+\s+[\d:]+\s+\d{4})$/', $line, $match) && ! str_contains($match[2], 'D')) {
                $files[] = ['name' => trim($match[1]), 'size' => (int) $match[3], 'modified' => strtotime($match[4]) ?: null];
            }
        }

        return collect($files)->sortByDesc('name')->values()->all();
    }

    public function get(string $name, string $localPath): void
    {
        $this->run('get "'.$this->safe($name).'" "'.$localPath.'"');
    }

    public function delete(string $name): void
    {
        $this->run('del "'.$this->safe($name).'"');
    }

    /**
     * Makes the folders of the path on the share (one by one; existing ones are fine).
     */
    protected function ensureFolder(): void
    {
        $current = '';

        foreach (array_filter(explode('/', trim(str_replace('\\', '/', (string) $this->target->path), '/'))) as $segment) {
            rescue(fn () => $this->run('mkdir "'.$this->safe($segment).'"', $current), null, false);
            $current = ltrim($current.'/'.$segment, '/');
        }
    }

    protected function run(string $command, ?string $directory = null): string
    {
        $credentials = tempnam(sys_get_temp_dir(), 'smbauth');
        chmod($credentials, 0600);
        [$domain, $user] = str_contains((string) $this->target->username, '\\') ? explode('\\', (string) $this->target->username, 2) : [null, (string) $this->target->username];
        file_put_contents($credentials, "username = {$user}\npassword = {$this->target->password}\n".($domain ? "domain = {$domain}\n" : ''));

        try {
            $result = Process::timeout(3600)->run([
                'smbclient', '//'.$this->target->host.'/'.trim((string) $this->target->share, '/'),
                '-A', $credentials,
                '-p', (string) ($this->target->port ?: 445),
                '-D', $directory ?? trim(str_replace('\\', '/', (string) $this->target->path), '/'),
                '-c', $command,
            ]);
        } finally {
            @unlink($credentials);
        }

        $output = $result->output().$result->errorOutput();

        if (! $result->successful() || preg_match('/NT_STATUS_[A-Z_]+/', $output, $error)) {
            throw new RuntimeException(trim($error[0] ?? $output) ?: 'smbclient failed');
        }

        return $output;
    }

    protected function safe(string $name): string
    {
        return str_replace(['"', '\\', "\n", "\r"], '', $name);
    }
}
