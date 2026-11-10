<?php

namespace App\Models;

use App\Support\Nas\NasTransports;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One NAS that keeps a copy of every file, for redundancy (Admin > NAS systems): how to reach it
 * (Synology File Station, SMB, FTP(S) or SFTP), the account and the main folder. Every active NAS gets
 * every file; each has its own outbox, so one NAS being offline does not hold up the others.
 */
#[Fillable([
    'name', 'is_active', 'driver', 'url', 'host', 'port', 'share', 'domain', 'username', 'password', 'private_key',
    'root', 'ftps', 'passive', 'verify_ssl',
])]
#[Hidden(['password', 'private_key'])]
class NasTarget extends Model
{
    /**
     * @return HasMany<NasSyncItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(NasSyncItem::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Whether it names a server to write to.
     */
    public function isConfigured(): bool
    {
        return $this->driver === NasTransports::SYNOLOGY ? filled($this->url) : filled($this->host);
    }

    /**
     * The main folder, "/ERP" style.
     */
    public function rootFolder(): string
    {
        return '/'.trim((string) $this->root, '/');
    }

    /**
     * "https://nas.example.be:5001" or "sftp://nas.example.be", for the list.
     */
    public function address(): string
    {
        return match ($this->driver) {
            NasTransports::SYNOLOGY => (string) $this->url,
            NasTransports::SMB => '//'.$this->host.'/'.$this->share,
            default => $this->driver.'://'.$this->host.($this->port ? ':'.$this->port : ''),
        };
    }

    protected static function booted(): void
    {
        static::saving(function (self $target): void {
            $target->url = filled($target->url) ? rtrim((string) $target->url, '/') : null;
            $target->root = $target->rootFolder();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
            'private_key' => 'encrypted',
            'ftps' => 'boolean',
            'passive' => 'boolean',
            'verify_ssl' => 'boolean',
        ];
    }
}
