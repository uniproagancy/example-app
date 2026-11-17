<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Models\User;
use App\Services\Import\Drivers\Alta\AltaDriver;
use App\Services\Import\Drivers\Alta\AltaStockSynchronizer;
use App\Services\Import\Drivers\Alta\AltaUnavailable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AltaBrowserFeedTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function alta(): Supplier
    {
        $supplier = Supplier::query()->where('code', 'alta')->sole();
        $supplier->update(['driver' => AltaDriver::class, 'is_active' => true, 'config' => ['min_quantity' => 2]]);

        return $supplier;
    }

    protected function stock(Supplier $supplier, string $code, int $quantity, ?DateTimeInterface $syncedAt = null): void
    {
        SupplierStock::query()->create([
            'supplier_id' => $supplier->id,
            'external_id' => $code,
            'quantity' => $quantity,
            'synced_at' => $syncedAt ?? now(),
        ]);
    }

    public function test_admin_gets_the_b2b_list_with_the_products_already_on_the_site_marked(): void
    {
        $alta = $this->alta();
        $this->stock($alta, 'HELD-1', 5);
        $this->stock($alta, 'NEW-1', 3);
        $this->stock($alta, 'LOW-1', 1);
        $this->stock($alta, 'მშრალი ყინვით No Frost', 9);
        Product::query()->first()->offers()->create(['supplier_id' => $alta->id, 'external_id' => 'HELD-1', 'cost_price' => 100, 'stock' => 5]);

        $this->mock(AltaStockSynchronizer::class, fn (MockInterface $mock) => $mock->shouldNotReceive('sync'));

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/admin/alta-browser/feed')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('minimum_quantity', 2)
            ->assertJsonPath('items', [
                ['item' => 'HELD-1', 'quantity' => 5, 'on_site' => true],
                ['item' => 'NEW-1', 'quantity' => 3, 'on_site' => false],
            ]);
    }

    public function test_a_stale_list_is_refreshed_from_alta_before_it_is_sent(): void
    {
        $alta = $this->alta();
        $this->stock($alta, 'OLD-1', 4, now()->subDays(3));

        $this->mock(AltaStockSynchronizer::class, fn (MockInterface $mock) => $mock->shouldReceive('sync')->once()->andReturnUsing(function (Supplier $supplier) {
            SupplierStock::query()->where('supplier_id', $supplier->id)->update(['quantity' => 7, 'synced_at' => now()]);

            return [];
        }));

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/admin/alta-browser/feed')
            ->assertOk()
            ->assertJsonPath('items.0', ['item' => 'OLD-1', 'quantity' => 7, 'on_site' => false]);
    }

    public function test_a_stale_list_that_cannot_be_refreshed_is_refused(): void
    {
        $alta = $this->alta();
        $this->stock($alta, 'OLD-1', 4, now()->subDays(3));

        $this->mock(AltaStockSynchronizer::class, fn (MockInterface $mock) => $mock->shouldReceive('sync')->once()->andThrow(new AltaUnavailable('down')));

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/admin/alta-browser/feed')
            ->assertStatus(503)
            ->assertJsonPath('error', 'stock_snapshot_stale');
    }

    public function test_only_signed_in_admins_get_the_list(): void
    {
        $this->alta();

        $this->getJson('/admin/alta-browser/feed')->assertUnauthorized();

        $this->actingAs(User::factory()->operator()->create())
            ->getJson('/admin/alta-browser/feed')
            ->assertForbidden();
    }
}
