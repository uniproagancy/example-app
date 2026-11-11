<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportLinks;
use App\Models\ProductOffer;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ImportLinksTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['services.citrus.base_url' => 'https://citrus.ge']);
        Http::preventStrayRequests();
    }

    protected function citrus(): Supplier
    {
        return Supplier::query()->where('code', 'citrus')->sole();
    }

    protected function answer(): array
    {
        return [
            'id' => 9001,
            'sku' => 'CT-9001',
            'name' => 'Marazzi გამწოვი 60 სმ',
            'price' => '459.00',
            'old_price' => '499.00',
            'stock' => 3,
            'stock_status' => 1,
            'manufacturer' => ['name' => 'Marazzi'],
            'breadcrumb' => [['name' => 'სამზარეულო'], ['name' => 'გამწოვები']],
            'details' => ['description' => '<p>ჩასაშენებელი გამწოვი</p>'],
            'attributes' => [['name' => 'სიგანე', 'value' => '60 სმ', 'type' => 'checkbox']],
        ];
    }

    public function test_pasted_links_join_the_queue_and_foreign_addresses_are_skipped(): void
    {
        $citrus = $this->citrus();

        $this->get(ImportLinks::getUrl())->assertOk()->assertSee(__('admin.links.links'));

        Livewire::test(ImportLinks::class)
            ->assertSet('data.supplier_id', $citrus->id)
            ->set('data.category', 'გამწოვები')
            ->set('data.links', "https://citrus.ge/product/gamtsovi-marazzi-60\nhttps://citrus.ge/product/gamtsovi-marazzi-60, https://zoommer.ge/product/phone-1\nhttps://citrus.ge/product/qura-bosch-2")
            ->call('add')
            ->assertHasNoErrors()
            ->assertSet('result.added', 2)
            ->assertSet('result.skipped', ['https://zoommer.ge/product/phone-1'])
            ->assertSet('data.links', null)
            ->assertSee('gamtsovi-marazzi-60')
            ->assertSee(__('admin.links.fetch', ['count' => 2]));

        $rows = SupplierStock::query()->where('supplier_id', $citrus->id)->orderBy('id')->get();

        $this->assertSame(['gamtsovi-marazzi-60', 'qura-bosch-2'], $rows->pluck('external_id')->all());
        $this->assertSame('გამწოვები', $rows->first()->data['category']);
        Http::assertNothingSent();
    }

    public function test_fetching_imports_each_link_once_and_explains_failures(): void
    {
        $citrus = $this->citrus();

        Http::fake([
            'citrus.ge/api/product/gamtsovi-marazzi-60' => Http::response($this->answer()),
            'citrus.ge/api/product/gone-forever' => Http::response(['message' => 'Not found'], 404),
        ]);

        $page = Livewire::test(ImportLinks::class)
            ->set('data.links', "https://citrus.ge/product/gamtsovi-marazzi-60\nhttps://citrus.ge/product/gone-forever")
            ->call('add')
            ->call('start')
            ->assertSet('running', true)
            ->assertSet('total', 2);

        for ($guard = 0; $page->get('running') && $guard < 5; $guard++) {
            $page->call('fetchNext');
        }

        $page->assertSet('running', false)
            ->assertSet('done', 1)
            ->assertSet('failed', 1)
            ->assertSet('failures', ['gone-forever' => __('admin.links.reason_page')])
            ->assertSee(__('admin.links.finished', ['done' => 1, 'failed' => 1]));

        $offer = ProductOffer::query()->where('supplier_id', $citrus->id)->where('external_id', 'gamtsovi-marazzi-60')->sole();

        $this->assertSame('Marazzi გამწოვი 60 სმ', $offer->product->kaTranslation->name);
        $this->assertSame(3, $offer->stock);

        $page->call('start')
            ->assertSet('total', 1);

        for ($guard = 0; $page->get('running') && $guard < 5; $guard++) {
            $page->call('fetchNext');
        }

        $page->assertSet('running', false)->assertSet('done', 0)->assertSet('failed', 1);
        $this->assertSame(1, ProductOffer::query()->where('supplier_id', $citrus->id)->count());
    }

    public function test_a_link_can_be_taken_out_of_the_queue(): void
    {
        $citrus = $this->citrus();
        $row = SupplierStock::query()->create([
            'supplier_id' => $citrus->id,
            'external_id' => 'gamtsovi-marazzi-60',
            'quantity' => 1,
            'data' => ['slug' => 'gamtsovi-marazzi-60'],
        ]);

        Livewire::test(ImportLinks::class)
            ->assertSee('gamtsovi-marazzi-60')
            ->call('forget', $row->id)
            ->assertDontSee('gamtsovi-marazzi-60');

        $this->assertModelMissing($row);
    }
}
