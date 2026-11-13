<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperatorRoleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function operator(): User
    {
        $operator = User::factory()->operator()->create();
        $this->actingAs($operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $operator;
    }

    public function test_operators_reach_only_orders_and_the_product_list(): void
    {
        $this->operator();
        $product = Product::query()->firstOrFail();

        $this->get('/admin')->assertForbidden();
        $this->get(OrderResource::getUrl())->assertOk()->assertDontSee(__('admin.settings.label'));
        $this->get(ProductResource::getUrl())->assertOk();
        $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertForbidden();
        $this->get(ProductResource::getUrl('create'))->assertForbidden();

        foreach (['/admin/settings', '/admin/categories', '/admin/users', '/admin/slides', '/admin/suppliers', '/admin/promo-codes'] as $path) {
            $this->get($path)->assertForbidden();
        }

        $this->assertSame(OrderResource::getUrl(), Filament::getHomeUrl());
    }

    public function test_operators_can_change_only_the_price_of_a_product(): void
    {
        $this->operator();
        $product = Product::query()->firstOrFail();
        $product->update(['stock' => 7, 'is_active' => true]);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('edit', $product)
            ->assertTableActionHidden('delete', $product)
            ->assertTableBulkActionHidden('bulkEdit')
            ->assertTableBulkActionHidden('delete')
            ->callTableAction('editPrice', $product, ['price' => 123.45, 'old_price' => null, 'stock' => 0])
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertEquals(123.45, $product->price);
        $this->assertSame(7, $product->stock);
        $this->assertTrue($product->is_active);
    }

    public function test_admins_can_make_a_user_an_operator_who_then_gets_into_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        Livewire::test(ManageUsers::class)
            ->callTableAction('edit', $user, ['is_operator' => true])
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->isOperator());
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse(User::factory()->admin()->operator()->make()->isOperator());
        $this->get('/admin')->assertOk();
    }
}
