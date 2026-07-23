<?php

namespace Masgeek\ArtisanToolkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;

class RotateAppKey extends Command
{
    protected $name = 'key:generate';

    protected $signature = 'key:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production}
                    {--no-env-file : Skip writing to the .env file (useful in Docker)}
                    {--reverse : Roll back key rotations using previous keys}
                    {--steps=1 : Number of previous keys to roll back when using --reverse}';

    protected $description = 'Set the application key, rotate old key to APP_PREVIOUS_KEYS, and re-encrypt configured model fields';

    public function handle(): int
    {
        $currentKey = config('app.key');

        $cipher = config('app.cipher', 'AES-256-CBC');

        if ($this->option('show')) {
            $this->line('<comment>'.base64_encode(Encrypter::generateKey($cipher)).'</comment>');

            return Command::SUCCESS;
        }

        // Fail early if no models are configured for re-encryption
        $modelsToProcess = config('artisan-toolkit.encrypted_models', []);
        if (empty($modelsToProcess)) {
            $this->error('No models configured for re-encryption in config/artisan-toolkit.php.');

            return Command::FAILURE;
        }

        if ($this->option('reverse')) {
            return $this->reverse($currentKey);
        }

        if (! $this->confirmRotation($currentKey)) {
            return Command::SUCCESS;
        }

        return $this->rotate($currentKey, $cipher);
    }

    /**
     * Prompt the user to confirm the forward key rotation.
     */
    private function confirmRotation(string $currentKey): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->newLine();
        $this->warn('This will:');
        $this->line('  1. Generate a new application key');
        $this->line('  2. Move the current key into APP_PREVIOUS_KEYS');
        $this->line('  3. Re-encrypt all configured model fields with the new key');

        if (config('artisan-toolkit.key_storage_path')) {
            $this->line('  4. Write keys to '.config('artisan-toolkit.key_storage_path'));
        }

        $this->newLine();

        return $this->confirm('Do you want to proceed with key rotation?', false);
    }

    /**
     * Prompt the user to confirm the reverse key rotation.
     */
    private function confirmReverse(int $steps, int $available): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->newLine();
        $this->warn("This will reverse {$steps} key rotation(s) out of {$available} available.");
        $this->line('  1. Re-encrypt all configured model fields back to previous key(s)');
        $this->line('  2. Restore the oldest rolled-back key as APP_KEY');

        if (config('artisan-toolkit.key_storage_path')) {
            $this->line('  3. Write keys to '.config('artisan-toolkit.key_storage_path'));
        }

        $this->newLine();

        return $this->confirm('Do you want to proceed with key reversal?', false);
    }

    /**
     * Forward rotation: generate a new key and re-encrypt.
     */
    private function rotate(string $currentKey, string $cipher): int
    {
        $newKey = 'base64:'.base64_encode(Encrypter::generateKey($cipher));

        $envPath = app()->environmentFilePath();
        $envWritable = $this->isEnvWritable($envPath);

        if ($envWritable) {
            $this->updateEnvironmentKeysForRotation($envPath, $currentKey, $newKey);
            $this->info('Rotated previous APP_KEY into APP_PREVIOUS_KEYS ring.');
        }

        // Build new previous keys: current key moves to front of the ring
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
            $this->printEnvInstructions($newKey, $previousKeys);
        }

        if (! $this->reEncryptConfiguredModels()) {
            return Command::FAILURE;
        }

        $this->writeKeyFile($newKey, $previousKeys);

        return Command::SUCCESS;
    }

    /**
     * Reverse rotation: iterate through N previous keys and re-encrypt back.
     */
    private function reverse(string $currentKey): int
    {
        $steps = max(1, (int) $this->option('steps'));
        $previousKeys = config('app.previous_keys', []);

        if (empty($previousKeys)) {
            $this->error('No previous keys available to reverse.');

            return Command::FAILURE;
        }

        if ($steps > count($previousKeys)) {
            $this->error("Requested {$steps} steps but only ".count($previousKeys).' previous key(s) available.');

            return Command::FAILURE;
        }

        if (! $this->confirmReverse($steps, count($previousKeys))) {
            return Command::SUCCESS;
        }

        // Pop the N keys to restore (from front of array = most recent)
        $keysToRestore = array_slice($previousKeys, 0, $steps);
        $remainingPrevious = array_slice($previousKeys, $steps);

        $this->info("Reversing {$steps} key rotation(s)...");

        // Iterate through each key, re-encrypting data from current key back.
        // Each step: decrypt with workingKey (moved to previous_keys), re-encrypt with targetKey.
        $workingKey = $currentKey;
        foreach ($keysToRestore as $targetKey) {
            $this->info('Re-encrypting from current key back to previous key...');

            config([
                'app.key' => $targetKey,
                'app.previous_keys' => array_merge([$workingKey], $remainingPrevious),
            ]);

            $this->reEncryptConfiguredModels();

            $workingKey = $targetKey;
        }

        // Final state: restored key becomes APP_KEY, current key joins previous ring
        $newPreviousKeys = array_merge([$currentKey], $remainingPrevious);

        $envPath = app()->environmentFilePath();
        $envWritable = $this->isEnvWritable($envPath);

        if ($envWritable) {
            $this->updateEnvironmentKeysForReverse($envPath, $workingKey, $newPreviousKeys);
        }

        config([
            'app.key' => $workingKey,
            'app.previous_keys' => $newPreviousKeys,
        ]);

        $this->info('Key rotation reversed successfully.');

        if (! $envWritable) {
            $this->printEnvInstructions($workingKey, $newPreviousKeys);
        }

        $this->writeKeyFile($workingKey, $newPreviousKeys);

        return Command::SUCCESS;
    }

    /**
     * Determine if the .env file is present and writable.
     */
    private function isEnvWritable(string $envPath): bool
    {
        if ($this->option('no-env-file')) {
            return false;
        }

        if (! file_exists($envPath)) {
            $this->warn('.env file not found — skipping file update.');

            return false;
        }

        if (! is_writable($envPath)) {
            $this->warn('.env file is not writable — skipping file update.');

            return false;
        }

        return true;
    }

    /**
     * Update .env for a forward key rotation.
     */
    private function updateEnvironmentKeysForRotation(string $envPath, string $oldKey, string $newKey): void
    {
        $envContent = file_get_contents($envPath);

        $existingPreviousRaw = env('APP_PREVIOUS_KEYS', '');
        $previousKeysArray = array_filter(explode(',', $existingPreviousRaw));

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
     * Update .env for a reverse key rotation.
     */
    private function updateEnvironmentKeysForReverse(string $envPath, string $restoredKey, array $previousKeys): void
    {
        $envContent = file_get_contents($envPath);
        $newPreviousKeysString = implode(',', $previousKeys);

        // Set or update APP_KEY
        if (preg_match('/^APP_KEY=/m', $envContent)) {
            $envContent = preg_replace('/^APP_KEY=.*$/m', "APP_KEY={$restoredKey}", $envContent);
        } else {
            $envContent .= PHP_EOL."APP_KEY={$restoredKey}";
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
     * Print env variable instructions when .env cannot be written.
     */
    private function printEnvInstructions(string $key, array $previousKeys): void
    {
        $this->newLine();
        $this->warn('Set the following env variables before restarting:');
        $this->line("  APP_KEY={$key}");
        if (! empty($previousKeys)) {
            $escaped = implode(',', $previousKeys);
            $this->line("  APP_PREVIOUS_KEYS=\"{$escaped}\"");
        }
        $this->newLine();
    }

    /**
     * Write current and previous keys to a JSON file for Docker volume mounting.
     */
    private function writeKeyFile(string $currentKey, array $previousKeys): void
    {
        $keyStoragePath = config('artisan-toolkit.key_storage_path');

        if ($keyStoragePath === null || $keyStoragePath === '') {
            return;
        }

        $data = [
            'current_key' => $currentKey,
            'previous_keys' => array_values($previousKeys),
            'updated_at' => now()->toIso8601String(),
        ];

        $directory = dirname($keyStoragePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($keyStoragePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Keys written to {$keyStoragePath}");
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
