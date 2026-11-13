<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected const MAP = ['confirmed' => 'received', 'shipped' => 'received', 'delivered' => 'completed'];

    public function up(): void
    {
        foreach (self::MAP as $from => $to) {
            DB::table('orders')->where('status', $from)->update(['status' => $to]);
            DB::table('order_events')->where('type', 'status')->where('from', $from)->update(['from' => $to]);
            DB::table('order_events')->where('type', 'status')->where('to', $from)->update(['to' => $to]);
        }
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'completed')->update(['status' => 'delivered']);
        DB::table('orders')->whereIn('status', ['no_answer', 'call_later'])->update(['status' => 'received']);
    }
};
