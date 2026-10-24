<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Puts PDF files one after the other with the qpdf program (any PDF version, also scans), each page
 * keeping its own size and orientation. qpdf is a server package (apt install qpdf); when it is not
 * there, available() is false and the callers offer the files separately.
 */
class PdfMerger
{
    public function binary(): string
    {
        return (string) config('services.qpdf.binary', '/usr/bin/qpdf');
    }

    public function available(): bool
    {
        return is_file($this->binary()) && is_executable($this->binary());
    }

    /**
     * @param  list<string>  $files  absolute paths, in order
     */
    public function merge(array $files, string $target): void
    {
        $result = Process::timeout(120)->run([$this->binary(), '--warning-exit-0', '--empty', '--pages', ...$files, '--', $target]);

        if (! $result->successful() || ! is_file($target)) {
            throw new RuntimeException('qpdf could not merge the PDF files: '.trim($result->errorOutput()));
        }
    }
}
