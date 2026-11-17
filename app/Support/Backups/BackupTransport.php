<?php

namespace App\Support\Backups;

/**
 * How backup files get to a target and back.
 */
interface BackupTransport
{
    /**
     * Throws when the target cannot be reached or written.
     */
    public function test(): void;

    public function put(string $localPath, string $name): void;

    /**
     * The files in the target folder, newest first.
     *
     * @return list<array{name: string, size: ?int, modified: ?int}>
     */
    public function list(): array;

    public function get(string $name, string $localPath): void;

    public function delete(string $name): void;
}
