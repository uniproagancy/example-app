<?php

namespace Tests\Feature;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;
use App\Support\CategoryTree;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshApplicationWithLocale('ka');
    }

    protected function product(string $sku): Product
    {
        return Product::query()->where('sku', $sku)->firstOrFail();
    }

    public function test_promotions_page_lists_live_campaigns_and_each_campaign_shows_only_its_products(): void
    {
        $blackFriday = Campaign::factory()->create([
            'slug' => 'black-friday',
            'title' => ['ka' => 'შავი პარასკევი', 'en' => 'Black Friday'],
        ]);
        $blackFriday->products()->sync([$this->product('jbl')->id => ['position' => 0]]);
        Campaign::factory()->expired()->create(['title' => ['ka' => 'ძველი აქცია']]);
        Campaign::factory()->inactive()->create(['title' => ['ka' => 'გამორთული აქცია']]);

        $speaker = Catalog::find($this->product('jbl')->id);
        $otherProduct = Catalog::products()->firstWhere('id', '!=', $speaker['id']);

        $this->get('/ka/promotions')
            ->assertOk()
            ->assertSee('შავი პარასკევი')
            ->assertSee(route('promotions.show', ['campaign' => 'black-friday']), false)
            ->assertDontSee('ძველი აქცია')
            ->assertDontSee('გამორთული აქცია');

        $this->get('/ka/promotions/black-friday')
            ->assertOk()
            ->assertSee('<title>შავი პარასკევი — ELIO.GE</title>', false)
            ->assertSee('wire:key="category-product-'.$speaker['id'].'"', false)
            ->assertDontSee('wire:key="category-product-'.$otherProduct['id'].'"', false)
            ->assertSee(__('catalog.filters'))
            ->assertSee(__('catalog.sort.popular'));

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/ka/promotions/black-friday'), false);
    }

    public function test_campaign_discount_lowers_prices_of_its_products_while_it_runs(): void
    {
        $speaker = $this->product('jbl');
        $withOptions = Catalog::products()->first(fn (array $product) => collect($product['storages'])->whereNotNull('price')->isNotEmpty() && $product['id'] !== $speaker->id);
        $outside = Catalog::products()->first(fn (array $product) => ! in_array($product['id'], [$speaker->id, $withOptions['id']], true));
        $optionPrice = collect($withOptions['storages'])->whereNotNull('price')->first()['price'];
        $regular = (float) $speaker->price;

        $campaign = Campaign::factory()->create(['discount_percent' => 20]);
        $campaign->products()->sync([$speaker->id, $withOptions['id']]);
        Catalog::flush();

        $discounted = Catalog::find($speaker->id);
        $this->assertEquals(round($regular * 0.8), $discounted['price']);
        $this->assertEquals(max($regular, (float) $speaker->old_price), $discounted['old']);
        $this->assertEquals(round($optionPrice * 0.8), collect(Catalog::find($withOptions['id'])['storages'])->whereNotNull('price')->first()['price']);
        $this->assertEquals($outside['price'], Catalog::find($outside['id'])['price']);
        $this->assertGreaterThanOrEqual(20, Campaign::findLive($campaign->slug)['maxDiscount']);

        $campaign->update(['ends_at' => now()->subMinute()]);

        $this->assertEquals($regular, Catalog::find($speaker->id)['price']);
    }

    public function test_campaign_products_can_be_filtered_like_the_catalog(): void
    {
        $speaker = Catalog::find($this->product('jbl')->id);
        $other = Catalog::products()->first(fn (array $product) => $product['id'] !== $speaker['id'] && $product['brand'] && $product['brand'] !== $speaker['brand']);

        $campaign = Campaign::factory()->create(['slug' => 'brand-week']);
        $campaign->products()->sync([$speaker['id'], $other['id']]);

        Livewire::withQueryParams([])
            ->test('pages::catalog', ['campaign' => 'brand-week'])
            ->assertSee('category-product-'.$speaker['id'].'"', false)
            ->assertSee('category-product-'.$other['id'].'"', false)
            ->call('toggleBrand', $speaker['brand'])
            ->assertSee('category-product-'.$speaker['id'].'"', false)
            ->assertDontSee('category-product-'.$other['id'].'"', false);
    }

    public function test_home_page_shows_live_campaigns_instead_of_discounted_products(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $live = Campaign::factory()->create(['slug' => 'black-friday']);
        $live->products()->sync([$this->product('jbl')->id]);
        Campaign::factory()->expired()->create(['slug' => 'new-year']);

        $this->get('/ka')
            ->assertOk()
            ->assertSee('id="home-campaigns"', false)
            ->assertSee('/ka/promotions/black-friday', false)
            ->assertDontSee('/ka/promotions/new-year', false)
            ->assertSee(__('shop.sale.all'));

        Campaign::query()->forceDelete();
        Once::flush();

        $this->get('/ka')->assertOk()->assertDontSee('id="home-campaigns"', false);
    }

    public function test_finished_or_unknown_campaign_pages_are_not_found(): void
    {
        Campaign::factory()->expired()->create(['slug' => 'new-year']);

        $this->get('/ka/promotions/new-year')->assertNotFound();
        $this->get('/ka/promotions/missing')->assertNotFound();
    }

    public function test_admin_creates_a_campaign_with_products_in_the_chosen_order(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $speaker = $this->product('jbl');
        $phone = Product::query()->whereKeyNot($speaker->id)->firstOrFail();

        $this->get(CampaignResource::getUrl())->assertOk()->assertSee(__('admin.campaigns.help'));

        Livewire::test(ManageCampaigns::class)
            ->callAction(CreateAction::class, [
                'title' => ['ka' => 'მობილურების კვირეული', 'en' => 'Mobile week'],
                'badge' => ['ka' => '−15%'],
                'slug' => 'mobile-week',
                'theme' => 'blue',
                'discount_percent' => 15,
                'products' => [$phone->id, $speaker->id],
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $campaign = Campaign::query()->sole();

        $this->assertSame('mobile-week', $campaign->slug);
        $this->assertSame([$phone->id, $speaker->id], $campaign->products()->pluck('products.id')->all());
        $this->assertSame(15, $campaign->discount_percent);
        $this->assertEquals(round((float) $speaker->price * 0.85), Catalog::find($speaker->id)['price']);
    }

    public function test_admin_can_add_a_whole_category_to_a_campaign(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $root = CategoryTree::roots()->first(fn (array $node) => Catalog::productsIn($node)->isNotEmpty());
        $expected = Catalog::productsIn($root)->pluck('id')->sort()->values()->all();

        Livewire::test(ManageCampaigns::class)
            ->mountAction(CreateAction::class)
            ->fillForm(['add_category' => (string) $root['id']])
            ->fillForm(['title' => ['ka' => 'ახალი წელი'], 'slug' => 'new-year', 'theme' => 'red'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertEqualsCanonicalizing($expected, Campaign::query()->sole()->products()->pluck('products.id')->all());
    }
}
