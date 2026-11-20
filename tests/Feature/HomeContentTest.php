<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Deal;
use App\Models\HomeBlock;
use App\Models\Perk;
use App\Models\Product;
use App\Models\Promo;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeContentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_home_page_renders_sections_from_the_database(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka')
            ->assertOk()
            ->assertSee('მიწოდება მთელ საქართველოში')
            ->assertDontSee('უფასო მიწოდება თბილისში')
            ->assertDontSee('14 დღიანი დაბრუნება')
            ->assertDontSee('0% განვადება')
            ->assertSee('დღის შეთავაზება')
            ->assertSee('Galaxy Tab S10 FE, 128 GB')
            ->assertSee('1 099 ₾')
            ->assertSee('1 549 ₾')
            ->assertSee('აუდიო კვირეული')
            ->assertSee(url('/ka/categories/headphones'), false)
            ->assertDontSee('ოფიციალური დისტრიბუცია')
            ->assertSee('Samsung')
            ->assertDontSee('09 ·');
    }

    public function test_promo_banners_sit_between_category_sections(): void
    {
        $this->refreshApplicationWithLocale('ka');
        Promo::query()->where('is_active', true)->get()->slice(2)->each->delete();

        while (Promo::query()->where('is_active', true)->count() < 3) {
            Promo::query()->create(['title' => ['ka' => 'ჭკვიანი სახლი'], 'theme' => 'field', 'icon' => 'home', 'position' => 99, 'is_active' => true]);
        }

        $html = $this->get('/ka')->assertOk()->getContent();
        $sections = $this->positions($html, 'wire:key="section-');
        $banners = $this->positions($html, 'data-promo-group');

        $this->assertGreaterThanOrEqual(4, count($sections));
        $this->assertCount(2, $banners, 'One wide banner, then a pair.');
        $this->assertTrue($sections[1] < $banners[0] && $banners[0] < $sections[2], 'The first banner follows the second category.');
        $this->assertTrue($sections[3] < $banners[1] && (! isset($sections[4]) || $banners[1] < $sections[4]), 'The pair follows the fourth category.');
        $this->assertSame(1, substr_count($html, 'აუდიო კვირეული'));

        HomeBlock::query()->where('key', 'categories')->update(['is_active' => false]);

        $this->assertCount(2, $this->positions($this->get('/ka')->assertOk()->getContent(), 'data-promo-group'), 'Without categories the banners keep their own place.');
    }

    /**
     * @return array<int, int>
     */
    protected function positions(string $html, string $needle): array
    {
        $found = [];
        $offset = 0;

        while (($at = strpos($html, $needle, $offset)) !== false) {
            $found[] = $at;
            $offset = $at + 1;
        }

        return $found;
    }

    public function test_blocks_follow_database_order_and_visibility(): void
    {
        $this->refreshApplicationWithLocale('ka');

        HomeBlock::query()->where('key', 'brands')->first()->update(['position' => 0]);
        HomeBlock::query()->where('key', 'perks')->first()->update(['is_active' => false]);

        $this->get('/ka')
            ->assertOk()
            ->assertDontSee('მიწოდება მთელ საქართველოში')
            ->assertSeeInOrder(['id="brands"', 'დღის შეთავაზება', 'აუდიო კვირეული'], false);
    }

    public function test_content_changes_in_database_show_on_the_site(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Perk::query()->first()->update(['title' => ['ka' => 'უფასო მიწოდება ყველგან']]);
        Promo::query()->first()->update(['ends_at' => now()->subMinute()]);
        Brand::query()->where('name', 'Samsung')->first()->update(['show_on_home' => false]);

        $this->get('/ka')
            ->assertOk()
            ->assertSee('უფასო მიწოდება ყველგან')
            ->assertDontSee('აუდიო კვირეული')
            ->assertSee('სმარტ სახლის კვირა')
            ->assertDontSee('<span class="font-display text-lg leading-none font-extrabold tracking-[-0.6px] sm:text-2xl">Samsung</span>', false);
    }

    public function test_home_brands_follow_chosen_order_and_limit(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Brand::query()->where('name', 'Bose')->first()->update(['home_position' => 0]);
        Brand::query()->whereIn('name', ['Nikon', 'Meta'])->update(['show_on_home' => true, 'home_position' => 20]);
        Catalog::flush();

        $names = Catalog::brands()->pluck('name')->all();

        $this->assertCount(Brand::HOME_LIMIT, $names);
        $this->assertSame(['Bose', 'Apple', 'Samsung', 'Sony', 'Xiaomi', 'DJI', 'Logitech', 'JBL'], $names);

        $tile = fn (string $name) => '<span class="font-display text-lg leading-none font-extrabold tracking-[-0.6px] sm:text-2xl">'.$name.'</span>';

        $response = $this->get('/ka')
            ->assertOk()
            ->assertSeeInOrder(array_map($tile, $names), false)
            ->assertDontSee($tile('Nikon'), false);

        $this->assertSame(Brand::HOME_LIMIT, substr_count($response->getContent(), 'tracking-[-0.6px] sm:text-2xl">'));
    }

    public function test_deal_price_applies_to_product_page_and_cart_only_while_live(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $tablet = Product::query()->where('sku', 'gtab-s10fe')->first();

        $this->get('/ka/product/samsung-galaxy-tab-s10-fe-128-gb')->assertOk()->assertSee('1 099 ₾');

        Livewire::test('cart')
            ->dispatch('cart-add', id: $tablet->id)
            ->assertSee('1 099 ₾');

        Deal::query()->first()->update(['ends_at' => now()->subMinute()]);

        $this->get('/ka')->assertOk()->assertDontSee('დღის შეთავაზება');
        $this->get('/ka/product/samsung-galaxy-tab-s10-fe-128-gb')->assertOk()->assertSee('1 199 ₾')->assertDontSee('1 099 ₾');
    }
}
