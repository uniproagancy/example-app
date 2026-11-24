<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiltersOffTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_filters_are_listed_first_then_switched_off_with_a_backup_and_switched_back_on(): void
    {
        Storage::fake('local');
        $memory = Attribute::query()->create(['code' => 'test-mekhsiereba', 'type' => 'select', 'is_filterable' => true, 'is_variant' => true, 'sort_order' => 1]);
        $colour = Attribute::query()->create(['code' => 'test-peri', 'type' => 'color', 'is_filterable' => true, 'is_variant' => false, 'sort_order' => 2]);
        $hidden = Attribute::query()->create(['code' => 'test-tsona', 'type' => 'select', 'is_filterable' => false, 'is_variant' => false, 'sort_order' => 3]);
        $switchedOn = Attribute::query()->where('is_filterable', true)->pluck('id')->sort()->values()->all();

        $this->artisan('filters:off')->assertSuccessful();

        $this->assertSame($switchedOn, Attribute::query()->where('is_filterable', true)->pluck('id')->sort()->values()->all());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->artisan('filters:off', ['--apply' => true])->assertSuccessful();

        $this->assertSame(0, Attribute::query()->where('is_filterable', true)->count());
        $this->assertTrue($memory->fresh()->is_variant);
        $this->assertTrue(Catalog::filterAttributes()->isEmpty());

        $backup = Storage::disk('local')->allFiles('filters-backup');
        $this->assertCount(1, $backup);
        $this->assertEqualsCanonicalizing($switchedOn, array_column(json_decode(Storage::disk('local')->get($backup[0]), true), 'id'));

        $this->artisan('filters:off', ['--restore' => basename($backup[0])])->assertSuccessful();

        $this->assertSame($switchedOn, Attribute::query()->where('is_filterable', true)->pluck('id')->sort()->values()->all());
        $this->assertTrue($colour->fresh()->is_filterable);
        $this->assertFalse($hidden->fresh()->is_filterable);

        $this->artisan('filters:off', ['--restore' => 'missing.json'])->assertFailed();
    }
}
