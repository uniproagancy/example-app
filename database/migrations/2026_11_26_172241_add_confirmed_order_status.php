<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'confirmed']);

        $this->remapStatusSms(['received' => 'confirmed', 'shipped' => 'confirmed']);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'confirmed')->update(['status' => 'shipped']);

        $this->remapStatusSms(['confirmed' => 'received']);
    }

    /**
     * @param  array<string, string>  $map
     */
    protected function remapStatusSms(array $map): void
    {
        $value = DB::table('settings')->where('key', 'status_sms')->value('value');

        if (blank($value)) {
            return;
        }

        $statuses = collect(explode(',', (string) $value))
            ->map(fn (string $status) => $map[trim($status)] ?? trim($status))
            ->filter()
            ->unique()
            ->implode(',');

        DB::table('settings')->where('key', 'status_sms')->update(['value' => $statuses]);
    }
};
