<?php

namespace App\Support;

use App\Models\User;

/**
 * PDFs need an explicit right per module: "{area}.generate_pdf" to make a PDF from the data in the
 * application (documents, reports, overviews, exports) and "{area}.download_pdf" to download PDF files
 * stored in the application (uploaded documents and attachments). Administrators have both.
 */
class PdfAccess
{
    public const GENERATE = 'generate_pdf';

    public const DOWNLOAD = 'download_pdf';

    public static function canGenerate(?User $user, string $area): bool
    {
        return $user !== null && $user->hasPermission($area.'.'.self::GENERATE);
    }

    public static function canDownload(?User $user, string $area): bool
    {
        return $user !== null && $user->hasPermission($area.'.'.self::DOWNLOAD);
    }

    /**
     * Whether a stored file is a PDF (by its name or path).
     */
    public static function isPdf(?string $name): bool
    {
        return strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)) === 'pdf';
    }
}
