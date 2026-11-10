<?php

namespace App\Support\Nas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * A Synology NAS through its File Station web API (DSM 7, over HTTPS): logs in with a NAS account,
 * uploads files (creating the folders on the way) and lists the shared folders to test the connection.
 * Settings > NAS holds the address, the account and the root folder.
 */
class SynologyClient
{
    protected ?string $sid = null;

    public function __construct(
        protected string $url,
        protected string $username,
        protected string $password,
        protected bool $verifySsl = true,
    ) {}

    public static function fromSettings(): self
    {
        $password = (string) settings()->get('nas.password');

        try {
            $password = $password !== '' ? Crypt::decryptString($password) : '';
        } catch (Throwable) {
            $password = '';
        }

        return new self(
            rtrim((string) settings()->get('nas.url'), '/'),
            (string) settings()->get('nas.username'),
            $password,
            (bool) settings()->get('nas.verify_ssl', true),
        );
    }

    /**
     * Writes a file at "$folder/$name" (an absolute path on the NAS, starting with the shared folder),
     * creating missing folders and replacing a file with that name.
     */
    public function upload(string $folder, string $name, string $contents): void
    {
        $this->call(fn (string $sid): Response => $this->http()
            ->attach('file', $contents, $name)
            ->post($this->url.'/webapi/entry.cgi?'.http_build_query([
                'api' => 'SYNO.FileStation.Upload', 'version' => 2, 'method' => 'upload', '_sid' => $sid,
            ]), [
                'path' => $folder,
                'create_parents' => 'true',
                'overwrite' => 'true',
            ]));
    }

    /**
     * The shared folders the account sees: proves the address and the account work.
     *
     * @return list<string>
     */
    public function shares(): array
    {
        $body = $this->call(fn (string $sid): Response => $this->http()->get($this->url.'/webapi/entry.cgi', [
            'api' => 'SYNO.FileStation.List', 'version' => 2, 'method' => 'list_share', '_sid' => $sid,
        ]));

        return array_values(array_map(fn (array $share): string => (string) ($share['path'] ?? $share['name'] ?? ''), (array) ($body['data']['shares'] ?? [])));
    }

    public function logout(): void
    {
        if ($this->sid !== null) {
            rescue(fn () => $this->http()->get($this->url.'/webapi/entry.cgi', ['api' => 'SYNO.API.Auth', 'version' => 6, 'method' => 'logout', 'session' => 'FileStation', '_sid' => $this->sid]), report: false);
            $this->sid = null;
        }
    }

    /**
     * A call with the session; an expired session (error 106/107/119) logs in again once.
     *
     * @param  callable(string): Response  $request
     * @return array<string, mixed>
     */
    protected function call(callable $request): array
    {
        foreach ([1, 2] as $try) {
            $body = $request($this->sid())->throw()->json() ?? [];

            if (($body['success'] ?? false) === true) {
                return $body;
            }

            $code = (int) ($body['error']['code'] ?? 0);

            if ($try === 1 && in_array($code, [106, 107, 119], true)) {
                $this->sid = null;

                continue;
            }

            throw new RuntimeException(__('erp.nas.errors.api', ['code' => $code ?: '?']));
        }

        throw new RuntimeException(__('erp.nas.errors.api', ['code' => '?']));
    }

    protected function sid(): string
    {
        if ($this->sid !== null) {
            return $this->sid;
        }

        if ($this->url === '' || $this->username === '') {
            throw new RuntimeException(__('erp.nas.errors.not_configured'));
        }

        $body = $this->http()->get($this->url.'/webapi/entry.cgi', [
            'api' => 'SYNO.API.Auth', 'version' => 6, 'method' => 'login',
            'account' => $this->username, 'passwd' => $this->password, 'session' => 'FileStation', 'format' => 'sid',
        ])->throw()->json() ?? [];

        if (($body['success'] ?? false) !== true || blank($body['data']['sid'] ?? null)) {
            // 400: wrong account or password, 403/406: two-step verification is on for this account.
            throw new RuntimeException(__('erp.nas.errors.login', ['code' => $body['error']['code'] ?? '?']));
        }

        return $this->sid = (string) $body['data']['sid'];
    }

    protected function http(): PendingRequest
    {
        return Http::withOptions(['verify' => $this->verifySsl])->timeout(120)->connectTimeout(15)->acceptJson();
    }
}
