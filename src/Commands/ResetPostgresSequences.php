<?php

namespace Dipesh79\PgResetSequences\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPostgresSequences extends Command
{
    protected $signature = 'db:reset-sequences
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Reset PostgreSQL auto-increment sequences to match the max existing ID in each table (run after importing a SQL dump)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('This command only supports PostgreSQL connections.');
            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in dry-run mode — no changes will be made.');
        }

        // Read sequence name directly from column_default — works even when
        // Postgres renamed the sequence (e.g. types_id_seq1) after a dump import.
        $columns = DB::select("
            SELECT
                table_schema,
                table_name,
                column_name,
                column_default
            FROM information_schema.columns
            WHERE column_default LIKE 'nextval(%'
            ORDER BY table_schema, table_name
        ");

        if (empty($columns)) {
            $this->warn('No sequences found. No columns with nextval() defaults detected.');
            return self::SUCCESS;
        }

        $rows       = [];
        $resetCount = 0;

        foreach ($columns as $col) {
            // Extract sequence name from: nextval('types_id_seq1'::regclass)
            preg_match("/nextval\('([^']+)'/", $col->column_default, $matches);

            if (empty($matches[1])) {
                continue;
            }

            $sequenceName = $matches[1]; // e.g. types_id_seq1
            $table        = $col->table_schema . '.' . $col->table_name;
            $column       = $col->column_name;

            $maxId = DB::selectOne(
                "SELECT COALESCE(MAX(\"{$column}\"), 0) AS max_id FROM {$table}"
            )->max_id;

            $currentVal = DB::selectOne(
                "SELECT last_value FROM \"{$sequenceName}\""
            )->last_value;

            $nextVal    = $maxId + 1;
            $needsReset = $currentVal < $nextVal;
            $status     = $needsReset ? '<fg=yellow>NEEDS RESET</>' : '<fg=green>OK</>';

            $rows[] = [$col->table_name, $column, $sequenceName, $currentVal, $maxId, $nextVal, $status];

            if ($needsReset) {
                $resetCount++;

                if (! $dryRun) {
                    DB::statement("SELECT setval('\"{$sequenceName}\"', {$nextVal}, false)");
                }
            }
        }

        if (empty($rows)) {
            $this->warn('No sequences could be resolved from column defaults.');
            return self::SUCCESS;
        }

        $this->table(
            ['Table', 'Column', 'Sequence', 'Current Val', 'Max ID', 'Next Val', 'Status'],
            $rows
        );

        if ($dryRun) {
            $this->info("{$resetCount} sequence(s) would be reset.");
        } else {
            $this->info("{$resetCount} sequence(s) reset successfully.");
        }

        return self::SUCCESS;
    }
}