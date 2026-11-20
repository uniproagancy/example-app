<?php

namespace Tests\Feature;

use App\Console\Commands\DropForeignTranslations;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DropForeignTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function addEnglish(): array
    {
        $product = Product::query()->where('sku', 'ip16')->sole();
        $category = Category::query()->where('code', 'gaming')->sole();

        DB::table('product_translations')->insert(['product_id' => $product->id, 'locale' => 'en', 'name' => 'iPhone 16 Pro', 'slug' => 'apple-iphone-16-pro-en']);
        DB::table('category_translations')->insert(['category_id' => $category->id, 'locale' => 'en', 'title' => 'Gaming', 'slug' => 'gaming-en']);

        return [
            'product_translations' => DB::table('product_translations')->where('locale', 'en')->get()->map(fn ($row) => (array) $row)->all(),
            'category_translations' => DB::table('category_translations')->where('locale', 'en')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    public function test_without_force_nothing_is_deleted(): void
    {
        Storage::fake('local');
        $this->addEnglish();
        $georgian = DB::table('product_translations')->where('locale', 'ka')->count();

        $this->artisan('translations:drop-foreign')->expectsOutputToContain('Nothing was deleted')->assertSuccessful();

        $this->assertSame(1, DB::table('product_translations')->where('locale', 'en')->count());
        $this->assertSame($georgian, DB::table('product_translations')->where('locale', 'ka')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_force_deletes_only_foreign_rows_and_undo_puts_them_back_exactly(): void
    {
        Storage::fake('local');
        $before = $this->addEnglish();
        $georgian = DB::table('product_translations')->where('locale', 'ka')->count();

        $foreign = collect(DropForeignTranslations::TABLES)->sum(fn (string $table) => DB::table($table)->where('locale', '!=', 'ka')->count());

        $this->artisan('translations:drop-foreign', ['--force' => true])->expectsOutputToContain("Deleted {$foreign} rows")->assertSuccessful();

        foreach (DropForeignTranslations::TABLES as $table) {
            $this->assertFalse(DB::table($table)->where('locale', '!=', 'ka')->exists(), $table);
        }

        $this->assertFalse(DB::table('product_translations')->where('locale', 'en')->exists());
        $this->assertFalse(DB::table('category_translations')->where('locale', 'en')->exists());
        $this->assertSame($georgian, DB::table('product_translations')->where('locale', 'ka')->count());

        $backup = Storage::disk('local')->files('translation-backups');
        $this->assertCount(1, $backup);

        $this->artisan('translations:drop-foreign', ['--undo' => $backup[0]])->expectsOutputToContain("Restored {$foreign} rows")->assertSuccessful();

        $this->assertEquals($before['product_translations'], DB::table('product_translations')->where('locale', 'en')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertEquals($before['category_translations'], DB::table('category_translations')->where('locale', 'en')->get()->map(fn ($row) => (array) $row)->all());

        $this->artisan('translations:drop-foreign', ['--undo' => '../.env'])->assertFailed();
    }
}
