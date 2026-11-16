<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Console\DumpCommand;
use Illuminate\Database\Events\MigrationsPruned;
use Illuminate\Database\Events\SchemaDumped;
use Illuminate\Filesystem\Filesystem;

/**
 * Replaces the built-in schema:dump --prune behaviour.
 *
 * The original --prune deletes every file under database/migrations.
 * This version only deletes files whose migration name appears in the
 * migration table, leaving pending (unrun) migration files untouched.
 *
 * Set 'schema_dump_include_schema' to false in config/artisan-toolkit.php
 * to generate schema-agnostic dumps (without schema prefix like "public." or "dbo.").
 */
class SchemaDumpCommand extends DumpCommand
{
    protected $signature = 'schema:dump
                {--database= : The database connection to use}
                {--path= : The path where the schema dump file should be stored}
                {--prune : Delete only migration files that have already been run (pending migrations are kept)}
                {--no-schema : Force a schema-agnostic dump for this run, overriding schema_dump_include_schema}
                {--without-migration-data : Dump the schema without the migration data}';

    protected $description = 'Dump the database schema; --prune removes only already-run migration files';

    public function handle(ConnectionResolverInterface $connections, Dispatcher $dispatcher): int
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        $connection = $connections->connection($this->input->getOption('database'));

        $this->schemaState($connection)->dump($connection, $path = $this->path($connection));

        $dispatcher->dispatch(new SchemaDumped($connection, $path));

        $this->maybeStripSchemaPrefix($connection, $path, $this->option('no-schema'));

        $info = 'Database schema dumped';

        if ($this->option('prune')) {
            $result = $this->safePrune($connection);
            $dispatcher->dispatch(new MigrationsPruned($connection, database_path('migrations')));
            $info .= " and pruned ({$result['deleted']} deleted, {$result['kept']} pending kept)";
        }

        $this->components->info($info.' successfully.');

        return self::SUCCESS;
    }

    /**
     * Strip the schema prefix from the dumped SQL file if configured.
     *
     * When 'schema_dump_include_schema' is false in config, removes the
     * connection's schema qualifier (e.g. "public." or "dbo.") from every
     * identifier so the dump can be loaded into any schema.
     */
    private function maybeStripSchemaPrefix(Connection $connection, string $path, bool $force = false): void
    {
        if (! $force && config('artisan-toolkit.schema_dump_include_schema', true)) {
            return;
        }

        $schema = $this->resolveSchemaName($connection);

        if ($schema === null) {
            return;
        }

        $contents = file_get_contents($path);

        file_put_contents($path, $this->stripSchemaPrefix($contents, $schema));
    }

    /**
     * Remove schema qualification from identifiers in a SQL dump and drop the
     * statements that bind the dump to a specific schema.
     *
     * Handles unquoted (public.users), double-quoted ("public"."users") and
     * bracket-quoted ([dbo].[users]) identifiers, removes CREATE SCHEMA
     * statements, and drops search_path pinning such as
     * SELECT pg_catalog.set_config('search_path', '', false); and
     * SET search_path = ...;
     */
    private function stripSchemaPrefix(string $contents, string $schema): string
    {
        $quoted = preg_quote($schema, '/');

        return preg_replace([
            '/^[ \t]*CREATE\s+SCHEMA\b[^;]*;[ \t]*\R?/im',
            "/^[ \t]*SELECT\\s+pg_catalog\\.set_config\\(\\s*'search_path'.*?\\);[ \t]*\\R?/im",
            '/^[ \t]*SET\s+search_path\s*=.*?;[ \t]*\R?/im',
            '/\b'.$quoted.'\./i',
            '/"'.$quoted.'"\./i',
            '/\['.$quoted.'\]\./i',
        ], '', $contents);
    }

    /**
     * Resolve the schema qualifier the dump would use for this connection.
     * Returns null for drivers whose dumps are not schema-qualified.
     */
    private function resolveSchemaName(Connection $connection): ?string
    {
        $config = $connection->getConfig();

        $schema = $config['schema'] ?? $config['search_path'] ?? null;

        if (is_string($schema) && $schema !== '') {
            // search_path may be a comma-separated preference list.
            return trim(explode(',', $schema)[0]);
        }

        return match ($config['driver'] ?? '') {
            'pgsql' => 'public',
            'sqlsrv', 'mssql' => 'dbo',
            default => null,
        };
    }

    /**
     * Delete only migration files that have an entry in the migrations table.
     * Files for pending (unrun) migrations are left on disk.
     *
     * @return array{deleted: int, kept: int}
     */
    private function safePrune(Connection $connection): array
    {
        $migrationsConfig = config('database.migrations', 'migrations');
        $table = is_array($migrationsConfig)
            ? ($migrationsConfig['table'] ?? 'migrations')
            : $migrationsConfig;

        $ran = $connection->table($table)
            ->pluck('migration')
            ->map(fn ($m) => $m.'.php')
            ->flip()
            ->all();

        $fs = new Filesystem;
        $deleted = 0;
        $kept = 0;

        foreach ($fs->files(database_path('migrations')) as $file) {
            $filename = $file->getFilename();

            if (isset($ran[$filename])) {
                $fs->delete($file->getPathname());
                $this->components->twoColumnDetail($filename, '<fg=red;options=bold>deleted</>');
                $deleted++;
            } else {
                $this->components->twoColumnDetail($filename, '<fg=yellow>kept</> <fg=gray>(pending)</>');
                $kept++;
            }
        }

        return compact('deleted', 'kept');
    }
}
