<?php

use App\Models\PriceAlert;
use App\Support\Catalog;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $productId = 0;

    public string $contact = '';

    public bool $open = false;

    public bool $sent = false;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
        $this->contact = (string) (auth()->user()?->email ?? '');
    }

    public function subscribe(): void
    {
        $product = Catalog::find($this->productId);

        abort_unless($product, 404);

        $contact = trim($this->contact);
        $isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
        $isPhone = (bool) preg_match('/^\+?[0-9\s\-()]{9,20}$/', $contact);

        if (! $isEmail && ! $isPhone) {
            throw ValidationException::withMessages(['contact' => __('product.price_alert.invalid')]);
        }

        $throttleKey = 'price-alert:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            throw ValidationException::withMessages([
                'contact' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $contact = $isEmail ? mb_strtolower($contact) : preg_replace('/[^\d+]/', '', $contact);

        if (PriceAlert::query()->where('contact', $contact)->waiting()->count() >= PriceAlert::MAX_PER_CONTACT) {
            throw ValidationException::withMessages(['contact' => __('product.price_alert.too_many')]);
        }

        PriceAlert::query()->updateOrCreate(
            ['product_id' => $this->productId, 'contact' => $contact],
            [
                'user_id' => auth()->id(),
                'channel' => $isEmail ? 'mail' : 'sms',
                'price' => $product['price'],
                'notified_price' => null,
                'notified_at' => null,
                'locale' => app()->getLocale(),
            ],
        );

        $this->sent = true;
    }
};
?>

<div class="rounded-[18px] border border-line-mid bg-white px-5 py-4">
    @if ($sent)
        <p role="status" class="m-0 flex items-center gap-2.5 text-sm font-semibold text-[#14804A]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
            {{ __('product.price_alert.sent') }}
        </p>
    @elseif (! $open)
        <button type="button" wire:click="$set('open', true)" class="flex w-full items-center gap-3 p-0 text-left text-navy">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-tint text-brand">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </span>
            <span class="flex min-w-0 flex-col gap-0.5">
                <span class="text-sm font-extrabold">{{ __('product.price_alert.title') }}</span>
                <span class="text-xs text-muted">{{ __('product.price_alert.text') }}</span>
            </span>
        </button>
    @else
        <form wire:submit="subscribe" class="flex flex-col gap-2.5">
            <span class="text-sm font-extrabold text-navy">{{ __('product.price_alert.title') }}</span>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input wire:model="contact" type="text" inputmode="email" autocomplete="email" placeholder="{{ __('product.price_alert.placeholder') }}" aria-label="{{ __('product.price_alert.placeholder') }}" @class(['h-12 min-w-0 flex-1 rounded-xl border-[1.5px] bg-white px-3.5 text-sm text-navy outline-0 focus:border-brand', 'border-sale' => $errors->has('contact'), 'border-[#E3E9F3]' => ! $errors->has('contact')])>
                <button type="submit" wire:loading.attr="disabled" class="h-12 shrink-0 rounded-xl bg-navy px-5 text-sm font-extrabold text-white hover:bg-brand disabled:opacity-60">{{ __('product.price_alert.submit') }}</button>
            </div>
            @error('contact') <span class="text-xs font-semibold text-sale">{{ $message }}</span> @enderror
            <span class="text-xs text-muted">{{ __('product.price_alert.hint') }}</span>
        </form>
    @endif
</div>
