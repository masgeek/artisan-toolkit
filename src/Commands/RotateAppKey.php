<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

class RotateAppKey extends Command
{
    /**
     * Override native key:generate signature and description.
     */
    protected $name = 'key:generate';

    protected $signature = 'key:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production}
                    {--no-env-file : Skip writing to the .env file (useful in Docker)}';

    protected $description = 'Set the application key, rotate old key to APP_PREVIOUS_KEYS, and re-encrypt configured model fields';

    public function handle(): int
    {
        $currentKey = config('app.key');

        // 1. Generate new AES key based on application cipher settings
        $cipher = config('app.cipher', 'AES-256-CBC');
        $newKey = 'base64:'.base64_encode(Encrypter::generateKey($cipher));

        if ($this->option('show')) {
            $this->line('<comment>'.$newKey.'</comment>');

            return Command::SUCCESS;
        }

        // Fail early if no models are configured for re-encryption
        $modelsToProcess = config('artisan-toolkit.encrypted_models', []);
        if (empty($modelsToProcess)) {
            $this->error('No models configured for re-encryption in config/artisan-toolkit.php.');

            return Command::FAILURE;
        }

        $envPath = app()->environmentFilePath();
        $envWritable = ! $this->option('no-env-file')
            && file_exists($envPath)
            && is_writable($envPath);

        if (! $this->option('no-env-file') && ! file_exists($envPath)) {
            $this->warn('.env file not found — skipping file update.');
        } elseif (! $this->option('no-env-file') && ! is_writable($envPath)) {
            $this->warn('.env file is not writable — skipping file update.');
        }

        if ($envWritable) {
            $this->updateEnvironmentKeys($envPath, $currentKey, $newKey);
            $this->info('Rotated previous APP_KEY into APP_PREVIOUS_KEYS ring.');
        }

        // 3. Dynamically append current key to runtime APP_PREVIOUS_KEYS array
        $previousKeys = config('app.previous_keys', []);
        if (! empty($currentKey)) {
            array_unshift($previousKeys, $currentKey);
        }

        config([
            'app.key' => $newKey,
            'app.previous_keys' => $previousKeys,
        ]);

        $this->info('Application key set successfully.');

        if (! $envWritable) {
            $this->newLine();
            $this->warn('Set the following env variables before restarting:');
            $this->line("  APP_KEY={$newKey}");
            if (! empty($previousKeys)) {
                $escaped = implode(',', $previousKeys);
                $this->line("  APP_PREVIOUS_KEYS=\"{$escaped}\"");
            }
            $this->newLine();
        }

        // 4. Process all configured models and fields
        if (! $this->reEncryptConfiguredModels()) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Write new APP_KEY and append old key into APP_PREVIOUS_KEYS in .env
     */
    private function updateEnvironmentKeys(string $envPath, string $oldKey, string $newKey): void
    {
        $envContent = file_get_contents($envPath);

        // Parse current previous keys
        $existingPreviousRaw = env('APP_PREVIOUS_KEYS', '');
        $previousKeysArray = array_filter(explode(',', $existingPreviousRaw));

        // Push old key into previous keys array if valid and not already logged
        if (! empty($oldKey) && ! in_array($oldKey, $previousKeysArray, true)) {
            array_unshift($previousKeysArray, $oldKey);
        }

        $newPreviousKeysString = implode(',', $previousKeysArray);

        // Set or update APP_KEY
        if (preg_match('/^APP_KEY=/m', $envContent)) {
            $envContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$newKey}", $envContent);
        } else {
            $envContent .= PHP_EOL."APP_KEY={$newKey}";
        }

        // Set or update APP_PREVIOUS_KEYS
        if (preg_match('/^APP_PREVIOUS_KEYS=/m', $envContent)) {
            $envContent = preg_replace('/^APP_PREVIOUS_KEYS=.*$/m', "APP_PREVIOUS_KEYS=\"{$newPreviousKeysString}\"",
                $envContent);
        } else {
            $envContent .= PHP_EOL."APP_PREVIOUS_KEYS=\"{$newPreviousKeysString}\"";
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Loop through N models from configuration and re-encrypt their defined fields.
     */
    private function reEncryptConfiguredModels(): bool
    {
        $modelsToProcess = config('artisan-toolkit.encrypted_models', []);

        if (empty($modelsToProcess)) {
            $this->error('No models configured for re-encryption in config/artisan-toolkit.php.');

            return false;
        }

        $processed = 0;

        foreach ($modelsToProcess as $modelClass => $fields) {
            if (! class_exists($modelClass)) {
                $this->warn("Skipping class [{$modelClass}]: Model class does not exist.");

                continue;
            }

            if (empty($fields) || ! is_array($fields)) {
                $this->warn("Skipping class [{$modelClass}]: No attributes specified.");

                continue;
            }

            $this->info("Re-encrypting [{$modelClass}] fields: ".implode(', ', $fields));
            $this->processModelInChunks($modelClass, $fields);
            $processed++;
        }

        if ($processed === 0) {
            $this->error('No valid models found for re-encryption. Ensure models exist and have fields defined.');

            return false;
        }

        return true;
    }

    /**
     * Chunk-process individual models safely inside database transactions.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $fields
     */
    private function processModelInChunks(string $modelClass, array $fields): void
    {
        $count = 0;

        $modelClass::chunk(100, function ($records) use ($fields, &$count) {
            DB::transaction(function () use ($records, $fields, &$count) {
                foreach ($records as $record) {
                    $dirty = false;

                    foreach ($fields as $field) {
                        $value = $record->getAttribute($field);

                        if ($value !== null) {
                            // Re-assigning field triggers Eloquent 'encrypted' cast with NEW APP_KEY
                            $record->setAttribute($field, $value);
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $record->saveQuietly();
                        $count++;
                    }
                }
            });
        });

        $this->info(" -> {$count} records updated for [{$modelClass}].");
    }
}
