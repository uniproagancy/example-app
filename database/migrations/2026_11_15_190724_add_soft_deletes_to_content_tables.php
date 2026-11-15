<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        'brands', 'categories', 'attributes', 'attribute_values', 'campaigns', 'promos', 'slides', 'faqs', 'menu_items',
        'pages', 'promo_codes', 'deals', 'perks', 'payment_methods', 'suppliers', 'contact_messages', 'users',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
