<?php

namespace App\Models;

use App\Support\Backups\BackupTransport;
use App\Support\Backups\LocalTransport;
use App\Support\Backups\SftpTransport;
use App\Support\Backups\SmbTransport;
use App\Support\Backups\SynologyTransport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place backups are sent to: an SFTP server, an SMB share (Windows / NAS) or a Synology NAS (File Station
 * API). Each has its own schedule, parts (code, database, server files) and number of backups to keep.
 */
#[Fillable(['name', 'type', 'host', 'port', 'username', 'password', 'share', 'path', 'verify_tls', 'parts', 'frequency', 'time', 'keep', 'is_active', 'last_run_at', 'last_status', 'last_message'])]
#[Hidden(['password'])]
class BackupTarget extends Model
{
    public const TYPES = ['sftp' => 'SFTP', 'smb' => 'SMB (Windows / NAS share)', 'synology' => 'Synology NAS (File Station)', 'local' => 'Folder on this server (mounted NAS / disk)'];

    public const PARTS = ['code', 'database', 'files'];

    public const FREQUENCIES = ['daily', 'weekly', 'manual'];

    /**
     * @return HasMany<Backup, $this>
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class)->latest('id');
    }

    public function transport(): BackupTransport
    {
        return match ($this->type) {
            'sftp' => new SftpTransport($this),
            'smb' => new SmbTransport($this),
            'synology' => new SynologyTransport($this),
            'local' => new LocalTransport($this),
        };
    }

    /**
     * Whether a scheduled backup is due now (daily or weekly at the set time).
     */
    public function isDue(): bool
    {
        if (! $this->is_active || $this->frequency === 'manual') {
            return false;
        }

        $at = today()->setTimeFromTimeString($this->time ?: '02:00');

        if (now()->lt($at)) {
            return false;
        }

        $since = $this->frequency === 'weekly' ? $at->copy()->subDays(6) : $at;

        return $this->last_run_at === null || $this->last_run_at->lt($since);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'parts' => 'array',
            'verify_tls' => 'boolean',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
