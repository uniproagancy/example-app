<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportSpreadsheet;
use App\Filament\Resources\Attributes\AttributeResource;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\RelationManagers\ValuesRelationManager;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\SupplierAttributeMaps\SupplierAttributeMapResource;
use App\Filament\Resources\SupplierCategoryMaps\Pages\ManageSupplierCategoryMaps;
use App\Filament\Resources\SupplierCategoryMaps\SupplierCategoryMapResource;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Support\CategoryOptions;
use App\Filament\Support\OptionSearch;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierCategoryMap;
use App\Models\User;
use App\Support\Catalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ImportAdminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_import_pages_render(): void
    {
        $supplier = Supplier::query()->where('code', 'midea')->sole();
        $attribute = Attribute::query()->first();

        foreach ([
            SupplierResource::getUrl(),
            SupplierResource::getUrl('create'),
            SupplierResource::getUrl('edit', ['record' => $supplier]),
            ImportSpreadsheet::getUrl(),
            SupplierCategoryMapResource::getUrl(),
            SupplierAttributeMapResource::getUrl(),
            AttributeResource::getUrl(),
            AttributeResource::getUrl('create'),
            AttributeResource::getUrl('edit', ['record' => $attribute]),
            ProductResource::getUrl('edit', ['record' => Product::query()->first()]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_supplier_rules_and_driver_settings_are_saved_into_config(): void
    {
        $supplier = Supplier::query()->where('code', 'midea')->sole();
        $columns = $supplier->config['columns'];

        Livewire::test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->fillForm([
                'is_active' => true,
                'config.require_stock' => false,
                'config.publish_on_import' => true,
                'config.rate' => 30,
                'config_json' => '{"base_url": "https://www.midea.ge", "from": 5}',
                'markup' => [['up_to' => 200, 'percent' => 25, 'add' => null], ['up_to' => null, 'percent' => null, 'add' => 60]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $supplier->refresh();

        $this->assertTrue($supplier->is_active);
        $this->assertEquals(['base_url' => 'https://www.midea.ge', 'from' => 5, 'rate' => 30, 'require_stock' => false, 'require_image' => false, 'publish_on_import' => true, 'columns' => $columns], $supplier->config);
        $this->assertSame(125.0, $supplier->markup(100));
        $this->assertSame(360.0, $supplier->markup(300));

        Livewire::test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->fillForm(['config.columns' => ['key' => ' c', 'cost' => 'g', 'key_as' => 'url', 'stock' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['key' => 'C', 'cost' => 'G', 'key_as' => 'url'], $supplier->fresh()->config['columns']);

        Livewire::test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->fillForm(['config_json' => '{broken', 'config.columns' => ['cost' => 'G']])
            ->call('save')
            ->assertHasFormErrors(['config_json', 'config.columns']);
    }

    public function test_supplier_category_can_be_mapped_or_turned_into_a_new_category(): void
    {
        $supplier = Supplier::query()->where('code', 'midea')->sole();
        $first = SupplierCategoryMap::query()->create(['supplier_id' => $supplier->id, 'external_name' => 'Air conditioners', 'hits' => 9]);
        $second = SupplierCategoryMap::query()->create(['supplier_id' => $supplier->id, 'external_name' => 'Kettles', 'hits' => 3]);
        $smart = Category::query()->where('code', 'smart')->sole();
        $waiting = Product::query()->where('sku', 'ip16')->sole();
        $waiting->update(['category_id' => null]);
        $waiting->offers()->create(['supplier_id' => $supplier->id, 'external_id' => 'AC-1', 'cost_price' => 900, 'stock' => 1, 'external_category' => 'Air conditioners']);

        Livewire::test(ManageSupplierCategoryMaps::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->call('updateTableColumnState', 'category_id', (string) $first->getKey(), 999999)
            ->call('updateTableColumnState', 'category_id', (string) $first->getKey(), $smart->id)
            ->assertNotified()
            ->callTableAction('create', $second, ['title' => 'ჩაიდნები', 'parent_id' => $smart->id])
            ->assertCanNotSeeTableRecords([$first, $second]);

        $this->assertSame($smart->id, $first->fresh()->category_id);
        $this->assertSame($smart->id, $waiting->fresh()->category_id);

        $created = Category::query()->find($second->fresh()->category_id);
        $this->assertSame($smart->id, $created->parent_id);
        $this->assertSame($supplier->id, $created->created_by_supplier_id);
        $this->assertSame('ჩაიდნები', $created->translate('ka')->title);
        $this->assertSame('chaidnebi', $created->translate('ka')->slug);
    }

    public function test_inline_picker_suggests_categories_by_the_supplier_name_and_searches_all(): void
    {
        $paths = [10 => 'ელექტრონიკა › აქსესუარები › კაბელი', 11 => 'ელექტრონიკა › პროექტორები', 12 => 'სახლი › ჩაიდნები', 13 => 'ელექტრონიკა › კაბელის ადაპტერი'];

        $this->assertSame([10, 13], array_keys(OptionSearch::suggest($paths, 'ვიდეო კაბელები')));
        $this->assertSame([11], array_keys(OptionSearch::suggest($paths, 'პროექტორის ეკრანები')));
        $this->assertSame([], OptionSearch::suggest($paths, 'ოო'));
        $this->assertSame([12], array_keys(OptionSearch::search($paths, 'ჩაიდ')));
        $this->assertSame([10, 13], array_keys(OptionSearch::search($paths, 'ელექტრ კაბ')));

        $supplier = Supplier::query()->where('code', 'midea')->sole();
        $map = SupplierCategoryMap::query()->create(['supplier_id' => $supplier->id, 'external_name' => 'Laptops', 'hits' => 2]);
        $laptops = Category::query()->where('code', 'laptops')->sole();

        $suggested = CategoryOptions::suggest('Laptops');
        $this->assertSame($laptops->id, array_key_first($suggested));

        Livewire::test(ManageSupplierCategoryMaps::class)
            ->assertTableSelectColumnHasOptions('category_id', $suggested, $map);

        $this->assertArrayHasKey($laptops->id, CategoryOptions::search('lapt'));
    }

    public function test_attribute_values_and_product_specs_are_edited_in_admin(): void
    {
        $attribute = Attribute::query()->create(['code' => 'mekhsiereba', 'type' => 'select', 'is_filterable' => true]);
        $attribute->saveTranslations(['ka' => ['name' => 'მეხსიერება'], 'en' => ['name' => 'Storage']]);

        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: ['label_ka' => '512 გბ', 'label_en' => '512 GB'])
            ->assertHasNoTableActionErrors();

        $value = AttributeValue::query()->where('attribute_id', $attribute->id)->sole();
        $this->assertSame('512-gb', $value->code);
        $this->assertSame('512 GB', $value->translate('en')->label);

        $product = Product::query()->where('sku', 'ip16')->sole();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['attributeValues' => [$value->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$value->id], $product->attributeValues()->pluck('attribute_values.id')->all());
        $this->assertSame(['mekhsiereba' => ['512-gb']], Catalog::findBySku('ip16')['filters']);
    }

    public function test_catalog_filters_by_attribute_values(): void
    {
        $this->refreshApplicationWithLocale('en');

        $attribute = Attribute::query()->create(['code' => 'mekhsiereba', 'type' => 'select', 'is_filterable' => true]);
        $attribute->saveTranslations(['ka' => ['name' => 'მეხსიერება'], 'en' => ['name' => 'Storage']]);
        $big = $attribute->values()->create(['code' => '512-gb']);
        $big->saveTranslations(['ka' => ['label' => '512 გბ'], 'en' => ['label' => '512 GB']]);
        $small = $attribute->values()->create(['code' => '128-gb']);
        $small->saveTranslations(['ka' => ['label' => '128 გბ'], 'en' => ['label' => '128 GB']]);

        Product::query()->where('sku', 'ip16')->sole()->attributeValues()->sync([$big->id]);
        Product::query()->where('sku', 's25')->sole()->attributeValues()->sync([$small->id]);
        Catalog::flush();

        $this->get('/en/catalog?all=1')->assertOk()->assertSee('Storage')->assertSee('512 GB');

        $component = Livewire::test('pages::catalog')
            ->call('toggleAttribute', 'mekhsiereba', '512-gb');

        $this->assertSame(['ip16'], $component->get('results')->pluck('sku')->all());

        $component->call('toggleAttribute', 'mekhsiereba', '128-gb');
        $this->assertEqualsCanonicalizing(['ip16', 's25'], $component->get('results')->pluck('sku')->all());

        $this->get('/en/catalog?f[mekhsiereba][0]=128-gb&f[bogus][0]=x')->assertOk()->assertSee('128 GB');

        $laptops = Category::query()->where('code', 'laptops')->sole();
        $attribute->categories()->sync([$laptops->id]);
        Catalog::flush();

        $this->assertTrue(Livewire::test('pages::catalog', ['slug' => 'gaming'])->get('attributeFacets')->isEmpty());
    }
}
