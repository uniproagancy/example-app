<?php

use App\Models\ContactMessage;
use App\Models\Faq;
use App\Support\Site;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $topic = 'consultation';

    public string $name = '';

    public string $phone = '';

    public string $orderNumber = '';

    public string $message = '';

    public bool $sent = false;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->phone = (string) $user->phone;
        }
    }

    public function chooseTopic(string $topic): void
    {
        if (in_array($topic, ContactMessage::TOPICS, true)) {
            $this->topic = $topic;
        }
    }

    public function send(): void
    {
        $this->validate();

        ContactMessage::query()->create([
            'user_id' => auth()->user()?->id,
            'topic' => $this->topic,
            'name' => trim($this->name),
            'phone' => trim($this->phone),
            'order_number' => $this->needsOrder() && trim($this->orderNumber) !== '' ? trim($this->orderNumber) : null,
            'message' => trim($this->message),
            'locale' => app()->getLocale(),
        ]);

        $this->reset('message', 'orderNumber');
        $this->sent = true;
    }

    public function startOver(): void
    {
        $this->sent = false;
    }

    public function needsOrder(): bool
    {
        return in_array($this->topic, ContactMessage::TOPICS_WITH_ORDER, true);
    }

    protected function rules(): array
    {
        return [
            'topic' => ['required', Rule::in(ContactMessage::TOPICS)],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-()]{9,20}$/'],
            'orderNumber' => ['nullable', 'string', 'max:20'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => __('pages.contact.name'),
            'phone' => __('pages.contact.phone'),
            'orderNumber' => __('pages.contact.order_number'),
            'message' => __('pages.contact.message'),
        ];
    }

    public function render()
    {
        return $this->view([
            'faqs' => Faq::query()->visible()->get(),
            'hours' => Site::hours(),
        ])->title(__('pages.contact.title'));
    }
};
?>

@php
    $faqIcons = [
        'delivery' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>',
        'warranty' => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM8.5 12l2.5 2.5 4.5-5"/>',
        'installments' => '<path d="M3 6h18v12H3zM3 10h18M7 15h4"/>',
        'returns' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
        'payment' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M15 15h3"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"/>',
    ];
    $input = 'h-12 w-full min-w-0 rounded-[14px] border-[1.5px] bg-white px-3.5 text-sm text-navy outline-none focus:border-brand';
@endphp

<main class="mx-auto flex max-w-[1480px] flex-col gap-6 px-4 pt-6 sm:px-6 lg:px-8">
    <nav aria-label="breadcrumb" class="flex items-center gap-2 text-[13px] text-muted">
        <a href="{{ route('home') }}" wire:navigate class="text-muted hover:text-brand">{{ __('account.home') }}</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        <span class="font-semibold text-navy">{{ __('pages.contact.title') }}</span>
    </nav>

    <section class="relative grid grid-cols-1 items-center gap-8 overflow-hidden rounded-[28px] bg-navy px-6 py-8 text-white sm:px-[52px] sm:py-12 lg:grid-cols-2">
        <div class="pointer-events-none absolute -top-[160px] -right-[120px] size-[460px] rounded-full bg-[radial-gradient(circle,rgba(20,180,255,.28),transparent_65%)]"></div>
        <div class="relative flex flex-col gap-3.5">
            <span class="text-xs font-bold tracking-[0.8px] text-sky uppercase">{{ __('pages.contact.title') }}</span>
            <h1 class="m-0 font-sans text-[clamp(28px,3.2vw,40px)] leading-[1.2] font-extrabold normal-case">{{ __('pages.contact.heading') }}</h1>
            <p class="m-0 max-w-[440px] text-[15px] leading-relaxed text-faint">{{ __('pages.contact.text') }}</p>
        </div>
        <div class="relative grid grid-cols-1 gap-3 sm:grid-cols-3">
            @if (Site::phone())
                <a href="{{ Site::phoneHref() }}" class="flex flex-col gap-3.5 rounded-[20px] border border-white/10 bg-white/7 p-5 text-white hover:bg-white/12 hover:text-white">
                    <span class="grid size-12 place-items-center rounded-[14px] bg-brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></span>
                    <span class="flex flex-col gap-1"><span class="text-xs text-faint">{{ __('pages.contact.hotline') }}</span><span class="font-display text-[17px] font-extrabold">{{ Site::phone() }}</span></span>
                </a>
            @endif
            @if (Site::email())
                <a href="mailto:{{ Site::email() }}" class="flex flex-col gap-3.5 rounded-[20px] border border-white/10 bg-white/7 p-5 text-white hover:bg-white/12 hover:text-white">
                    <span class="grid size-12 place-items-center rounded-[14px] bg-brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18v12H3zM3 7l9 6 9-6"/></svg></span>
                    <span class="flex flex-col gap-1"><span class="text-xs text-faint">{{ __('pages.contact.email') }}</span><span class="font-display text-base font-extrabold break-all">{{ Site::email() }}</span></span>
                </a>
            @endif
            @if ($hours['week'] || $hours['sunday'])
                <div class="flex flex-col gap-3.5 rounded-[20px] border border-white/10 bg-white/7 p-5">
                    <span class="grid size-12 place-items-center rounded-[14px] bg-brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2"/></svg></span>
                    <span class="flex flex-col gap-1">
                        <span class="text-xs text-faint">{{ __('pages.contact.hours') }}</span>
                        @if ($hours['week'])
                            <span class="text-[15px] font-extrabold">{{ __('pages.contact.weekdays') }} {{ $hours['week'] }}</span>
                        @endif
                        @if ($hours['sunday'])
                            <span class="text-xs text-faint">{{ __('pages.contact.sunday') }} {{ $hours['sunday'] }}</span>
                        @endif
                    </span>
                </div>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 items-start gap-7 pb-2 lg:grid-cols-2">
        <div class="flex flex-col gap-3.5 px-1 py-2">
            <h2 class="m-0 font-sans text-2xl font-extrabold normal-case">{{ __('pages.contact.faq_title') }}</h2>
            <span class="max-w-[440px] text-[15px] leading-relaxed text-muted">{{ __('pages.contact.faq_text') }}</span>
            <div x-data="{ open: null }" class="mt-1 flex flex-col gap-2.5">
                @foreach ($faqs as $faq)
                    <div wire:key="faq-{{ $faq->id }}" class="rounded-[20px] border bg-white transition-colors" x-bind:class="open === {{ $faq->id }} ? 'border-brand' : 'border-line hover:border-brand'">
                        <button type="button" x-on:click="open = open === {{ $faq->id }} ? null : {{ $faq->id }}" x-bind:aria-expanded="open === {{ $faq->id }}" class="flex w-full items-center gap-3.5 px-5 py-[18px] text-left text-navy">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#EAF2FF] text-brand">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $faqIcons[$faq->icon] ?? $faqIcons['help'] !!}</svg>
                            </span>
                            <span class="flex-1 text-[15px] font-extrabold">{{ $faq->localized('question') }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8A97AE" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 transition-transform" x-bind:class="open === {{ $faq->id }} && 'rotate-90'"><path d="m9 6 6 6-6 6"/></svg>
                        </button>
                        <div x-show="open === {{ $faq->id }}" x-collapse x-cloak class="px-5 pb-5 text-sm leading-relaxed text-muted sm:pl-[78px]">{{ $faq->localized('answer') }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-3.5 rounded-[28px] border border-line bg-white p-5 sm:p-7">
            <span class="text-xl font-extrabold">{{ __('pages.contact.form_title') }}</span>
            @if ($sent)
                <div class="flex flex-1 flex-col items-center justify-center gap-3.5 px-5 py-10 text-center">
                    <span class="grid size-16 place-items-center rounded-full bg-[#E7F7EE] text-[#14804A]"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg></span>
                    <span class="text-lg font-extrabold">{{ __('pages.contact.sent_title') }}</span>
                    <span class="max-w-[300px] text-sm leading-normal text-muted">{{ __('pages.contact.sent_text') }}</span>
                    <button type="button" wire:click="startOver" class="text-sm font-bold text-brand">{{ __('pages.contact.new_message') }}</button>
                </div>
            @else
                <form wire:submit="send" class="flex flex-col gap-3.5">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (App\Models\ContactMessage::TOPICS as $topicKey)
                            <button type="button" wire:click="chooseTopic('{{ $topicKey }}')" @class([
                                'h-9 rounded-full border-[1.5px] px-3.5 text-[13px] font-bold',
                                'border-brand bg-brand text-white' => $topic === $topicKey,
                                'border-[#E3E9F3] bg-white text-navy hover:border-brand' => $topic !== $topicKey,
                            ])>{{ __("pages.contact.topics.$topicKey") }}</button>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <label class="flex min-w-0 flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                            {{ __('pages.contact.name') }}
                            <input wire:model="name" autocomplete="name" @class([$input, 'border-sale' => $errors->has('name'), 'border-[#E3E9F3]' => ! $errors->has('name')])>
                            @error('name')<span class="font-semibold text-sale">{{ $message }}</span>@enderror
                        </label>
                        <label class="flex min-w-0 flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                            {{ __('pages.contact.phone') }}
                            <input wire:model="phone" type="tel" autocomplete="tel" placeholder="5__ __ __ __" @class([$input, 'border-sale' => $errors->has('phone'), 'border-[#E3E9F3]' => ! $errors->has('phone')])>
                            @error('phone')<span class="font-semibold text-sale">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    @if ($this->needsOrder())
                        <label class="flex flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                            {{ __('pages.contact.order_number') }}
                            <input wire:model="orderNumber" placeholder="#EL-000000" class="{{ $input }} border-[#E3E9F3]">
                            @error('orderNumber')<span class="font-semibold text-sale">{{ $message }}</span>@enderror
                        </label>
                    @endif
                    <label class="flex flex-col gap-1.5 text-xs font-bold text-[#3A4A68]">
                        {{ __('pages.contact.message') }}
                        <textarea wire:model="message" rows="5" @class(['min-h-[130px] w-full resize-y rounded-[14px] border-[1.5px] bg-white px-3.5 py-3 text-sm text-navy outline-none focus:border-brand', 'border-sale' => $errors->has('message'), 'border-[#E3E9F3]' => ! $errors->has('message')])></textarea>
                        @error('message')<span class="font-semibold text-sale">{{ $message }}</span>@enderror
                    </label>
                    <button type="submit" wire:loading.attr="disabled" wire:target="send" class="flex h-[52px] items-center justify-center gap-2 rounded-[14px] bg-brand text-[15px] font-extrabold text-white hover:bg-brand-dark disabled:opacity-60">
                        {{ __('pages.contact.send') }}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                </form>
            @endif
        </div>
    </section>
</main>
