<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Dumps the database to a SQL file and restores it, in PHP, so backups do
 * not depend on the mysqldump and mysql programs being installed.
 *
 * The file has one statement per line: CREATE TABLE statements are folded
 * onto one line and quoted values have their line breaks escaped. Tables
 * listed in config('backup.excluded_tables') are neither dumped nor dropped.
 */
class DatabaseDumper
{
    /**
     * Rows per INSERT statement, and the size at which a statement is
     * written early (MariaDB's max_allowed_packet is often 1 MB).
     */
    private const ROWS_PER_INSERT = 200;

    private const MAX_INSERT_BYTES = 512 * 1024;

    public function __construct(private ?string $connectionName = null) {}

    public function dump(string $path): void
    {
        $connection = $this->connection();
        $pdo = $connection->getPdo();
        $out = fopen($path, 'wb');

        $snapshot = $connection->transactionLevel() === 0;

        if ($snapshot) {
            $connection->unprepared('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        }

        try {
            fwrite($out, '-- '.config('app.name').' database backup, '.now()->toIso8601String()."\n");
            fwrite($out, "SET NAMES utf8mb4;\n");
            fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");

            foreach ($this->tables() as $table) {
                $create = (array) $connection->selectOne('SHOW CREATE TABLE `'.$table.'`');

                fwrite($out, 'DROP TABLE IF EXISTS `'.$table."`;\n");
                fwrite($out, str_replace(["\r\n", "\n", "\r"], ' ', $create['Create Table']).";\n");

                $columns = null;
                $values = [];
                $bytes = 0;

                foreach ($connection->cursor('SELECT * FROM `'.$table.'`') as $row) {
                    $row = (array) $row;
                    $columns ??= '(`'.implode('`,`', array_keys($row)).'`)';

                    $tuple = '('.implode(',', array_map(fn (mixed $value): string => match (true) {
                        $value === null => 'NULL',
                        is_bool($value) => $value ? '1' : '0',
                        is_int($value), is_float($value) => (string) $value,
                        default => $pdo->quote((string) $value),
                    }, $row)).')';

                    $values[] = $tuple;
                    $bytes += strlen($tuple);

                    if (count($values) >= self::ROWS_PER_INSERT || $bytes >= self::MAX_INSERT_BYTES) {
                        fwrite($out, 'INSERT INTO `'.$table.'` '.$columns.' VALUES '.implode(',', $values).";\n");
                        $values = [];
                        $bytes = 0;
                    }
                }

                if ($values !== []) {
                    fwrite($out, 'INSERT INTO `'.$table.'` '.$columns.' VALUES '.implode(',', $values).";\n");
                }
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($out);

            if ($snapshot) {
                $connection->unprepared('COMMIT');
            }
        }
    }

    /**
     * Replace every table (except the excluded ones) with the dump's contents.
     */
    public function restore(string $path): void
    {
        $connection = $this->connection();
        $in = fopen($path, 'rb');

        $connection->unprepared('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables() as $table) {
                $connection->unprepared('DROP TABLE IF EXISTS `'.$table.'`');
            }

            while (($line = fgets($in)) !== false) {
                $statement = rtrim($line, "\r\n");

                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                $connection->unprepared($statement);
            }
        } finally {
            fclose($in);
            $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * The database's tables, without views and the excluded tables.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $excluded = config('backup.excluded_tables', []);

        return collect($this->connection()->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn (object $row): string => (string) array_values((array) $row)[0])
            ->reject(fn (string $table): bool => in_array($table, $excluded, true))
            ->values()
            ->all();
    }

    private function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }
}
