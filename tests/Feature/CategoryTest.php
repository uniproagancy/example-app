<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Support\Catalog;
use App\Support\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_seeder_builds_a_three_level_tree_with_unique_slugs(): void
    {
        $this->assertSame(8, Category::query()->roots()->count());
        $this->assertTrue(Category::query()->whereHas('parent', fn ($query) => $query->whereNotNull('parent_id'))->exists());

        $slugs = CategoryTranslation::query()->where('locale', 'ka')->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count());
        $this->assertFalse(CategoryTranslation::query()->where('locale', 'en')->exists());

        $gaming = Category::query()->where('code', 'gaming')->first();
        $this->assertSame('gaming', $gaming->translation('ka')->slug);
    }

    public function test_home_page_lists_only_root_categories_with_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $response = $this->get('/ka')->assertOk();
        $products = Catalog::products();

        CategoryTree::roots()->each(function (array $root) use ($response, $products) {
            if ($products->contains('cat', $root['code'])) {
                $response->assertSee('id="sec-'.$root['code'].'"', false)
                    ->assertSee(url('/ka/categories/'.$root['slug']), false);
            } else {
                $response->assertDontSee('id="sec-'.$root['code'].'"', false);
            }
        });

        $response->assertDontSee('id="sec-small"', false)
            ->assertDontSee('id="sec-acc"', false)
            ->assertDontSee(url('/ka/categories/coffee-machines'), false);
    }

    public function test_category_changes_in_database_show_on_the_site(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $gaming = Category::query()->where('code', 'gaming')->first();
        $gaming->translations()->where('locale', 'ka')->first()->update(['title' => 'გეიმინგის ზონა']);

        Category::query()->where('code', 'photo')->first()->update(['is_active' => false]);

        $this->get('/ka')
            ->assertOk()
            ->assertSee('გეიმინგის ზონა')
            ->assertDontSee('id="sec-photo"', false);
    }

    public function test_category_page_shows_subcategories_and_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/categories/gaming')
            ->assertOk()
            ->assertSee('<title>გეიმინგი — ELIO.GE</title>', false)
            ->assertSee('კონსოლები')
            ->assertSee('ჯოისტიკები')
            ->assertSee('Xbox Series X, 1 TB')
            ->assertDontSee('hreflang="en"', false);
    }

    public function test_subcategory_page_shows_breadcrumb_and_filtered_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $ids = Product::query()->whereIn('sku', ['xsx', 'ds'])->pluck('id', 'sku');

        $this->get('/ka/categories/consoles')
            ->assertOk()
            ->assertSee(url('/ka/categories/gaming'), false)
            ->assertSee('wire:key="category-product-'.$ids['xsx'].'"', false)
            ->assertDontSee('wire:key="category-product-'.$ids['ds'].'"', false);
    }

    public function test_slug_from_other_language_redirects_and_unknown_slug_is_404(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka/categories/gaming')->assertOk();
        $this->get('/ka/kategoria/geimingi')->assertNotFound();
        $this->get('/ka/categories/does-not-exist')->assertNotFound();
    }
}
