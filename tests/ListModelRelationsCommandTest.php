<?php

namespace Masgeek\ArtisanToolkit\Tests;

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
        eval('namespace App\Models { class RelationsListTestModel extends \Illuminate\Database\Eloquent\Model { public function hasManyRelation(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\Illuminate\Database\Eloquent\Model::class); } public function notARelation(): string { return "hello"; } } }');

        $this->artisan('model:relations', [
            'model' => 'App\Models\RelationsListTestModel',
        ])->assertSuccessful();
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
}
