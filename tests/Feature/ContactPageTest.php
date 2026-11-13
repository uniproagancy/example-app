<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_contact_page_shows_settings_and_faqs_from_database(): void
    {
        $this->refreshApplicationWithLocale('ka');

        Faq::query()->first()->update(['question' => ['ka' => 'სად არის ჩემი ამანათი?', 'en' => 'Where is my parcel?']]);

        $this->get('/ka/contact')
            ->assertOk()
            ->assertSee('<title>კონტაქტი — ELIO.GE</title>', false)
            ->assertSee('დაგვიკავშირდი ნებისმიერი გზით')
            ->assertSee('info@elio.ge')
            ->assertSee('10:00–20:00')
            ->assertSee('სად არის ჩემი ამანათი?')
            ->assertSee('hreflang="en" href="'.url('/en/contact').'"', false);
    }

    public function test_message_is_saved_with_order_number_for_order_topics(): void
    {
        Livewire::test('pages::contact')
            ->call('chooseTopic', 'order')
            ->set('name', 'ნინო')
            ->set('phone', '555 12 34 56')
            ->set('orderNumber', 'EL-123456')
            ->set('message', 'სად არის ჩემი შეკვეთა?')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $message = ContactMessage::query()->sole();
        $this->assertSame('order', $message->topic);
        $this->assertSame('EL-123456', $message->order_number);
    }

    public function test_message_validation_and_no_waiting_between_messages(): void
    {
        Livewire::test('pages::contact')
            ->call('send')
            ->assertHasErrors(['name', 'phone', 'message']);

        $component = Livewire::test('pages::contact')->set('name', 'Test')->set('phone', '555123456');

        foreach (range(1, 5) as $attempt) {
            $component->set('message', 'Hello there '.$attempt)->call('send')->assertHasNoErrors();
        }

        $this->assertSame(5, ContactMessage::query()->count());
    }

    public function test_about_page_and_navigation_links(): void
    {
        $this->refreshApplicationWithLocale('en');

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('Tech you can trust')
            ->assertSee('Why we built ELIO.GE')
            ->assertSee('Official warranty')
            ->assertSee(url('/en/contact'), false);

        $this->get('/en')->assertSee('href="'.url('/en/contact').'"', false);
    }
}
