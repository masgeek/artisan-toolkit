<?php

declare(strict_types=1);

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

final class ConfigDiffCommand extends Command
{
    protected $signature = 'config:diff';

    protected $description = 'Compare published config with package defaults';

    public function handle(): int
    {
        $this->newLine();
        $this->info(" <fg=cyan>Toolkit Configuration Diff</>");
        $this->line("Comparing <comment>config/artisan-toolkit.php</comment> with package defaults.");
        $this->newLine();

        $defaults = [
            'overrides' => [
                'schema:dump' => \Masgeek\ArtisanToolkit\Commands\SchemaDumpCommand::class,
                'key:generate' => \Masgeek\ArtisanToolkit\Commands\RotateAppKey::class,
            ],
            'model_scan_paths' => [
                'app/Models',
                'app/Models/Base',
            ],
            'encrypted_models' => [],
            'key_storage_path' => storage_path('app/keys/app-keys.json'),
            'queues' => ['default','high','low'],
            'queue_timeout' => 60,
            'commands' => [
                'make:enum' => \Masgeek\ArtisanToolkit\Commands\MakeEnumCommand::class,
                'make:api-scaffold' => \Masgeek\ArtisanToolkit\Commands\MakeApiScaffoldCommand::class,
                'make:resource-full' => \Masgeek\ArtisanToolkit\Commands\MakeFullResourceCommand::class,
                'make:repo' => \Masgeek\ArtisanToolkit\Commands\MakeRepositoryCommand::class,
                'make:dto' => \Masgeek\ArtisanToolkit\Commands\MakeDtoCommand::class,
                'make:service' => \Masgeek\ArtisanToolkit\Commands\MakeServiceCommand::class,
                'make:api-endpoint' => \Masgeek\ArtisanToolkit\Commands\MakeApiEndpointCommand::class,
                'model:relations' => \Masgeek\ArtisanToolkit\Commands\ListModelRelationsCommand::class,
                'model:prune-orphaned' => \Masgeek\ArtisanToolkit\Commands\PruneOrphanedModelsCommand::class,
                'queues:list' => \Masgeek\ArtisanToolkit\Commands\QueuesListCommand::class,
                'queues:listen' => \Masgeek\ArtisanToolkit\Commands\QueuesListenCommand::class,
                'queues:clear' => \Masgeek\ArtisanToolkit\Commands\QueuesClearCommand::class,
                'config:diff' => \Masgeek\ArtisanToolkit\Commands\ConfigDiffCommand::class,
                'db:seed-partial' => \Masgeek\ArtisanToolkit\Commands\DbSeedPartialCommand::class,
                'app:status' => \Masgeek\ArtisanToolkit\Commands\AppStatusCommand::class,
                'route:filter' => \Masgeek\ArtisanToolkit\Commands\RouteFilterCommand::class,
            ],
        ];

        $userConfig = config('artisan-toolkit');

        if ($userConfig === null) {
            $this->error("Config not found. Please publish it first.");
            return self::FAILURE;
        }

        $rows = [];
        foreach ($defaults as $key => $defaultValue) {
            $userValue = $userConfig[$key] ?? null;

            if ($userValue === $defaultValue) {
                continue;
            }

            if (is_array($defaultValue)) {
                $rows = array_merge($rows, $this->diffArray($key, $defaultValue, $userValue));
            } else {
                $rows[] = [
                    $key,
                    $this->formatValue($defaultValue),
                    $this->formatValue($userValue),
                ];
            }
        }

        if (empty($rows)) {
            $this->info("Config is identical to defaults. <fg=green>✓</>");
        } else {
            $this->table(
                ['Setting', 'Default Value', 'Your Value'],
                $rows
            );
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function diffArray(string $key, array $default, mixed $user): array
    {
        $rows = [];
        $userArray = is_array($user) ? $user : [];

        $allKeys = array_unique(array_merge(array_keys($default), array_keys($userArray)));

        foreach ($allKeys as $subKey) {
            $defVal = $default[$subKey] ?? null;
            $usrVal = $userArray[$subKey] ?? null;

            if ($defVal === $usrVal) {
                continue;
            }

            $status = 'Modified';
            if ($defVal === null) {
                $status = '<fg=green>Added</>';
            } elseif ($usrVal === false || $usrVal === null) {
                $status = '<fg=red>Disabled</>';
            }

            $rows[] = [
                "{$key}.{$subKey} ({$status})",
                $this->formatValue($defVal),
                $this->formatValue($usrVal),
            ];
        }

        return $rows;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) return '<fg=gray>null</>';
        if ($value === false) return '<fg=red>false</>';
        if ($value === true) return '<fg=green>true</>';
        if (is_array($value)) return '<fg=gray>Array</>';
        if (is_string($value) && str_contains($value, '::class')) {
            return '<fg=cyan>' . $value . '</>';
        }

        return (string)$value;
    }
}
