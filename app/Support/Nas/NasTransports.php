<?php

namespace App\Support\Nas;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * The transport chosen in Settings > NAS.
 */
class NasTransports
{
    public const SYNOLOGY = 'synology';

    public const SMB = 'smb';

    public const FTP = 'ftp';

    public const SFTP = 'sftp';

    public const ALL = [self::SYNOLOGY, self::SMB, self::FTP, self::SFTP];

    public static function fromSettings(): NasTransport
    {
        return match ((string) settings()->get('nas.driver', self::SYNOLOGY)) {
            self::SMB => SmbTransport::fromSettings(),
            self::FTP => FlysystemTransport::ftpFromSettings(),
            self::SFTP => FlysystemTransport::sftpFromSettings(),
            default => SynologyClient::fromSettings(),
        };
    }

    /**
     * Whether the settings name a server to write to.
     */
    public static function configured(): bool
    {
        return (string) settings()->get('nas.driver', self::SYNOLOGY) === self::SYNOLOGY
            ? filled(settings()->get('nas.url'))
            : filled(settings()->get('nas.host'));
    }

    /**
     * A stored secret (password, private key), decrypted; empty when there is none.
     */
    public static function secret(string $key): string
    {
        $value = (string) settings()->get($key);

        try {
            return $value !== '' ? Crypt::decryptString($value) : '';
        } catch (Throwable) {
            return '';
        }
    }
}
