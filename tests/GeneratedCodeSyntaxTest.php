<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\File;

/**
 * Regression guard: every generator must emit syntactically valid PHP.
 *
 * Heredoc interpolation bugs in stubs previously produced files containing
 * `$this->(` and classes extending unimported base classes, neither of which
 * is caught by a file-existence assertion.
 */
class GeneratedCodeSyntaxTest extends TestCase
{
    /** @var list<string> */
    private array $directories = ['Enums', 'Repositories', 'Services', 'DTOs', 'Http'];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            File::deleteDirectory(app_path($directory));
        }

        parent::tearDown();
    }

    public function test_all_generators_emit_valid_php(): void
    {
        $this->artisan('make:enum', [
            'name' => 'UserRole',
            '--backed' => 'string',
            '--cases' => 'Admin,Partner',
        ])->assertSuccessful();

        $this->artisan('make:repo', ['name' => 'PostRepo', '--model' => 'Post'])->assertSuccessful();
        $this->artisan('make:service', ['name' => 'Billing'])->assertSuccessful();
        $this->artisan('make:dto', ['name' => 'UserData'])->assertSuccessful();
        $this->artisan('make:api-scaffold', ['name' => 'Currency', '--no-route' => true])->assertSuccessful();
        $this->artisan('make:resource-full', ['name' => 'PostResource', '--model' => 'Post'])->assertSuccessful();

        $failures = [];

        foreach ($this->generatedFiles() as $file) {
            $output = [];
            $code = 0;
            exec('php -l '.escapeshellarg($file).' 2>&1', $output, $code);

            if ($code !== 0) {
                $failures[] = $file."\n".implode("\n", $output);
            }
        }

        $this->assertSame([], $failures, "Generated invalid PHP:\n".implode("\n", $failures));
    }

    public function test_generated_classes_import_their_base_classes(): void
    {
        $this->artisan('make:api-scaffold', ['name' => 'Currency', '--no-route' => true])->assertSuccessful();

        $repo = File::get(app_path('Repositories/CurrencyRepo.php'));

        // A bare `extends BaseRepo` with no import produces an undefined class.
        $this->assertStringContainsString('use App\Repositories\BaseRepo;', $repo);
        $this->assertStringContainsString('class CurrencyRepo extends BaseRepo', $repo);

        $resource = File::get(app_path('Http/Resources/CurrencyResource.php'));
        $this->assertStringContainsString('use Illuminate\Http\Resources\Json\JsonResource;', $resource);
        $this->assertStringContainsString('class CurrencyResource extends JsonResource', $resource);
    }

    public function test_generated_controller_uses_a_usable_variable_reference(): void
    {
        $this->artisan('make:api-scaffold', ['name' => 'Currency', '--no-route' => true])->assertSuccessful();

        $controller = File::get(app_path('Http/Controllers/Api/CurrencyController.php'));

        // `$this->$repoVar->method()` parses as property access on the string
        // variable and silently emitted `$this->(` in generated output.
        $this->assertStringNotContainsString('$this->(', $controller);
        $this->assertStringContainsString('$this->currencyRepo->paginateWithSort(', $controller);
    }

    /**
     * @return list<string>
     */
    private function generatedFiles(): array
    {
        $files = [];

        foreach ($this->directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(app_path($directory), \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
