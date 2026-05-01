# Laravel PG Reset Sequences

A simple Artisan command that resets PostgreSQL auto-increment sequences after importing a SQL dump.

## The Problem

When you import a PostgreSQL SQL dump into an existing database, the sequences (used for auto-incrementing IDs) are not updated to reflect the data that was just imported. This causes a `SQLSTATE[23505]: Unique violation` error when trying to insert new rows, because the sequence tries to use IDs that already exist.

```
SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "types_pkey"
DETAIL: Key (id)=(1) already exists.
```

## Installation

```bash
composer require dipesh79/laravel-pg-reset-sequences
```

The package auto-discovers itself via Laravel's package auto-discovery. No manual registration needed.

## Usage

### Dry run (preview only — no changes made)

```bash
php artisan db:reset-sequences --dry-run
```

### Reset all sequences

```bash
php artisan db:reset-sequences
```

### Example output

```
+------------------+--------+--------------------+-------------+--------+----------+-------------+
| Table            | Column | Sequence           | Current Val | Max ID | Next Val | Status      |
+------------------+--------+--------------------+-------------+--------+----------+-------------+
| types            | id     | types_id_seq1      | 1           | 5      | 6        | NEEDS RESET |
| users            | id     | users_id_seq1      | 1           | 12     | 13       | NEEDS RESET |
| products         | id     | products_id_seq1   | 1           | 0      | 1        | OK          |
+------------------+--------+--------------------+-------------+--------+----------+-------------+
2 sequence(s) reset successfully.
```

## How It Works

The command queries `information_schema.columns` for all columns with a `nextval(...)` default and parses the sequence name directly from the `column_default` value. This approach works even when PostgreSQL has renamed sequences after a dump import (e.g. `types_id_seq` → `types_id_seq1`).

For each sequence it:
1. Reads the current `last_value` of the sequence
2. Reads the `MAX(id)` from the actual table
3. Calls `setval(sequence, max_id + 1, false)` so the next insert uses the correct next value
4. Skips sequences that are already in sync

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- PostgreSQL connection

## License

MIT