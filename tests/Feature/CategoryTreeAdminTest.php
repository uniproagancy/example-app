<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryTreeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        CategoryResource::flushTree();
    }

    public function test_categories_are_listed_as_a_tree_with_children_under_their_parent(): void
    {
        $iphone = Product::query()->where('sku', 'ip16')->sole();
        $leaf = $iphone->category;
        $root = Category::query()->find($leaf->ancestorIds() ? last($leaf->ancestorIds()) : $leaf->id);
        $branch = [$root->id];

        for ($level = [$root->id]; $level !== [];) {
            $level = Category::query()->whereIn('parent_id', $level)->pluck('id')->all();
            $branch = [...$branch, ...$level];
        }

        $directOnRoot = Product::query()->where('category_id', $root->id)->count();
        $total = Product::query()->whereIn('category_id', $branch)->count();

        $tree = CategoryResource::tree();
        $records = Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$root, $leaf])
            ->assertSee(__('admin.categories.children_count', ['count' => $root->children()->count()]))
            ->instance()
            ->getTableRecords()
            ->pluck('id')
            ->all();

        $this->assertSame(0, $tree[$root->id]['depth']);
        $this->assertGreaterThan(0, $tree[$leaf->id]['depth']);
        $this->assertGreaterThan($directOnRoot, $tree[$root->id]['total']);
        $this->assertSame($total, $tree[$root->id]['total']);

        foreach ($records as $index => $id) {
            $parentId = Category::query()->whereKey($id)->value('parent_id');

            if ($parentId) {
                $this->assertContains($parentId, array_slice($records, 0, $index), 'Every child is listed after its parent.');
                $this->assertSame($tree[$parentId]['depth'] + 1, $tree[$id]['depth']);
            }
        }
    }

    public function test_plus_adds_a_subcategory_right_under_the_row_and_can_keep_going(): void
    {
        $parent = Product::query()->where('sku', 'ip16')->sole()->category;
        $lastPosition = (int) Category::query()->where('parent_id', $parent->id)->max('position');

        Livewire::test(ListCategories::class)
            ->callTableAction('addChild', $parent, ['title' => 'ქეისები'])
            ->assertHasNoTableActionErrors()
            ->assertNotified()
            ->callTableAction('addChild', $parent, ['title' => 'დამცავი მინები', 'title_en' => 'Screen protectors', 'is_active' => false], ['another' => true])
            ->assertHasNoTableActionErrors()
            ->assertSet('mountedActions.0.name', 'addChild')
            ->assertSet('mountedActions.0.data.title', null);

        $cases = Category::query()->whereHas('translations', fn ($query) => $query->where('title', 'ქეისები'))->sole();
        $glass = Category::query()->whereHas('translations', fn ($query) => $query->where('title', 'დამცავი მინები'))->sole();

        $this->assertSame($parent->id, $cases->parent_id);
        $this->assertSame($lastPosition + 1, $cases->position);
        $this->assertTrue($cases->is_active);
        $this->assertSame('ქეისები', $cases->translate('en')->title);
        $this->assertSame('keisebi', $cases->translate('ka')->slug);

        $this->assertSame($parent->id, $glass->parent_id);
        $this->assertSame($lastPosition + 2, $glass->position);
        $this->assertFalse($glass->is_active);
        $this->assertSame('Screen protectors', $glass->translate('en')->title);

        Livewire::test(ListCategories::class)
            ->callTableAction('addChild', $parent, ['title' => ''])
            ->assertHasTableActionErrors(['title' => 'required']);
    }
}
