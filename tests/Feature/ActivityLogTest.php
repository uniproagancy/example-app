<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_staff_changes_are_logged_with_old_and_new_values(): void
    {
        $operator = User::factory()->operator()->create(['name' => 'ოპერატორი ნინო']);
        $product = Product::query()->where('sku', 'ip16')->sole();
        $oldPrice = (float) $product->price;

        $this->actingAs($operator);
        $product->update(['price' => 3333]);

        $log = ActivityLog::query()->sole();

        $this->assertSame($operator->id, $log->user_id);
        $this->assertSame('updated', $log->event);
        $this->assertSame($product->getMorphClass(), $log->subject_type);
        $this->assertSame($product->id, $log->subject_id);
        $this->assertEquals($oldPrice, $log->changes['price']['old']);
        $this->assertEquals(3333, $log->changes['price']['new']);
        $this->assertArrayNotHasKey('updated_at', $log->changes);
        $this->assertStringContainsString('3333', implode(' ', ActivityLogResource::changeLines($log)));
    }

    public function test_customers_imports_and_secrets_are_not_logged(): void
    {
        Product::query()->where('sku', 'ip16')->sole()->update(['price' => 10]);

        $this->actingAs(User::factory()->create());
        Setting::put('site_name', 'X');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $admin->update(['password' => 'new-secret-password', 'name' => 'Boss']);

        $log = ActivityLog::query()->sole();
        $this->assertSame(['name'], array_keys($log->changes));
    }

    public function test_admin_browses_the_log_and_operators_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Product::query()->where('sku', 'ip16')->sole()->update(['price' => 2999]);
        Setting::put('phone', '599 99 88 77');

        $this->get(ActivityLogResource::getUrl())->assertOk();

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords(ActivityLog::query()->get())
            ->filterTable('subject_type', (new Setting)->getMorphClass())
            ->assertCountTableRecords(1)
            ->callTableAction('details', ActivityLog::query()->where('subject_type', (new Setting)->getMorphClass())->sole());

        $this->actingAs(User::factory()->operator()->create());
        $this->get(ActivityLogResource::getUrl())->assertForbidden();
    }
}
