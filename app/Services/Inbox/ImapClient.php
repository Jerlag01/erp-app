<?php

namespace App\Services\Inbox;

use RuntimeException;

/**
 * A small IMAP client (RFC 3501) for reading one mailbox folder: log in, list the messages after
 * a UID, fetch one whole message, and optionally move it to another folder. Plain sockets with
 * TLS, because PHP 8.4 no longer ships the imap extension.
 */
class ImapClient
{
    /** @var resource|null */
    protected $socket = null;

    protected int $tag = 0;

    /** @var list<string> */
    protected array $capabilities = [];

    public function __construct(
        protected string $host,
        protected int $port = 993,
        protected string $encryption = 'ssl',
        protected int $timeout = 30,
    ) {}

    public function connect(string $username, string $password): void
    {
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->host]]);
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $socket = @stream_socket_client("{$scheme}://{$this->host}:{$this->port}", $errno, $error, $this->timeout, STREAM_CLIENT_CONNECT, $context);

        if (! is_resource($socket)) {
            throw new RuntimeException("Can not reach the mail server {$this->host}:{$this->port} ({$error}).");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
        $greeting = $this->readLine();

        if (! str_starts_with($greeting, '* OK') && ! str_starts_with($greeting, '* PREAUTH')) {
            throw new RuntimeException('Unexpected greeting from the mail server.');
        }

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS');

            if (! stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('STARTTLS failed.');
            }
        }

        $this->command('LOGIN '.self::quote($username).' '.self::quote($password));
        $this->capabilities = $this->capabilityList();
    }

    /**
     * Opens a folder and returns its UIDVALIDITY (changes when the folder is rebuilt).
     */
    public function select(string $folder): int
    {
        $response = $this->command('SELECT '.self::quote(self::folderName($folder)));

        foreach ($response['lines'] as $line) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $line, $match)) {
                return (int) $match[1];
            }
        }

        return 0;
    }

    /**
     * The UIDs above the given one, oldest first.
     *
     * @return list<int>
     */
    public function uidsAfter(int $uid): array
    {
        $response = $this->command('UID SEARCH UID '.($uid + 1).':*');
        $uids = [];

        foreach ($response['lines'] as $line) {
            if (preg_match('/^\* SEARCH(.*)$/i', $line, $match)) {
                foreach (preg_split('/\s+/', trim($match[1])) as $value) {
                    if (ctype_digit($value) && (int) $value > $uid) {
                        $uids[] = (int) $value;
                    }
                }
            }
        }

        sort($uids);

        return array_values(array_unique($uids));
    }

    /**
     * The whole message, without marking it read.
     */
    public function fetch(int $uid): ?string
    {
        $response = $this->command("UID FETCH {$uid} BODY.PEEK[]");

        return $response['literals'][0] ?? null;
    }

    public function move(int $uid, string $folder): void
    {
        $target = self::quote(self::folderName($folder));

        if (in_array('MOVE', $this->capabilities, true)) {
            $this->command("UID MOVE {$uid} {$target}");

            return;
        }

        $this->command("UID COPY {$uid} {$target}");
        $this->command("UID STORE {$uid} +FLAGS.SILENT (\\Deleted)");
        $this->command(in_array('UIDPLUS', $this->capabilities, true) ? "UID EXPUNGE {$uid}" : 'EXPUNGE');
    }

    public function markSeen(int $uid): void
    {
        $this->command("UID STORE {$uid} +FLAGS.SILENT (\\Seen)");
    }

    public function logout(): void
    {
        if (is_resource($this->socket)) {
            try {
                $this->command('LOGOUT');
            } catch (RuntimeException) {
                // Closing anyway.
            }

            fclose($this->socket);
        }

        $this->socket = null;
    }

    /**
     * @return list<string>
     */
    protected function capabilityList(): array
    {
        foreach ($this->command('CAPABILITY')['lines'] as $line) {
            if (preg_match('/^\* CAPABILITY (.*)$/i', $line, $match)) {
                return array_map('strtoupper', preg_split('/\s+/', trim($match[1])));
            }
        }

        return [];
    }

    /**
     * Sends a command and reads up to its tagged answer; literals ({n} blocks) are returned apart.
     *
     * @return array{lines: list<string>, literals: list<string>}
     */
    protected function command(string $command): array
    {
        $tag = 'A'.str_pad((string) ++$this->tag, 4, '0', STR_PAD_LEFT);
        $this->write("{$tag} {$command}\r\n");
        $lines = [];
        $literals = [];

        while (true) {
            $line = $this->readLine();

            while (preg_match('/\{(\d+)\}$/', $line, $match)) {
                $literals[] = $this->readBytes((int) $match[1]);
                $line .= ' '.$this->readLine();
            }

            if (str_starts_with($line, $tag.' ')) {
                if (! preg_match('/^'.$tag.' OK/i', $line)) {
                    $shown = str_starts_with($command, 'LOGIN') ? 'LOGIN' : strtok($command, ' ');

                    throw new RuntimeException("The mail server refused {$shown}: ".trim(substr($line, strlen($tag) + 1)));
                }

                return ['lines' => $lines, 'literals' => $literals];
            }

            $lines[] = $line;
        }
    }

    protected function write(string $data): void
    {
        if (! is_resource($this->socket) || @fwrite($this->socket, $data) === false) {
            throw new RuntimeException('The connection to the mail server was lost.');
        }
    }

    protected function readLine(): string
    {
        $line = is_resource($this->socket) ? fgets($this->socket) : false;

        if ($line === false) {
            throw new RuntimeException('The connection to the mail server was lost.');
        }

        return rtrim($line, "\r\n");
    }

    protected function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->socket, min(65536, $length - strlen($data)));

            if ($chunk === false || ($chunk === '' && feof($this->socket))) {
                throw new RuntimeException('The connection to the mail server was lost.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    protected static function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * Folder names travel in modified UTF-7 ("Factures reçues").
     */
    protected static function folderName(string $folder): string
    {
        return mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
    }
}
