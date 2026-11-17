<?php

namespace App\Support\Backups;

use App\Models\BackupTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Backups to a Synology NAS through its File Station web API (HTTPS, port 5001 by default). The folder
 * starts with the shared folder, e.g. "/backup/power-erp". Accounts with two-step verification cannot sign in.
 */
class SynologyTransport implements BackupTransport
{
    protected ?string $sid = null;

    public function __construct(protected BackupTarget $target) {}

    public function __destruct()
    {
        if ($this->sid !== null) {
            rescue(fn () => $this->http()->get('/webapi/entry.cgi', ['api' => 'SYNO.API.Auth', 'version' => 6, 'method' => 'logout', 'session' => 'FileStation', '_sid' => $this->sid]), null, false);
        }
    }

    public function test(): void
    {
        $local = tempnam(sys_get_temp_dir(), 'syno');
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
        $stream = fopen($localPath, 'rb');

        $response = $this->http()->timeout(3600)
            ->attach('file', $stream, $name)
            ->post('/webapi/entry.cgi?'.http_build_query(['api' => 'SYNO.FileStation.Upload', 'version' => 2, 'method' => 'upload', '_sid' => $this->sid()]), [
                'path' => $this->folder(),
                'create_parents' => 'true',
                'overwrite' => 'true',
            ]);

        is_resource($stream) && fclose($stream);
        $this->ensure($response->json(), 'upload');
    }

    public function list(): array
    {
        $data = $this->ensure($this->http()->get('/webapi/entry.cgi', [
            'api' => 'SYNO.FileStation.List', 'version' => 2, 'method' => 'list', 'folder_path' => $this->folder(),
            'additional' => '["size","time"]', '_sid' => $this->sid(),
        ])->json(), 'list');

        return collect($data['files'] ?? [])->reject(fn (array $file): bool => (bool) ($file['isdir'] ?? false))
            ->map(fn (array $file): array => ['name' => $file['name'], 'size' => $file['additional']['size'] ?? null, 'modified' => $file['additional']['time']['mtime'] ?? null])
            ->sortByDesc('name')->values()->all();
    }

    public function get(string $name, string $localPath): void
    {
        $response = $this->http()->timeout(3600)->withOptions(['sink' => $localPath])->get('/webapi/entry.cgi', [
            'api' => 'SYNO.FileStation.Download', 'version' => 2, 'method' => 'download', 'mode' => 'download',
            'path' => json_encode([$this->folder().'/'.$name]), '_sid' => $this->sid(),
        ]);

        if (! $response->successful() || str_starts_with((string) $response->header('Content-Type'), 'application/json')) {
            throw new RuntimeException('Download from the Synology failed.');
        }
    }

    public function delete(string $name): void
    {
        $this->ensure($this->http()->get('/webapi/entry.cgi', [
            'api' => 'SYNO.FileStation.Delete', 'version' => 2, 'method' => 'delete',
            'path' => json_encode([$this->folder().'/'.$name]), '_sid' => $this->sid(),
        ])->json(), 'delete');
    }

    protected function sid(): string
    {
        if ($this->sid !== null) {
            return $this->sid;
        }

        $data = $this->ensure($this->http()->get('/webapi/entry.cgi', [
            'api' => 'SYNO.API.Auth', 'version' => 6, 'method' => 'login', 'account' => $this->target->username,
            'passwd' => $this->target->password, 'session' => 'FileStation', 'format' => 'sid',
        ])->json(), 'login');

        return $this->sid = (string) ($data['sid'] ?? throw new RuntimeException('The Synology gave no session.'));
    }

    protected function http(): PendingRequest
    {
        $host = preg_replace('#^https?://#', '', rtrim($this->target->host, '/'));

        return Http::baseUrl('https://'.$host.':'.($this->target->port ?: 5001))->timeout(60)->withOptions(['verify' => $this->target->verify_tls]);
    }

    protected function folder(): string
    {
        return '/'.trim(str_replace('\\', '/', (string) $this->target->path), '/');
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>
     */
    protected function ensure(?array $response, string $action): array
    {
        if (! ($response['success'] ?? false)) {
            $code = $response['error']['code'] ?? '?';

            throw new RuntimeException("Synology {$action} failed (error {$code}).");
        }

        return (array) ($response['data'] ?? []);
    }
}
