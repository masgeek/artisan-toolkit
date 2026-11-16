<?php

namespace Masgeek\ArtisanToolkit\Tests;

use Illuminate\Support\Facades\Artisan;

class ListModelRelationsCommandTest extends TestCase
{
    public function test_it_shows_error_for_nonexistent_model(): void
    {
        $this->artisan('model:relations', [
            'model' => 'App\Models\NonExistentModel',
        ])->assertFailed();
    }

    public function test_it_lists_relations_from_model(): void
    {
        eval('namespace App\Models { class RelationsTargetModel extends \Illuminate\Database\Eloquent\Model {} class RelationsListTestModel extends \Illuminate\Database\Eloquent\Model { public function hasManyRelation(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(RelationsTargetModel::class); } public function notARelation(): string { return "hello"; } } }');

        Artisan::call('model:relations', [
            'model' => 'App\Models\RelationsListTestModel',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('hasManyRelation', $output);
        $this->assertStringContainsString('HasMany', $output);
        $this->assertStringContainsString('RelationsTargetModel', $output);
        $this->assertStringNotContainsString('notARelation', $output);
    }

    public function test_it_shows_no_relations_message(): void
    {
        eval('namespace App\Models { class NoRelationListTestModel extends \Illuminate\Database\Eloquent\Model { public function regularMethod(): string { return "hello"; } } }');

        $this->artisan('model:relations', [
            'model' => 'App\Models\NoRelationListTestModel',
        ])->assertSuccessful();
    }

    public function test_it_handles_model_in_base_namespace(): void
    {
        eval('namespace App\Models\Base { class BaseNamespaceModel extends \Illuminate\Database\Eloquent\Model { public function baseRel(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\Illuminate\Database\Eloquent\Model::class); } } }');

        $this->artisan('model:relations', [
            'model' => 'App\Models\Base\BaseNamespaceModel',
        ])->assertSuccessful();
    }

    public function test_it_outputs_json(): void
    {
        eval('namespace App\Models { class JsonTargetModel extends \Illuminate\Database\Eloquent\Model {} class JsonSourceModel extends \Illuminate\Database\Eloquent\Model { public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(JsonTargetModel::class); } } }');

        Artisan::call('model:relations', [
            'model' => 'App\Models\JsonSourceModel',
            '--json' => true,
        ]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame('items', $decoded[0]['name']);
        $this->assertSame('HasMany', $decoded[0]['type']);
        $this->assertSame('JsonTargetModel', $decoded[0]['target']);
    }

    public function test_it_recurses_to_the_requested_depth(): void
    {
        eval('namespace App\Models { class DepthC extends \Illuminate\Database\Eloquent\Model {} class DepthB extends \Illuminate\Database\Eloquent\Model { public function c(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(DepthC::class); } } class DepthA extends \Illuminate\Database\Eloquent\Model { public function b(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(DepthB::class); } } }');

        Artisan::call('model:relations', [
            'model' => 'App\Models\DepthA',
            '--depth' => 2,
            '--json' => true,
        ]);

        $paths = array_column(json_decode(Artisan::output(), true), 'path');

        $this->assertContains('b', $paths);
        $this->assertContains('b.c', $paths);
    }

    public function test_it_resolves_inverse_relations_and_pivot_columns(): void
    {
        eval('namespace App\Models { class InvTagModel extends \Illuminate\Database\Eloquent\Model {} class InvChildModel extends \Illuminate\Database\Eloquent\Model { public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(InvParentModel::class); } } class InvParentModel extends \Illuminate\Database\Eloquent\Model { public function children(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(InvChildModel::class); } public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(InvTagModel::class)->withPivot("role"); } } }');

        Artisan::call('model:relations', [
            'model' => 'App\Models\InvParentModel',
            '--json' => true,
        ]);

        $relations = [];
        foreach (json_decode(Artisan::output(), true) as $relation) {
            $relations[$relation['name']] = $relation;
        }

        $this->assertSame('parent', $relations['children']['inverse']);
        $this->assertSame('role', $relations['tags']['pivot']);
    }
}
