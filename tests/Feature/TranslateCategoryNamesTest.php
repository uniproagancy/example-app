<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslateCategoryNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gives_georgian_categories_english_names_and_slugs_and_keeps_real_english_names(): void
    {
        $root = Category::query()->create(['is_active' => true]);
        $root->saveTranslations(['ka' => ['title' => 'ფოტო და ვიდეო', 'slug' => 'foto-da-video']]);

        $child = Category::createUnder($root->id, 'დრონი');
        $brand = Category::createUnder($root->id, 'PlayStation');
        $named = Category::createUnder($root->id, 'ბინოკლი', 'Field binoculars');
        $unknown = Category::createUnder($root->id, 'უცნობი კატეგორია');

        $this->artisan('categories:translate', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($root->fresh()->translate('en', false));

        $this->artisan('categories:translate')
            ->expectsOutputToContain('უცნობი კატეგორია')
            ->expectsOutputToContain('Translated 2 categories.')
            ->assertSuccessful();

        $this->assertSame('Photo and video', $root->fresh()->translate('en', false)->title);
        $this->assertStringStartsWith('photo-and-video', $root->fresh()->translate('en', false)->slug);
        $this->assertSame('Drones', $child->fresh()->translate('en', false)->title);
        $this->assertStringStartsWith('drones', $child->fresh()->translate('en', false)->slug);
        $this->assertSame('PlayStation', $brand->fresh()->translate('en', false)->title);
        $this->assertSame('Field binoculars', $named->fresh()->translate('en', false)->title);
        $this->assertSame('უცნობი კატეგორია', $unknown->fresh()->translate('en', false)->title);
        $this->assertSame('ფოტო და ვიდეო', $root->fresh()->translate('ka', false)->title);
        $this->assertTrue(Redirect::query()->where('from_path', '/en/categories/droni')->exists());
    }
}
