<?php

namespace App\Support\Nas;

/**
 * How files reach the NAS: the Synology File Station API, SMB, FTP(S) or SFTP (Settings > NAS).
 */
interface NasTransport
{
    /**
     * Writes a file at "$folder/$name" ($folder from the server's or share's root, e.g. "/ERP/604"),
     * creating missing folders and replacing a file with that name.
     */
    public function upload(string $folder, string $name, string $contents): void;

    /**
     * Connects and lists the root folder: proves the address and the account work. Returns what was found.
     */
    public function test(): string;

    /**
     * Ends the session (after a batch).
     */
    public function close(): void;
}
