<?php

namespace Tests\Feature;

use App\Filament\Pages\SupplierProducts;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierProductsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function supplier(string $code): Supplier
    {
        return Supplier::query()->create(['code' => $code, 'name' => ucfirst($code), 'driver' => 'none', 'is_active' => true, 'priority' => 10, 'config' => []]);
    }

    public function test_products_are_listed_by_supplier_and_by_the_day_they_were_added(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $zoommer = $this->supplier('zoommer-test');
        $alta = $this->supplier('alta-test');

        $phone = Product::query()->where('sku', 'ip16')->sole();
        $speaker = Product::query()->where('sku', 'jbl')->sole();
        $manual = Product::query()->doesntHave('offers')->whereKeyNot([$phone->id, $speaker->id])->firstOrFail();

        $phone->offers()->create(['supplier_id' => $zoommer->id, 'external_id' => 'Z1', 'cost_price' => 100, 'stock' => 1]);
        $speaker->offers()->create(['supplier_id' => $alta->id, 'external_id' => 'A1', 'cost_price' => 100, 'stock' => 1]);
        Product::query()->toBase()->update(['created_at' => now()->subDays(40)]);
        Product::query()->whereKey($phone->id)->toBase()->update(['created_at' => now()]);
        Product::query()->whereKey([$speaker->id, $manual->id])->toBase()->update(['created_at' => now()->subDays(10)]);

        $this->get(SupplierProducts::getUrl())->assertOk()->assertSee(__('admin.supplier_products.label'))->assertSee('Zoommer-test');

        $page = Livewire::test(SupplierProducts::class)
            ->assertCanSeeTableRecords([$phone, $speaker, $manual]);

        $page->call('showSupplier', (string) $zoommer->id)
            ->assertCanSeeTableRecords([$phone])
            ->assertCanNotSeeTableRecords([$speaker, $manual]);

        $page->call('showSupplier', SupplierProducts::MANUAL)
            ->assertCanSeeTableRecords([$manual])
            ->assertCanNotSeeTableRecords([$phone, $speaker]);

        $page->call('showSupplier', '')
            ->filterTable('period', 'today')
            ->assertCanSeeTableRecords([$phone])
            ->assertCanNotSeeTableRecords([$speaker, $manual]);

        Livewire::test(SupplierProducts::class)
            ->filterTable('created_between', ['from' => now()->subDays(11)->toDateString(), 'until' => now()->subDays(9)->toDateString()])
            ->assertCanSeeTableRecords([$speaker, $manual])
            ->assertCanNotSeeTableRecords([$phone]);
    }

    public function test_operators_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->operator()->create());

        $this->get(SupplierProducts::getUrl())->assertForbidden();
    }
}
