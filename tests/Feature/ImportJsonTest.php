<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportJson;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            ->set('data.file', [UploadedFile::fake()->createWithContent('alta.json', json_encode($answers))])
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
        $this->assertSame((float) $alta->markup(150), (float) $product->price);
    }

    public function test_paste_and_file_together_or_garbage_are_refused(): void
    {
        Livewire::test(ImportJson::class)
            ->set('data.json', json_encode($this->answer(1)))
            ->set('data.file', [UploadedFile::fake()->createWithContent('alta.json', json_encode($this->answer(2)))])
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
