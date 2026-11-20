<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportJson;
use App\Http\Controllers\JsonUploadController;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ImportJsonTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        Http::fake();
        Storage::fake('local');
    }

    protected function upload(string $content): string
    {
        $upload = (string) Str::uuid();
        Storage::disk('local')->put(JsonUploadController::path((int) auth()->id(), $upload), $content);

        return $upload;
    }

    protected function answer(int $id, array $overrides = []): array
    {
        return array_replace_recursive(['product' => [
            'id' => $id,
            'barCode' => 'BC'.$id,
            'name' => 'Alta speaker '.$id,
            'price' => 200,
            'brandName' => 'JBL',
            'parentCategoryName' => 'Audio',
            'categoryName' => 'Speakers',
            'storageQuantity' => 4,
            'specificationGroup' => [['specifications' => [
                ['specificationName' => 'Color', 'specificationMeaning' => 'Black', 'isMainSpecification' => true],
            ]]],
        ]], $overrides);
    }

    public function test_page_renders_and_previews_before_saving_anything(): void
    {
        $alta = Supplier::query()->where('code', 'alta')->sole();

        $this->get(ImportJson::getUrl())->assertOk()->assertSee(__('admin.json.paste'));

        $page = Livewire::test(ImportJson::class)
            ->assertSet('data.supplier_id', $alta->id)
            ->set('data.json', json_encode([$this->answer(1), $this->answer(2), ['product' => ['id' => 3, 'name' => '', 'price' => 0]]]))
            ->call('look')
            ->assertHasNoErrors()
            ->assertSet('total', 3)
            ->assertSet('valid', 2)
            ->assertSet('preview.0.state', 'new')
            ->assertSet('preview.2.state', 'invalid');

        $this->assertNotEmpty($page->get('problems'));
        $this->assertSame(0, Product::query()->where('sku', 'like', 'alta-%')->count());
        Storage::disk('local')->assertExists('json-imports/'.$page->get('sourceKey').'.json');
    }

    public function test_saving_imports_in_batches_and_marks_known_products_as_updates(): void
    {
        $answers = collect(range(1, 7))->map(fn (int $id) => $this->answer($id))->all();

        $page = Livewire::test(ImportJson::class)
            ->call('uploaded', $this->upload(json_encode($answers)), 'alta.json')
            ->call('look')
            ->assertSet('valid', 7)
            ->call('startSaving')
            ->assertSet('cursor', ImportJson::BATCH)
            ->assertSet('saving', true)
            ->call('saveNext')
            ->assertSet('saving', false)
            ->assertSet('saved', 7)
            ->assertNotified(__('admin.json.done', ['count' => 7]));

        $product = Product::query()->where('sku', 'alta-BC1')->with('kaTranslation')->sole();
        $this->assertSame('Alta speaker 1', $product->kaTranslation->name);
        $this->assertFalse($product->is_active);
        $this->assertSame(4, $product->stock);
        Storage::disk('local')->assertMissing('json-imports/'.$page->get('sourceKey').'.json');

        Livewire::test(ImportJson::class)
            ->set('data.json', json_encode($this->answer(1)))
            ->call('look')
            ->assertSet('preview.0.state', 'update');
    }

    protected function zoommerAnswer(int $id, array $product = [], ?array $assets = null): array
    {
        $answer = ['product' => array_replace([
            'id' => $id,
            'barCode' => '88-'.$id,
            'name' => 'Zoommer phone '.$id,
            'price' => 900,
            'previousPrice' => 1000,
            'brandName' => 'Samsung',
            'categoryName' => 'Phones',
            'subCategoryName' => 'Smartphones',
            'isInStock' => true,
            'imageUrl' => "https://s3.zoommer.ge/site/{$id}.jpeg",
            'images' => [],
            'specificationGroup' => [],
        ], $product), 'availabilityInStores' => [['city' => 'თბილისი', 'inStock' => true]]];

        if ($assets !== null) {
            $answer['iapiAssets'] = $assets;
        }

        return $answer;
    }

    protected function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_a_zoommer_extension_file_saves_products_with_the_photos_inside_it(): void
    {
        Storage::fake('public');
        $zoommer = Supplier::query()->where('code', 'zoommer')->sole();
        $photo = ['url' => 'https://s3.zoommer.ge/site/41.jpeg', 'base64' => base64_encode($this->png())];

        $answers = [
            $this->zoommerAnswer(41, [], [$photo]),
            $this->zoommerAnswer(42, ['name' => '']),
            $this->zoommerAnswer(43, [], [['url' => 'https://s3.zoommer.ge/site/43.jpeg', 'base64' => 'not base64 !!']]),
        ];

        Livewire::test(ImportJson::class)
            ->set('data.supplier_id', $zoommer->id)
            ->call('uploaded', $this->upload(json_encode($answers)), 'zoommer.json')
            ->call('look')
            ->assertSet('total', 3)
            ->assertSet('valid', 1)
            ->assertSet('preview.0.code', '41')
            ->assertSet('preview.0.state', 'new')
            ->assertSet('preview.2.state', 'invalid')
            ->call('startSaving')
            ->assertSet('saved', 1);

        $product = Product::query()->where('sku', 'ZOOM-8841')->with('kaTranslation')->sole();
        $this->assertSame('Zoommer phone 41', $product->kaTranslation->name);
        $this->assertSame('900.00', $product->cost_price);
        $this->assertSame(1, $product->images()->count());
        Http::assertNothingSent();

        Livewire::test(ImportJson::class)
            ->set('data.supplier_id', $zoommer->id)
            ->set('data.json', json_encode($this->zoommerAnswer(41)))
            ->call('look')
            ->assertSet('preview.0.state', 'update');
    }

    public function test_a_single_answer_can_have_cost_and_stock_corrected(): void
    {
        $alta = Supplier::query()->where('code', 'alta')->sole();

        Livewire::test(ImportJson::class)
            ->set('data.json', json_encode($this->answer(9)))
            ->call('look')
            ->assertSet('data.price', '200')
            ->assertSet('data.stock', '4')
            ->set('data.price', '150')
            ->set('data.stock', '11')
            ->call('startSaving')
            ->assertSet('saved', 1);

        $product = Product::query()->where('sku', 'alta-BC9')->sole();
        $this->assertSame(11, $product->stock);
        $this->assertSame('150.00', $product->cost_price);
        $this->assertSame('150.00', $product->price);
    }

    public function test_a_large_file_arrives_in_pieces_and_is_read_one_product_at_a_time(): void
    {
        $content = "\u{FEFF}[\n".json_encode($this->answer(1), JSON_PRETTY_PRINT).",\n".json_encode($this->answer(2, ['product' => ['name' => 'Brace } and "quote" {']])).']'.str_repeat(' ', 12 * 1024 * 1024);
        $upload = (string) Str::uuid();
        $offset = 0;

        foreach (str_split($content, JsonUploadController::CHUNK_BYTES) as $chunk) {
            $this->call('POST', route('json-upload', ['upload' => $upload]), [], [], [], ['HTTP_X_UPLOAD_OFFSET' => (string) $offset, 'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $chunk)
                ->assertOk()
                ->assertJsonPath('size', $offset + strlen($chunk));
            $offset += strlen($chunk);
        }

        $this->assertSame(strlen($content), Storage::disk('local')->size(JsonUploadController::path((int) auth()->id(), $upload)));

        Livewire::test(ImportJson::class)
            ->call('uploaded', $upload, 'alta.json')
            ->call('look')
            ->assertHasNoErrors()
            ->assertSet('total', 2)
            ->assertSet('valid', 2)
            ->assertSet('preview.1.name', 'Brace } and "quote" {')
            ->call('startSaving')
            ->assertSet('saved', 2);

        Storage::disk('local')->assertMissing(JsonUploadController::path((int) auth()->id(), $upload));
    }

    public function test_upload_pieces_must_arrive_in_order_and_only_from_admins(): void
    {
        $upload = (string) Str::uuid();
        $send = fn (int $offset, string $chunk) => $this->call('POST', route('json-upload', ['upload' => $upload]), [], [], [], ['HTTP_X_UPLOAD_OFFSET' => (string) $offset, 'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $chunk);

        $send(0, 'abc')->assertOk()->assertJsonPath('size', 3);
        $send(10, 'def')->assertStatus(409)->assertJsonPath('size', 3);
        $send(3, 'def')->assertOk()->assertJsonPath('size', 6);

        $this->actingAs(User::factory()->operator()->create());
        $send(0, 'abc')->assertForbidden();
    }

    public function test_paste_and_file_together_or_garbage_are_refused(): void
    {
        Livewire::test(ImportJson::class)
            ->set('data.json', json_encode($this->answer(1)))
            ->call('uploaded', $this->upload(json_encode($this->answer(2))), 'alta.json')
            ->call('look')
            ->assertHasErrors(['data.file']);

        Livewire::test(ImportJson::class)
            ->set('data.json', 'definitely not json at all')
            ->call('look')
            ->assertSet('valid', 0);

        Livewire::test(ImportJson::class)
            ->call('startSaving')
            ->assertHasErrors(['data.json']);
    }
}
