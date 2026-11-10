<?php

namespace App\Support\Nas;

use App\Models\NasTarget;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * SMB (Windows / Samba share) through the "smbclient" program of Samba: the account goes in a
 * temporary credentials file (never on the command line); folders are made one by one, an existing file
 * is replaced. Paths are below the share (the NAS record's share and main folder).
 */
class SmbTransport implements NasTransport
{
    public function __construct(
        protected string $host,
        protected string $share,
        protected string $username,
        protected string $password,
        protected ?string $domain = null,
        protected ?int $port = null,
    ) {}

    public static function fromTarget(NasTarget $target): self
    {
        if (blank($target->host) || blank($target->share)) {
            throw new RuntimeException(__('erp.nas.errors.not_configured'));
        }

        return new self(
            (string) $target->host,
            trim((string) $target->share, '/\\'),
            (string) $target->username,
            (string) $target->password,
            filled($target->domain) ? (string) $target->domain : null,
            $target->port ?: null,
        );
    }

    public function upload(string $folder, string $name, string $contents): void
    {
        $segments = array_values(array_filter(explode('/', self::safe($folder)), fn (string $segment): bool => $segment !== ''));
        $commands = [];

        // Every folder on the way ("already exists" is fine), then into it and the file.
        foreach (array_keys($segments) as $index) {
            $commands[] = 'mkdir "'.implode('\\', array_slice($segments, 0, $index + 1)).'"';
        }

        $local = tempnam(sys_get_temp_dir(), 'nas');
        file_put_contents($local, $contents);

        try {
            $commands[] = 'cd "\\'.implode('\\', $segments).'"';
            $commands[] = 'put "'.$local.'" "'.self::safe($name).'"';
            $this->run(implode('; ', $commands));
        } finally {
            @unlink($local);
        }
    }

    public function test(): string
    {
        $this->run('ls');

        return __('erp.nas.test_reachable', ['server' => '//'.$this->host.'/'.$this->share]);
    }

    public function close(): void {}

    /**
     * Runs smbclient commands; an error other than "already exists" fails.
     */
    protected function run(string $commands): string
    {
        $credentials = tempnam(sys_get_temp_dir(), 'smb');
        file_put_contents($credentials, "username = {$this->username}\npassword = {$this->password}\n".($this->domain ? "domain = {$this->domain}\n" : ''));
        chmod($credentials, 0600);

        try {
            $result = Process::timeout(120)->run(array_values(array_filter([
                'smbclient', '//'.$this->host.'/'.$this->share,
                '--authentication-file='.$credentials,
                $this->port ? '--port='.$this->port : null,
                '--max-protocol=SMB3',
                '-c', $commands,
            ])));
        } finally {
            @unlink($credentials);
        }

        $output = $result->output().$result->errorOutput();
        $errors = collect(preg_split('/\R/', $output))
            ->filter(fn (string $line): bool => str_contains($line, 'NT_STATUS_') && ! str_contains($line, 'NT_STATUS_OBJECT_NAME_COLLISION'));

        if ($errors->isNotEmpty() || ($result->failed() && ! str_contains($output, 'NT_STATUS_OBJECT_NAME_COLLISION'))) {
            throw new RuntimeException(__('erp.nas.errors.smb', ['error' => trim($errors->first() ?? $output) ?: $result->exitCode()]));
        }

        return $output;
    }

    /**
     * smbclient separates commands with ";" and quotes with '"': neither may be in a name.
     */
    protected static function safe(string $path): string
    {
        return str_replace([';', '"'], '-', $path);
    }
}
