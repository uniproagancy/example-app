<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportSpreadsheet;
use App\Jobs\ImportProductJob;
use App\Models\Supplier;
use App\Models\SupplierStock;
use App\Models\User;
use App\Services\Import\SupplierActions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Fakes\FakeSupplierDriver;
use Tests\TestCase;

class ImportSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        FakeSupplierDriver::$payloads = [];
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function supplier(bool $active = true): Supplier
    {
        return Supplier::query()->create([
            'code' => 'fake',
            'name' => 'Fake',
            'driver' => FakeSupplierDriver::class,
            'is_active' => $active,
            'priority' => 10,
            'config' => ['columns' => ['key' => 'A', 'name' => 'B', 'cost' => 'C', 'stock' => 'D']],
            'markup' => [],
        ]);
    }

    protected function csv(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_page_offers_only_suppliers_that_take_price_lists(): void
    {
        $kontakt = Supplier::query()->where('code', 'kontakt')->sole();
        $zoommer = Supplier::query()->where('code', 'zoommer')->sole();

        $this->get(ImportSpreadsheet::getUrl(['supplier' => 'kontakt']))->assertOk()->assertSee('Excel');

        $component = Livewire::withQueryParams(['supplier' => 'kontakt'])->test(ImportSpreadsheet::class)
            ->assertSet('data.supplier_id', $kontakt->id)
            ->assertSee('key: A');

        $this->assertArrayNotHasKey($zoommer->id, $component->instance()->form->getComponent('supplier_id')->getOptions());
    }

    public function test_file_is_previewed_confirmed_and_loaded_into_the_chosen_supplier(): void
    {
        $supplier = $this->supplier();
        SupplierStock::query()->create(['supplier_id' => $supplier->id, 'external_id' => 'OLD', 'quantity' => 3, 'synced_at' => now()]);

        $file = $this->csv('kontakt_10-11.csv', "\xEF\xBB\xBFcode;name;cost;stock\nX1;Kettle;\"1 250,50\";4\nX2;Toaster;80;0\nX1;Kettle;1200;5\n");

        Livewire::withQueryParams(['supplier' => 'fake'])->test(ImportSpreadsheet::class)
            ->set('data.file', [$file])
            ->mountAction('load')
            ->assertActionMounted('load')
            ->assertSet('pending.total', 3)
            ->assertSet('pending.rows.1.name', 'Toaster')
            ->assertSet('pending.other', 'Kontakt Home')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSet('result.read', 2)
            ->assertSet('result.zeroed', 1)
            ->assertSet('result.duplicates', 1)
            ->assertSet('data.file', [])
            ->assertSet('pending', null);

        $this->assertSame(5, SupplierStock::query()->where('external_id', 'X1')->value('quantity'));
        $this->assertSame('1200.00', SupplierStock::query()->where('external_id', 'X1')->value('cost_price'));
        $this->assertSame('Toaster', SupplierStock::query()->where('external_id', 'X2')->value('data')['name']);
        $this->assertSame(0, SupplierStock::query()->where('external_id', 'OLD')->value('quantity'));
    }

    public function test_unreadable_or_wrong_files_never_reach_the_stock_table(): void
    {
        $this->supplier();

        Livewire::withQueryParams(['supplier' => 'fake'])->test(ImportSpreadsheet::class)
            ->set('data.file', [$this->csv('prices.pdf', 'X1,Kettle,10,1')])
            ->mountAction('load')
            ->assertHasErrors(['data.file'])
            ->assertActionNotMounted('load')
            ->set('data.file', [$this->csv('prices.csv', "code,name,cost,stock\n")])
            ->mountAction('load')
            ->assertHasErrors(['data.file'])
            ->assertActionNotMounted('load');

        $this->assertSame(0, SupplierStock::query()->count());
    }

    public function test_partial_file_queues_only_its_own_rows_and_switches_the_supplier_on(): void
    {
        Queue::fake();

        $supplier = $this->supplier(active: false);
        FakeSupplierDriver::$payloads['fake'] = collect(['X1', 'X2', 'X3'])
            ->mapWithKeys(fn (string $id) => [$id => FakeSupplierDriver::payload($id)])
            ->all();
        SupplierStock::query()->create(['supplier_id' => $supplier->id, 'external_id' => 'X1', 'quantity' => 3, 'synced_at' => now()]);

        Livewire::withQueryParams(['supplier' => 'fake'])->test(ImportSpreadsheet::class)
            ->set('data.mode', 'merge')
            ->set('data.file', [$this->csv('fake.csv', "code,name,cost,stock\nX2,Toaster,80,2\n")])
            ->callAction('load')
            ->assertSet('result.zeroed', 0)
            ->callAction('queue');

        $this->assertSame(3, SupplierStock::query()->where('external_id', 'X1')->value('quantity'));
        $this->assertTrue($supplier->fresh()->is_active);

        Queue::assertPushed(ImportProductJob::class, 1);
        Queue::assertPushed(ImportProductJob::class, fn (ImportProductJob $job) => $job->externalId === 'X2');
    }

    public function test_old_excel_workbooks_are_read_with_links_and_leading_zeros(): void
    {
        $supplier = $this->supplier();
        $supplier->update(['config' => ['columns' => ['key' => 'A', 'url' => 'B', 'cost' => 'C', 'stock' => 'D']]]);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['code', 'link', 'cost', 'stock']]);
        $sheet->setCellValueExplicit('A2', '00123', DataType::TYPE_STRING);
        $sheet->setCellValue('B2', 'პროდუქტი');
        $sheet->getCell('B2')->getHyperlink()->setUrl('https://example.ge/p/123');
        $sheet->setCellValue('C2', 1250.5);
        $sheet->setCellValue('D2', 7);

        $path = tempnam(sys_get_temp_dir(), 'xl').'.xls';
        IOFactory::createWriter($book, 'Xls')->save($path);

        $result = app(SupplierActions::class)->loadPriceList($supplier, $path);

        $row = SupplierStock::query()->where('supplier_id', $supplier->id)->sole();

        $this->assertSame(1, $result['read']);
        $this->assertSame('00123', $row->external_id);
        $this->assertSame('1250.50', $row->cost_price);
        $this->assertSame(7, $row->quantity);
        $this->assertSame('https://example.ge/p/123', $row->data['url']);

        @unlink($path);
    }
}
