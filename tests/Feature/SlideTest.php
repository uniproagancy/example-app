<?php

namespace Tests\Feature;

use App\Models\Slide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlideTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_hero_slides_come_from_database_in_current_language(): void
    {
        $this->refreshApplicationWithLocale('ka');

        $this->get('/ka')
            ->assertOk()
            ->assertSeeInOrder(['iPhone 17 Pro — შემდეგი დონე', 'ითამაშე მეტი, დაელოდე ნაკლები', 'დიდი ეკრანები, მცირე ფასად'])
            ->assertSee('იყიდე ახლავე')
            ->assertSee('href="'.url('/ka/categories/gaming').'"', false);

        $this->refreshApplicationWithLocale('en');

        $this->get('/en')
            ->assertOk()
            ->assertSee('Play more, wait less')
            ->assertSee('href="'.url('/en/categories/gaming').'"', false);
    }

    public function test_inactive_and_scheduled_slides_are_hidden(): void
    {
        $this->refreshApplicationWithLocale('en');

        Slide::query()->where('position', 2)->update(['is_active' => false]);
        Slide::query()->where('position', 3)->update(['starts_at' => now()->addDay()]);

        Slide::query()->create([
            'title' => ['ka' => 'ვადაგასული', 'en' => 'Expired promo'],
            'ends_at' => now()->subMinute(),
            'position' => 9,
        ]);

        cache()->forget(Slide::CACHE_KEY);

        $this->get('/en')
            ->assertOk()
            ->assertSee('iPhone 17 Pro — the next level')
            ->assertDontSee('Play more, wait less')
            ->assertDontSee('Big screens, small prices')
            ->assertDontSee('Expired promo');
    }

    public function test_slide_with_custom_url_and_image(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Slide::query()->delete();
        Slide::query()->create([
            'title' => ['ka' => 'შავი პარასკევი', 'en' => 'Black Friday'],
            'button_label' => ['ka' => 'ნახვა', 'en' => 'View'],
            'url' => '/about',
            'image' => 'slides/black-friday.jpg',
            'theme' => 'unknown-theme',
        ]);

        $this->get('/ka')
            ->assertOk()
            ->assertSee('შავი პარასკევი')
            ->assertSee('href="'.url('/ka/about').'"', false)
            ->assertSee('/storage/slides/black-friday.jpg', false)
            ->assertDontSee('aria-label="'.__('shop.next', [], 'ka').'" class="absolute top-1/2', false);
    }

    public function test_home_page_works_without_slides(): void
    {
        $this->refreshApplicationWithLocale('en');

        Slide::query()->delete();

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('x-data="heroSlider', false)
            ->assertSee('id="sec-mobile"', false);
    }
}
