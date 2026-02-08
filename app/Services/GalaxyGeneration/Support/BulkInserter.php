<?php

namespace App\Services\GalaxyGeneration\Support;

use Illuminate\Support\Facades\DB;

/**
 * Efficient bulk database operations.
 *
 * Handles chunked inserts and updates to prevent memory issues
 * and maximize database throughput.
 */
final class BulkInserter
{
    public const DEFAULT_CHUNK_SIZE = 500;

    /**
     * Get the SQL function for current timestamp based on database driver.
     */
    private static function nowFunction(): string
    {
        return DB::getDriverName() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    /**
     * Bulk insert rows into a table.
     * Uses optimized raw SQL for large batches.
     *
     * @param  string  $table  Table name
     * @param  array  $rows  Rows to insert
     * @param  int  $chunkSize  Rows per batch
     * @return int Total rows inserted
     */
    public static function insert(string $table, array $rows, int $chunkSize = self::DEFAULT_CHUNK_SIZE): int
    {
        if (empty($rows)) {
            return 0;
        }

        $inserted = 0;

        // For large datasets, use raw SQL which is faster
        if (count($rows) > 5000) {
            return self::rawInsert($table, $rows, $chunkSize);
        }

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
            $inserted += count($chunk);
        }

        return $inserted;
    }

    /**
     * Raw SQL insert for maximum performance.
     */
    private static function rawInsert(string $table, array $rows, int $chunkSize): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $columnList = '`'.implode('`, `', $columns).'`';
        $inserted = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $values = [];
            $params = [];

            foreach ($chunk as $row) {
                $placeholders = [];
                foreach ($columns as $col) {
                    $value = $row[$col] ?? null;
                    if ($value === null) {
                        $placeholders[] = 'NULL';
                    } elseif (is_bool($value)) {
                        $placeholders[] = $value ? '1' : '0';
                    } else {
                        $placeholders[] = '?';
                        $params[] = $value;
                    }
                }
                $values[] = '('.implode(', ', $placeholders).')';
            }

            $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES ".implode(', ', $values);
            DB::insert($sql, $params);
            $inserted += count($chunk);
        }

        return $inserted;
    }

    /**
     * Bulk insert with ignore on duplicate.
     *
     * @param  string  $table  Table name
     * @param  array  $rows  Rows to insert
     * @param  int  $chunkSize  Rows per batch
     * @return int Total rows inserted (excludes ignored)
     */
    public static function insertOrIgnore(string $table, array $rows, int $chunkSize = self::DEFAULT_CHUNK_SIZE): int
    {
        if (empty($rows)) {
            return 0;
        }

        $inserted = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $inserted += DB::table($table)->insertOrIgnore($chunk);
        }

        return $inserted;
    }

    /**
     * Bulk update using CASE WHEN pattern.
     *
     * @param  string  $table  Table name
     * @param  array  $updates  Array of ['id' => x, 'column' => value, ...]
     * @param  string  $keyColumn  Column to match on (default: 'id')
     * @param  array  $updateColumns  Columns to update
     * @param  int  $chunkSize  Rows per batch
     * @return int Total rows updated
     */
    public static function update(
        string $table,
        array $updates,
        string $keyColumn = 'id',
        array $updateColumns = [],
        int $chunkSize = self::DEFAULT_CHUNK_SIZE
    ): int {
        if (empty($updates) || empty($updateColumns)) {
            return 0;
        }

        $updated = 0;

        foreach (array_chunk($updates, $chunkSize) as $chunk) {
            $updated += self::executeUpdate($table, $chunk, $keyColumn, $updateColumns);
        }

        return $updated;
    }

    /**
     * Execute a single batch update.
     */
    private static function executeUpdate(string $table, array $chunk, string $keyColumn, array $updateColumns): int
    {
        $keys = array_column($chunk, $keyColumn);
        $setClauses = [];
        $params = [];

        foreach ($updateColumns as $column) {
            $cases = [];

            foreach ($chunk as $row) {
                $cases[] = "WHEN {$keyColumn} = ? THEN ?";
                $params[] = $row[$keyColumn];
                $params[] = $row[$column];
            }

            $setClauses[] = "{$column} = CASE ".implode(' ', $cases).' END';
        }

        $keyPlaceholders = implode(',', array_fill(0, count($keys), '?'));
        $params = array_merge($params, $keys);

        $sql = "UPDATE {$table} SET ".implode(', ', $setClauses).", updated_at = ".self::nowFunction()." WHERE {$keyColumn} IN ({$keyPlaceholders})";

        return DB::affectingStatement($sql, $params);
    }

    /**
     * Bulk upsert (insert or update on conflict).
     *
     * @param  string  $table  Table name
     * @param  array  $rows  Rows to upsert
     * @param  array|string  $uniqueBy  Unique column(s)
     * @param  array|null  $update  Columns to update on conflict (null = all)
     * @param  int  $chunkSize  Rows per batch
     * @return int Total rows affected
     */
    public static function upsert(
        string $table,
        array $rows,
        array|string $uniqueBy,
        ?array $update = null,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE
    ): int {
        if (empty($rows)) {
            return 0;
        }

        $affected = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $affected += DB::table($table)->upsert($chunk, $uniqueBy, $update);
        }

        return $affected;
    }
}
