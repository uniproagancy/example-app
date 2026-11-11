<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('driver', 32)->nullable();
            $table->string('installment_type', 16)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->boolean('on_quick_order')->default(true);
            $table->decimal('min_total', 10, 2)->nullable();
            $table->decimal('max_total', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('payment_method_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->string('note')->nullable();

            $table->unique(['payment_method_id', 'locale']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 32);
            $table->string('external_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->unsignedTinyInteger('months')->nullable();
            $table->string('status', 16)->default('pending');
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('redirect_url', 2048)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['driver', 'status']);
            $table->index('external_id');
        });

        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 40);
            $table->string('method', 8);
            $table->string('path');
            $table->json('query')->nullable();
            $table->json('headers')->nullable();
            $table->mediumText('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 24)->nullable();
            $table->boolean('confirmed')->nullable();
            $table->string('status_before', 24)->nullable();
            $table->string('status_after', 24)->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['driver', 'created_at']);
            $table->index('outcome');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 16)->default('pending')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->unsignedTinyInteger('installment_months')->nullable()->after('paid_at');

            $table->index('payment_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 40)->change();
        });

        foreach ([
            ['card', 'bog', 'bog_card'],
            ['card', 'tbc', 'bog_card'],
            ['card', 'credo', 'bog_card'],
            ['card', 'other', 'bog_card'],
            ['card', null, 'bog_card'],
            ['installment', 'bog', 'bog_installment'],
            ['installment', 'tbc', 'tbc_installment'],
            ['installment', 'credo', 'credo_installment'],
            ['installment', null, 'bog_installment'],
        ] as [$old, $bank, $new]) {
            DB::table('orders')
                ->where('payment_method', $old)
                ->when($bank === null, fn ($query) => $query->whereNull('bank'), fn ($query) => $query->where('bank', $bank))
                ->update(['payment_method' => $new]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'paid_at', 'installment_months']);
        });

        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_method_translations');
        Schema::dropIfExists('payment_methods');
    }
};
