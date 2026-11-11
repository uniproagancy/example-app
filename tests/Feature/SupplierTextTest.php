<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Import\SpecNames;
use App\Services\Import\TaxonomyResolver;
use App\Support\Catalog;
use App\Support\Html;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTextTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_english_spec_names_get_a_georgian_name_and_keep_the_english_one(): void
    {
        $this->assertSame(['ka' => 'ფერი', 'en' => 'Color'], SpecNames::translate('Color:'));
        $this->assertSame(['ka' => 'მაქსიმალური დატვირთვა', 'en' => 'Load of Weight'], SpecNames::translate('Load of Weight'));
        $this->assertSame(['ka' => 'შეფუთვა (mm) (L × W × H)', 'en' => 'Packing (mm) (L × W × H)'], SpecNames::translate('Packing (mm) (L &times; W &times; H)'));
        $this->assertSame(['ka' => 'Flux capacitor', 'en' => 'Flux capacitor'], SpecNames::translate('Flux capacitor'));
        $this->assertSame(['ka' => 'ეკრანის ზომა', 'en' => null], SpecNames::translate('ეკრანის ზომა'));

        $supplier = Supplier::query()->where('code', 'allmarket')->sole();
        $attribute = app(TaxonomyResolver::class)->attribute($supplier, 'Model');

        $this->assertSame('modeli', $attribute->code);
        $this->assertSame('მოდელი', $attribute->translate('ka')->name);
        $this->assertSame('Model', $attribute->translate('en')->name);
    }

    public function test_attributes_already_imported_in_english_are_translated_by_command(): void
    {
        $attribute = Attribute::query()->create(['code' => 'load-of-weight', 'type' => 'select']);
        $attribute->saveTranslations(['ka' => ['name' => 'Load of Weight']]);
        $unknown = Attribute::query()->create(['code' => 'flux', 'type' => 'select']);
        $unknown->saveTranslations(['ka' => ['name' => 'Flux capacitor']]);

        $this->artisan('attributes:translate-names', ['--dry-run' => true])->expectsOutputToContain('Would rename 1')->assertSuccessful();
        $this->assertSame('Load of Weight', $attribute->fresh()->translate('ka')->name);

        $this->artisan('attributes:translate-names')->expectsOutputToContain('Renamed 1')->assertSuccessful();

        $attribute = $attribute->fresh('translations');
        $this->assertSame('მაქსიმალური დატვირთვა', $attribute->translate('ka')->name);
        $this->assertSame('Load of Weight', $attribute->translate('en')->name);
        $this->assertSame('Flux capacitor', $unknown->fresh()->translate('ka')->name);
    }

    public function test_supplier_html_is_cleaned_for_the_product_page(): void
    {
        $this->assertSame(
            '<p>Model:CPMS-4365<br> Size: 1 × 2<br>Color: <b>White</b></p>',
            Html::clean('<p onclick="x()">Model:CPMS-4365<br /> Size: 1 &times; 2<br><span style="color:red">Color: <b>White</b></span></p><script>alert(1)</script><iframe src="x"></iframe>'),
        );
        $this->assertSame('<p>line one<br>line two</p><p>second &amp; last</p>', Html::clean("line one\nline two\n\nsecond &amp; last"));
        $this->assertSame('<p>escaped</p>', Html::clean('&lt;p&gt;escaped&lt;/p&gt;'));
        $this->assertSame('', Html::clean(null));

        $this->refreshApplicationWithLocale('ka');

        Product::query()->where('sku', 'ip16')->sole()->translations()->where('locale', 'ka')
            ->update(['description' => '<p>Technical Parameters<br /> Model:CPMS-4365<br /> Packing (mm) (L &times; W &times; H): 305*110*57mm</p><script>alert(1)</script>']);
        Catalog::flush();

        $this->get('/ka/product/apple-iphone-16-pro')
            ->assertOk()
            ->assertSee('<p>Technical Parameters<br> Model:CPMS-4365<br> Packing (mm) (L × W × H): 305*110*57mm</p>', false)
            ->assertDontSee('&lt;p&gt;', false)
            ->assertDontSee('alert(1)', false);
    }
}
