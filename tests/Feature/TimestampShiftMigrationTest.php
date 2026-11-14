<?php

namespace Tests\Feature;

use App\Models\AbandonedCart;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimestampShiftMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_timestamps_move_four_hours_forward_and_back(): void
    {
        $order = Order::query()->create([
            'number' => 'EL-123456', 'status' => 'new', 'first_name' => 'ნინო', 'phone' => '555123456',
            'payment_method' => 'cash', 'payment_status' => Order::PAYMENT_PENDING,
            'subtotal' => 10, 'discount' => 0, 'shipping' => 0, 'total' => 10, 'locale' => 'ka',
        ]);
        DB::table('orders')->where('id', $order->id)->update(['created_at' => '2026-11-10 08:30:00', 'paid_at' => null]);
        $cart = AbandonedCart::factory()->create();
        $cartTime = DB::table('abandoned_carts')->where('id', $cart->id)->value('created_at');

        $migration = require database_path('migrations/2026_11_15_010408_shift_existing_timestamps_to_tbilisi_time.php');

        $migration->up();

        $row = DB::table('orders')->where('id', $order->id)->first();
        $this->assertSame('2026-11-10 12:30:00', $row->created_at);
        $this->assertNull($row->paid_at);
        $this->assertSame($cartTime, DB::table('abandoned_carts')->where('id', $cart->id)->value('created_at'));

        $migration->down();

        $this->assertSame('2026-11-10 08:30:00', DB::table('orders')->where('id', $order->id)->value('created_at'));
    }
}
