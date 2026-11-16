<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Database\Seeders\ToolkitTestSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

class UtilityCommandsTest extends TestCase
{
    public function test_app_status_reports_versions_and_toolkit_counts(): void
    {
        $this->artisan('app:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Application Status')
            ->expectsOutputToContain('PHP Version:')
            ->expectsOutputToContain('Laravel Version:')
            ->expectsOutputToContain('Environment:')
            ->expectsOutputToContain('Toolkit Status')
            ->expectsOutputToContain('Active Overrides:')
            ->expectsOutputToContain('Active Commands:');
    }

    public function test_route_filter_matches_by_uri(): void
    {
        Route::get('v1/currencies', fn () => 'ok')->name('currencies.index');
        Route::get('v1/invoices', fn () => 'ok')->name('invoices.index');

        $this->artisan('route:filter', ['pattern' => 'currencies'])
            ->assertSuccessful()
            ->expectsOutputToContain('v1/currencies');
    }

    public function test_route_filter_matches_by_route_name(): void
    {
        Route::get('v1/widgets', fn () => 'ok')->name('widgets.index');

        $this->artisan('route:filter', ['pattern' => 'widgets.index'])
            ->assertSuccessful()
            ->expectsOutputToContain('widgets.index');
    }

    public function test_route_filter_returns_cleanly_with_no_matches(): void
    {
        $this->artisan('route:filter', ['pattern' => 'nothing-matches-this'])
            ->assertSuccessful();
    }

    public function test_route_filter_outputs_json_and_honours_method_and_except(): void
    {
        Route::get('v1/widgets', fn () => 'ok')->name('widgets.index');
        Route::post('v1/widgets', fn () => 'ok')->name('widgets.store');

        Artisan::call('route:filter', ['pattern' => 'widgets', '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertNotEmpty($decoded);
        $this->assertArrayHasKey('methods', $decoded[0]);

        Artisan::call('route:filter', ['pattern' => 'widgets', '--method' => 'POST']);
        $this->assertStringContainsString('POST', Artisan::output());

        Artisan::call('route:filter', ['pattern' => 'widgets', '--except' => 'store', '--json' => true]);
        foreach (json_decode(Artisan::output(), true) as $route) {
            $this->assertStringNotContainsString('store', (string) $route['name']);
        }
    }

    public function test_route_filter_honours_middleware_filter(): void
    {
        Route::get('v1/guarded', fn () => 'ok')->name('guarded.index')->middleware('throttle:60,1');

        Artisan::call('route:filter', [
            'pattern' => 'guarded',
            '--middleware' => 'throttle',
            '--json' => true,
        ]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertNotEmpty($decoded);
        $this->assertStringContainsString('throttle', implode(',', $decoded[0]['middleware']));
    }

    public function test_db_seed_partial_runs_a_seeder(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'toolkit-seed-'.uniqid();
        mkdir($dir, 0755, true);

        $file = $dir.DIRECTORY_SEPARATOR.'ToolkitTestSeeder.php';
        file_put_contents($file, <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ToolkitTestSeeder extends Seeder
{
    public static bool $ran = false;

    public function run(): void
    {
        self::$ran = true;
    }
}
PHP);

        require_once $file;

        $this->artisan('db:seed-partial', ['class' => 'Database\Seeders\ToolkitTestSeeder'])
            ->assertSuccessful()
            ->expectsOutputToContain('Running seed')
            ->expectsOutputToContain('Successfully seeded');

        $this->assertTrue(ToolkitTestSeeder::$ran);

        unlink($file);
        rmdir($dir);
    }

    public function test_db_seed_partial_fails_for_an_unknown_class(): void
    {
        $this->artisan('db:seed-partial', ['class' => 'Database\Seeders\DoesNotExist'])
            ->assertFailed()
            ->expectsOutputToContain('Seeding failed');
    }
}
