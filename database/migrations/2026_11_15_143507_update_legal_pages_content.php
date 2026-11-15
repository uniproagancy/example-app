<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $content = PageSeeder::content();

        foreach (PageSeeder::LEGAL_PAGES as $slug) {
            $page = Page::withoutGlobalScopes()->firstOrCreate(['slug' => $slug], [
                'is_active' => true,
                'in_footer' => true,
                'position' => (int) Page::withoutGlobalScopes()->max('position') + 1,
            ]);

            $page->saveTranslations($content[$slug]);
            $page->touch();
        }
    }

    public function down(): void {}
};
