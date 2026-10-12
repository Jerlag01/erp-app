<?php

namespace App\Models;

use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A 4G router at a COW site. The password (encrypted) and the VPN file (private disk) are only
 * shown to users with "charging.secrets".
 */
#[Fillable(['work_site_id', 'type', 'serial_number', 'sim_ean', 'sim_ssn', 'username', 'password', 'vpn_file', 'vpn_file_name', 'notes'])]
#[Hidden(['password'])]
class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use HasFactory;

    public const DISK = 'local';

    /**
     * @return BelongsTo<WorkSite, $this>
     */
    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    public static function vpnDirectory(): string
    {
        return 'routers/vpn';
    }

    protected static function booted(): void
    {
        static::updated(function (self $router): void {
            if ($router->wasChanged('vpn_file') && filled($old = $router->getOriginal('vpn_file'))) {
                Storage::disk(self::DISK)->delete($old);
            }
        });

        static::deleted(function (self $router): void {
            if (filled($router->vpn_file)) {
                Storage::disk(self::DISK)->delete($router->vpn_file);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }
}
