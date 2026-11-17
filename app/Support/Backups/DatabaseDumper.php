<?php

namespace App\Support\Backups;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A plain SQL dump of the MySQL database (the server has no mysqldump): per table DROP + CREATE and the
 * rows as INSERTs of 200 rows. Every statement ends with ";" at the end of a line, so import() can read it
 * back statement by statement. Cache, sessions and queued jobs are dumped without their rows.
 */
class DatabaseDumper
{
    public const WITHOUT_ROWS = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function dump(string $path): void
    {
        $pdo = DB::connection()->getPdo();
        $out = fopen($path, 'wb');
        fwrite($out, '-- Power ERP database backup '.now()->toIso8601String()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");

        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = (string) array_values((array) $row)[0];
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            fwrite($out, "\nDROP TABLE IF EXISTS `{$table}`;\n".str_replace("\n", ' ', (string) ($create['Create Table'] ?? array_values($create)[1])).";\n");

            if (in_array($table, self::WITHOUT_ROWS, true)) {
                continue;
            }

            $keys = collect(DB::select("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'"))->sortBy('Seq_in_index')->pluck('Column_name')->all();
            $order = $keys === [] ? '' : ' ORDER BY '.implode(', ', array_map(fn (string $key): string => "`{$key}`", $keys));

            for ($offset = 0; ; $offset += 500) {
                $rows = DB::select("SELECT * FROM `{$table}`{$order} LIMIT 500 OFFSET {$offset}");

                if ($rows === []) {
                    break;
                }

                foreach (array_chunk($rows, 200) as $chunk) {
                    $columns = '`'.implode('`, `', array_keys((array) $chunk[0])).'`';
                    $values = array_map(fn (object $record): string => '('.implode(', ', array_map(
                        fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                        array_values((array) $record),
                    )).')', $chunk);

                    fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES ".implode(', ', $values).";\n");
                }

                if (count($rows) < 500) {
                    break;
                }
            }
        }

        fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
    }

    /**
     * Runs a dump made by dump() against the database (its tables are replaced).
     */
    public function import(string $path): void
    {
        $in = fopen($path, 'rb') ?: throw new RuntimeException('The database dump cannot be read.');
        $statement = '';

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            while (($line = fgets($in)) !== false) {
                if ($statement === '' && (str_starts_with($line, '--') || trim($line) === '')) {
                    continue;
                }

                $statement .= $line;

                if (str_ends_with(rtrim($line, "\r\n"), ';')) {
                    DB::unprepared($statement);
                    $statement = '';
                }
            }
        } finally {
            fclose($in);
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
