<?php

namespace App\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Downloads a product image (e.g. copied from a web shop) onto the public disk.
 * Only public http(s) addresses and PNG/JPEG/WebP images up to 5 MB are accepted.
 */
class ImportImageFromUrl
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return string the stored path on the public disk
     */
    public function handle(string $url, string $directory): string
    {
        $this->guardUrl($url);

        try {
            $response = Http::timeout(15)->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]])->get($url);
        } catch (ConnectionException) {
            throw $this->invalid(__('erp.stock.image.unreachable'));
        }

        $extension = match (strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]))) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => null,
        };

        if (! $response->successful() || $extension === null) {
            throw $this->invalid(__('erp.stock.image.not_an_image'));
        }

        if (strlen($response->body()) > self::MAX_BYTES) {
            throw $this->invalid(__('erp.stock.image.too_large'));
        }

        $path = $directory.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * Blocks addresses on the internal network, so the server cannot be used to probe it.
     */
    protected function guardUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || blank($parts['host'] ?? null)) {
            throw $this->invalid(__('erp.stock.image.invalid_url'));
        }

        $ips = gethostbynamel($parts['host']) ?: [];

        foreach ($ips ?: [$parts['host']] as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw $this->invalid(__('erp.stock.image.invalid_url'));
            }
        }
    }

    protected function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['image_url' => $message]);
    }
}
