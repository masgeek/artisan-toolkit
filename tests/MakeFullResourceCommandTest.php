<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MakeFullResourceCommandTest extends TestCase
{
    private string $resourcesPath;
    private string $collectionsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resourcesPath = app_path('Http/Resources');
        $this->collectionsPath = app_path('Http/Resources/Collections');

        File::deleteDirectory($this->resourcesPath);
        File::makeDirectory($this->resourcesPath, 0755, true);
        File::makeDirectory($this->collectionsPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('Http/Resources'));

        Schema::dropIfExists('coverage_test_models');
        Schema::dropIfExists('coverage_rel_test_models');
        Schema::dropIfExists('coverage_rel_skip_models');

        parent::tearDown();
    }

    public function test_it_creates_resource_and_collection(): void
    {
        $this->artisan('make:resource-full', ['name' => 'UserResource'])
            ->assertSuccessful();

        $this->assertFileExists($this->resourcesPath . '/UserResource.php');
        $this->assertFileExists($this->collectionsPath . '/UserResourceCollection.php');

        $resourceContent = File::get($this->resourcesPath . '/UserResource.php');
        $this->assertStringContainsString('class UserResource extends \Illuminate\Http\Resources\Json\JsonResource', $resourceContent);

        $collectionContent = File::get($this->collectionsPath . '/UserResourceCollection.php');
        $this->assertStringContainsString('class UserResourceCollection', $collectionContent);
    }

    public function test_it_infers_model_from_resource_name(): void
    {
        $this->artisan('make:resource-full', ['name' => 'UserResource'])
            ->assertSuccessful();

        $content = File::get($this->resourcesPath . '/UserResource.php');
        $this->assertStringContainsString('@var User', $content);
    }

    public function test_it_creates_resource_with_explicit_model(): void
    {
        $this->artisan('make:resource-full', [
            'name' => 'UserResource',
            '--model' => 'Admin',
        ])->assertSuccessful();

        $content = File::get($this->resourcesPath . '/UserResource.php');
        $this->assertStringContainsString('@var Admin', $content);
        $this->assertStringContainsString('use App\Models\Admin;', $content);
    }

    public function test_it_fails_when_resource_already_exists(): void
    {
        File::put($this->resourcesPath . '/UserResource.php', '<?php');

        $this->artisan('make:resource-full', ['name' => 'UserResource'])
            ->assertFailed();
    }

    public function test_it_fails_when_collection_already_exists(): void
    {
        File::put($this->collectionsPath . '/UserResourceCollection.php', '<?php');

        $this->artisan('make:resource-full', ['name' => 'UserResource'])
            ->assertFailed();
    }

    public function test_it_generates_fields_from_existing_model(): void
    {
        Schema::create('coverage_test_models', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        eval('namespace App\Models { class CoverageTestModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "coverage_test_models";
        }}');

        $this->artisan('make:resource-full', [
            'name' => 'CoverageResource',
            '--model' => 'CoverageTestModel',
        ])->assertSuccessful();

        $content = File::get($this->resourcesPath . '/CoverageResource.php');
        $this->assertStringContainsString("'id' => \$model->id,", $content);
        $this->assertStringContainsString("'name' => \$model->name,", $content);
        $this->assertStringContainsString("'email' => \$model->email,", $content);
        $this->assertStringContainsString("'created_at' => \$model->created_at,", $content);
        $this->assertStringContainsString("'updated_at' => \$model->updated_at,", $content);
    }

    public function test_it_falls_back_to_placeholder_when_model_missing(): void
    {
        $this->artisan('make:resource-full', [
            'name' => 'MissingModelResource',
            '--model' => 'NonExistentCoverageModel',
        ])->assertSuccessful();

        $content = File::get($this->resourcesPath . '/MissingModelResource.php');
        $this->assertStringContainsString("// 'id' => \$model->id,", $content);
        $this->assertStringContainsString("// 'name' => \$model->name,", $content);
    }

    public function test_it_generates_related_resources_with_relationships(): void
    {
        Schema::create('coverage_rel_test_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        eval('namespace App\Models { class CoverageRelModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "coverage_rel_test_models";
            public function relPosts(): \Illuminate\Database\Eloquent\Relations\HasMany {
                return $this->hasMany(\App\Models\CoverageRelModel::class, "coverage_rel_test_models");
            }
            public function relProfile(): \Illuminate\Database\Eloquent\Relations\HasOne {
                return $this->hasOne(\App\Models\CoverageRelModel::class, "coverage_rel_test_models");
            }
        }}');

        $this->artisan('make:resource-full', [
            'name' => 'RelModelResource',
            '--model' => 'CoverageRelModel',
            '--with-relationships' => true,
        ])->assertSuccessful();

        $content = File::get($this->resourcesPath . '/RelModelResource.php');
        $this->assertStringContainsString("'id' => \$model->id,", $content);
        $this->assertStringContainsString('use App\Http\Resources\RelPostResource;', $content);
        $this->assertStringContainsString('use App\Http\Resources\RelProfileResource;', $content);
        $this->assertStringContainsString('RelPostResource::collection($model->relPosts),', $content);
        $this->assertStringContainsString('new RelProfileResource($model->relProfile),', $content);

        $this->assertFileExists($this->resourcesPath . '/RelPostResource.php');
        $this->assertFileExists($this->resourcesPath . '/RelProfileResource.php');
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_it_skips_existing_related_resource(): void
    {
        Schema::create('coverage_rel_skip_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        File::put($this->resourcesPath . '/SkipPostResource.php', '<?php');

        eval('namespace App\Models { class CoverageRelSkipModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "coverage_rel_skip_models";
            public function skipPosts(): \Illuminate\Database\Eloquent\Relations\HasMany {
                return $this->hasMany(\App\Models\CoverageRelSkipModel::class, "coverage_rel_skip_models");
            }
            public function skipTags(): \Illuminate\Database\Eloquent\Relations\HasMany {
                return $this->hasMany(\App\Models\CoverageRelSkipModel::class, "coverage_rel_skip_models");
            }
        }}');

        $this->artisan('make:resource-full', [
            'name' => 'SkipModelResource',
            '--model' => 'CoverageRelSkipModel',
            '--with-relationships' => true,
        ])->assertSuccessful();

        $this->assertStringEqualsStringIgnoringLineEndings(
            '<?php',
            File::get($this->resourcesPath . '/SkipPostResource.php')
        );

        $this->assertFileExists($this->resourcesPath . '/SkipTagResource.php');
    }

    public function test_it_falls_back_to_placeholder_when_model_table_missing(): void
    {
        eval('namespace App\Models { class FullResourceNoTableModel extends \Illuminate\Database\Eloquent\Model {
            protected $table = "nonexistent_full_resource_table";
        }}');

        $this->artisan('make:resource-full', [
            'name' => 'NoTableResource',
            '--model' => 'FullResourceNoTableModel',
        ])->assertSuccessful();

        $content = File::get($this->resourcesPath . '/NoTableResource.php');
        $this->assertStringContainsString("// 'id' => \$model->id,", $content);
        $this->assertStringContainsString("// 'name' => \$model->name,", $content);
    }
}
