<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected array $values = [];

    protected function makeVariants(): array
    {
        $colour = Attribute::query()->create(['code' => 'peri', 'type' => 'color', 'is_variant' => true]);
        $colour->saveTranslations(['ka' => ['name' => 'ფერი'], 'en' => ['name' => 'Colour']]);
        $memory = Attribute::query()->create(['code' => 'mekhsiereba', 'type' => 'select', 'is_variant' => true]);
        $memory->saveTranslations(['ka' => ['name' => 'მეხსიერება'], 'en' => ['name' => 'Storage']]);

        foreach ([[$colour, 'shavi', 'შავი', '#111111'], [$colour, 'tetri', 'თეთრი', '#FFFFFF'], [$memory, '256-gb', '256 GB', null], [$memory, '512-gb', '512 GB', null]] as [$attribute, $code, $label, $hex]) {
            $value = $attribute->values()->create(['code' => $code, 'color_hex' => $hex]);
            $value->saveTranslations(['ka' => ['label' => $label], 'en' => ['label' => $label]]);
            $this->values[$code] = $value->id;
        }

        [$black256, $white256, $black512] = Product::query()->whereIn('sku', ['ip16', 's25', 'px'])->orderByRaw("CASE sku WHEN 'ip16' THEN 0 WHEN 's25' THEN 1 ELSE 2 END")->get()->all();

        $black256->attributeValues()->sync([$this->values['shavi'], $this->values['256-gb']]);
        $white256->attributeValues()->sync([$this->values['tetri'], $this->values['256-gb']]);
        $black512->attributeValues()->sync([$this->values['shavi'], $this->values['512-gb']]);
        Product::query()->whereKey([$black256->id, $white256->id, $black512->id])->update(['variant_group' => 'g-phones']);
        Catalog::flush();

        return [$black256, $white256, $black512];
    }

    public function test_switchers_link_to_siblings_that_keep_the_rest_of_the_choice(): void
    {
        [$black256, $white256, $black512] = $this->makeVariants();

        $switchers = collect(Catalog::variantSwitchers(Catalog::find($black256->id)))->keyBy('code');

        $this->assertSame(['peri', 'mekhsiereba'], $switchers->keys()->all());
        $this->assertTrue($switchers['peri']['colour']);
        $this->assertSame(['shavi', 'tetri'], array_column($switchers['peri']['options'], 'code'));
        $this->assertNull($switchers['peri']['options'][0]['url']);
        $this->assertSame(Catalog::find($white256->id)['url'], $switchers['peri']['options'][1]['url']);
        $this->assertSame(Catalog::find($black512->id)['url'], $switchers['mekhsiereba']['options'][1]['url']);

        $fromWhite = collect(Catalog::variantSwitchers(Catalog::find($white256->id)))->keyBy('code');

        $this->assertSame(Catalog::find($black256->id)['url'], $fromWhite['peri']['options'][0]['url']);
        $this->assertFalse($fromWhite->has('mekhsiereba'), 'White comes in 256 GB only, so storage is not a choice there.');

        Attribute::query()->where('code', 'mekhsiereba')->update(['is_variant' => false]);
        Catalog::flush();

        $this->assertSame(['peri'], array_column(Catalog::variantSwitchers(Catalog::find($black256->id)), 'code'));
    }

    public function test_product_page_shows_the_switchers(): void
    {
        $this->refreshApplicationWithLocale('ka');
        [$black256, $white256] = $this->makeVariants();

        $this->get(Catalog::find($black256->id)['url'])
            ->assertOk()
            ->assertSee('ფერი')
            ->assertSee('თეთრი')
            ->assertSee('href="'.Catalog::find($white256->id)['url'].'"', false)
            ->assertSee('512 GB');
    }

    public function test_a_product_whose_other_colours_are_not_on_the_site_still_shows_its_colour(): void
    {
        $this->refreshApplicationWithLocale('ka');
        [$black256, $white256, $black512] = $this->makeVariants();
        Product::query()->whereKey([$white256->id, $black512->id])->update(['variant_group' => null]);
        $black256->update(['variant_group' => 'zoommer-1']);
        Catalog::flush();

        $switchers = collect(Catalog::variantSwitchers(Catalog::find($black256->id)))->keyBy('code');

        $this->assertSame(['peri'], $switchers->keys()->all());
        $this->assertSame(['shavi'], array_column($switchers['peri']['options'], 'code'));
        $this->assertTrue($switchers['peri']['options'][0]['current']);

        $this->get(Catalog::find($black256->id)['url'])
            ->assertOk()
            ->assertSee('ფერი')
            ->assertSee('შავი')
            ->assertSee('#111111', false);
    }

    public function test_admin_links_and_unlinks_variants_from_the_product_form(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $iphone = Product::query()->where('sku', 'ip16')->sole();
        $galaxy = Product::query()->where('sku', 's25')->sole();
        $pixel = Product::query()->where('sku', 'px')->sole();

        Livewire::test(EditProduct::class, ['record' => $iphone->getRouteKey()])
            ->assertSet('data.variant_siblings', [])
            ->set('data.variant_siblings', [$galaxy->id, $pixel->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $group = $iphone->fresh()->variant_group;
        $this->assertNotNull($group);
        $this->assertSame($group, $galaxy->fresh()->variant_group);
        $this->assertSame($group, $pixel->fresh()->variant_group);

        Livewire::test(EditProduct::class, ['record' => $galaxy->getRouteKey()])
            ->assertSet('data.variant_siblings', [$iphone->id, $pixel->id])
            ->set('data.variant_siblings', [$iphone->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($pixel->fresh()->variant_group);
        $this->assertSame($group, $iphone->fresh()->variant_group);

        Livewire::test(EditProduct::class, ['record' => $iphone->getRouteKey()])
            ->set('data.variant_siblings', [])
            ->call('save');

        $this->assertNull($iphone->fresh()->variant_group);
        $this->assertNull($galaxy->fresh()->variant_group);
    }
}
