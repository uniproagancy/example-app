<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Catalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function category(string $title, ?Category $parent = null): Category
    {
        $category = Category::query()->create(['parent_id' => $parent?->id, 'is_active' => true, 'position' => 99]);
        $category->saveTranslations([
            'ka' => ['title' => $title, 'slug' => 'ka-'.$title],
            'en' => ['title' => $title, 'slug' => 'en-'.$title],
        ]);

        return $category;
    }

    public function test_selected_products_are_deleted_together(): void
    {
        $iphone = Product::query()->where('sku', 'ip16')->sole();
        $console = Product::query()->where('sku', 'ps5')->sole();
        $kept = Product::query()->where('sku', 's25')->sole();
        $iphone->offers()->create(['supplier_id' => Supplier::query()->value('id'), 'external_id' => 'X1', 'cost_price' => 100, 'stock' => 1]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('delete', [$iphone, $console])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertModelMissing($iphone);
        $this->assertModelMissing($console);
        $this->assertModelExists($kept);
        $this->assertNull(Catalog::findBySku('ip16'));
        $this->assertNotNull(Catalog::findBySku('s25'));
    }

    public function test_only_empty_categories_are_deleted_and_a_selected_branch_goes_together(): void
    {
        $parent = $this->category('ცარიელი მშობელი');
        $child = $this->category('ცარიელი შვილი', $parent);
        $grandchild = $this->category('ცარიელი შვილიშვილი', $child);
        $withProducts = Product::query()->where('sku', 'ip16')->sole()->category;
        $withChildren = $this->category('მშობელი მოუნიშნავი შვილით');
        $unselectedChild = $this->category('მოუნიშნავი შვილი', $withChildren);

        Livewire::test(ListCategories::class)
            ->callTableBulkAction('delete', [$parent, $grandchild, $child, $withProducts, $withChildren])
            ->assertNotified();

        $this->assertModelMissing($parent);
        $this->assertModelMissing($child);
        $this->assertModelMissing($grandchild);
        $this->assertModelExists($withProducts);
        $this->assertModelExists($withChildren);
        $this->assertModelExists($unselectedChild);
    }
}
