@php($accounts = App\Support\Company::bankAccounts())

<div class="flex w-full flex-col gap-3 rounded-[18px] border-[1.5px] border-[#D5E2F7] bg-tint/40 px-5 py-[18px] text-left text-sm">
    <span class="font-extrabold text-navy">{{ __('payments.transfer.title') }}</span>
    <span class="text-muted">{{ __('payments.transfer.text', ['amount' => $amount]) }}</span>

    @if ($accounts)
        <div class="flex flex-col gap-2">
            @foreach ($accounts as $account)
                <div class="flex flex-col gap-0.5 rounded-xl bg-white px-3.5 py-2.5" x-data="{ copied: false }">
                    <span class="text-xs text-muted">{{ collect([$account['bank'], $account['currency']])->filter()->implode(' · ') }}</span>
                    <span class="flex items-center justify-between gap-2">
                        <span class="font-mono text-[13px] font-bold tracking-[0.3px] break-all text-navy">{{ trim(chunk_split($account['iban'], 4, ' ')) }}</span>
                        <button type="button" x-on:click="navigator.clipboard?.writeText(@js($account['iban'])); copied = true; setTimeout(() => copied = false, 1500)" class="shrink-0 p-0 text-xs font-bold text-brand">
                            <span x-show="! copied">{{ __('payments.transfer.copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('payments.transfer.copied') }}</span>
                        </button>
                    </span>
                </div>
            @endforeach
        </div>
        <span class="text-xs text-muted">{{ __('payments.transfer.purpose') }}: <strong class="text-navy">{{ __('invoice.purpose_text', ['number' => $invoiceNumber]) }}</strong></span>
    @endif

    <a href="{{ $invoiceUrl }}" target="_blank" rel="noopener" class="flex h-11 items-center justify-center gap-2 rounded-xl bg-navy px-4 text-sm font-bold text-white hover:bg-brand hover:text-white">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>
        {{ __('payments.transfer.invoice') }}
    </a>
</div>
