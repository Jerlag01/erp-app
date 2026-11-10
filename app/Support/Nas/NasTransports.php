<?php

namespace App\Support\Nas;

use App\Models\NasTarget;

/**
 * The ways to reach a NAS (see NasTarget::driver).
 */
class NasTransports
{
    public const SYNOLOGY = 'synology';

    public const SMB = 'smb';

    public const FTP = 'ftp';

    public const SFTP = 'sftp';

    public const ALL = [self::SYNOLOGY, self::SMB, self::FTP, self::SFTP];

    public static function for(NasTarget $target): NasTransport
    {
        return match ($target->driver) {
            self::SMB => SmbTransport::fromTarget($target),
            self::FTP => FlysystemTransport::ftp($target),
            self::SFTP => FlysystemTransport::sftp($target),
            default => SynologyClient::fromTarget($target),
        };
    }
}
