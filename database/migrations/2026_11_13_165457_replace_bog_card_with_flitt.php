<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $retired = DB::table('payment_methods')->where('driver', 'bog-card')->get();

        if (! DB::table('payment_methods')->where('code', 'card')->exists()) {
            $id = DB::table('payment_methods')->insertGetId([
                'code' => 'card',
                'driver' => 'flitt',
                'installment_type' => null,
                'logo' => null,
                'is_active' => true,
                'is_online' => true,
                'on_quick_order' => (bool) ($retired->first()->on_quick_order ?? true),
                'min_total' => null,
                'max_total' => null,
                'sort_order' => (int) ($retired->min('sort_order') ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_method_translations')->insert([
                ['payment_method_id' => $id, 'locale' => 'ka', 'name' => 'ბარათით გადახდა', 'note' => 'Visa, Mastercard — უსაფრთხო გადახდა Flitt-ით'],
                ['payment_method_id' => $id, 'locale' => 'en', 'name' => 'Card payment', 'note' => 'Visa, Mastercard — secure payment with Flitt'],
            ]);
        }

        foreach ($retired as $method) {
            if (DB::table('orders')->where('payment_method', $method->code)->exists()) {
                DB::table('payment_methods')->where('id', $method->id)->update(['driver' => null, 'is_online' => false, 'is_active' => false, 'updated_at' => now()]);
            } else {
                DB::table('payment_methods')->where('id', $method->id)->delete();
            }
        }
    }

    public function down(): void {}
};
