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
 * migrations table, leaving pending (unrun) migration files untouched.
 */
class SchemaDumpCommand extends DumpCommand
{
    protected $signature = 'schema:dump
                {--database= : The database connection to use}
                {--path= : The path where the schema dump file should be stored}
                {--prune : Delete only migration files that have already been run (pending migrations are kept)}';

    protected $description = 'Dump the database schema; --prune removes only already-run migration files';

    /**
     * @param ConnectionResolverInterface $connections
     * @param Dispatcher $dispatcher
     * @return int
     */
    public function handle(ConnectionResolverInterface $connections, Dispatcher $dispatcher): int
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        $connection = $connections->connection($this->input->getOption('database'));

        $this->schemaState($connection)->dump($connection, $path = $this->path($connection));

        $dispatcher->dispatch(new SchemaDumped($connection, $path));

        $info = 'Database schema dumped';

        if ($this->option('prune')) {
            $result = $this->safePrune($connection);
            $dispatcher->dispatch(new MigrationsPruned($connection, database_path('migrations')));
            $info .= " and pruned ({$result['deleted']} deleted, {$result['kept']} pending kept)";
        }

        $this->components->info($info . ' successfully.');

        return self::SUCCESS;
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
            ->map(fn ($m) => $m . '.php')
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
