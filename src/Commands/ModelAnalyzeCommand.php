<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ModelAnalyzeCommand extends Command
{
    protected $signature = 'model:analyze {model : Fully qualified model class}';

    protected $description = 'Deep dive analysis of an Eloquent model';

    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Class {$modelClass} does not exist.");
            return self::FAILURE;
        }

        $instance = app($modelClass);
        $table = $instance->getTable();

        $this->info("Analysis for <comment>{$modelClass}</comment>");
        $this->line("Table: {$table}");
        $this->newLine();

        $columns = Schema::getColumnListing($table);
        $casts = $instance->getCasts();

        $rows = [];
        foreach ($columns as $col) {
            $cast = $casts[$col] ?? 'none';
            $rows[] = [$col, $cast];
        }

        $this->table(['Column', 'Cast'], $rows);

        return self::SUCCESS;
    }
}
