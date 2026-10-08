<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The upload zones of a project. Offers and invoices are financial: the teams on a project
 * do not see them, only administrators, project leaders and roles with "project_finance".
 */
enum ProjectFileSection: string implements HasIcon, HasLabel
{
    case Documents = 'documents';

    case Images = 'images';

    case Offers = 'offers';

    case Invoices = 'invoices';

    case Plans = 'plans';

    case Schematics = 'schematics';

    case Extra = 'extra';

    /**
     * Office documents, PDF and text.
     */
    public const DOCUMENT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'docm', 'dot', 'dotx', 'odt', 'rtf', 'txt', 'md', 'csv',
        'xls', 'xlsx', 'xlsm', 'xlsb', 'xlt', 'xltx', 'ods',
        'ppt', 'pptx', 'pptm', 'pps', 'ppsx', 'pot', 'potx', 'odp',
        'xml', 'json', 'msg', 'eml',
    ];

    /**
     * Every common picture format, including iPhone (HEIC) and camera RAW files.
     */
    public const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'jpe', 'jfif', 'png', 'gif', 'webp', 'heic', 'heif', 'avif', 'bmp', 'tif', 'tiff', 'svg', 'ico',
        'jxl', 'raw', 'dng', 'cr2', 'cr3', 'nef', 'arw', 'orf', 'rw2', 'raf', 'psd',
    ];

    public const PLAN_EXTENSIONS = ['dwg', 'dxf'];

    /**
     * Installers, executables and scripts are never accepted, not even in the "extra" zone.
     */
    public const BLOCKED_EXTENSIONS = [
        'exe', 'msi', 'msix', 'msixbundle', 'msp', 'mst', 'appx', 'appxbundle', 'appinstaller', 'application',
        'apk', 'aab', 'xapk', 'ipa', 'dmg', 'pkg', 'mpkg', 'deb', 'rpm', 'snap', 'flatpak', 'appimage', 'run', 'iso', 'img',
        'bat', 'cmd', 'com', 'scr', 'pif', 'cpl', 'msc', 'gadget', 'inf', 'reg', 'lnk', 'sys', 'dll', 'drv', 'ocx',
        'ps1', 'psm1', 'psd1', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'hta', 'jar', 'sh', 'bash', 'csh', 'ksh',
        'php', 'phtml', 'phar', 'pl', 'py', 'rb', 'cgi',
    ];

    public function getLabel(): string
    {
        return __('erp.enums.project_file_section.'.$this->value);
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Documents => Heroicon::OutlinedDocumentText,
            self::Images => Heroicon::OutlinedPhoto,
            self::Offers => Heroicon::OutlinedDocumentCurrencyEuro,
            self::Invoices => Heroicon::OutlinedReceiptPercent,
            self::Plans => Heroicon::OutlinedMap,
            self::Schematics => Heroicon::OutlinedBolt,
            self::Extra => Heroicon::OutlinedFolderPlus,
        };
    }

    public function isFinancial(): bool
    {
        return in_array($this, [self::Offers, self::Invoices], true);
    }

    /**
     * The extensions this zone accepts, or null when anything but BLOCKED_EXTENSIONS is fine.
     *
     * @return list<string>|null
     */
    public function allowedExtensions(): ?array
    {
        return match ($this) {
            self::Documents, self::Offers, self::Invoices => self::DOCUMENT_EXTENSIONS,
            self::Images => self::IMAGE_EXTENSIONS,
            self::Plans => self::PLAN_EXTENSIONS,
            self::Schematics, self::Extra => null,
        };
    }

    public function accepts(string $fileName): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return false;
        }

        return $this->allowedExtensions() === null || in_array($extension, $this->allowedExtensions(), true);
    }

    /**
     * Helper text listing what may be uploaded.
     */
    public function acceptedHint(): string
    {
        $allowed = $this->allowedExtensions();

        return $allowed === null
            ? __('erp.projects.files.any_type')
            : __('erp.projects.files.allowed_types', ['types' => strtoupper(implode(', ', $allowed))]);
    }

    /**
     * Pictures a browser can show inline; everything else is downloaded.
     */
    public static function isPreviewableImage(?string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'], true);
    }
}
