@php
    use App\Support\Catalog;
    use App\Support\Company;

    $seller = Company::details();
    $accounts = Company::bankAccounts();
    $note = Company::invoiceNote();
    $stamp = Company::stampUrl();
    $single = $orders->count() === 1;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('invoice.title') }} — {{ $orders->map->invoiceNumber()->implode(', ') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #0a1633; font: 13px/1.45 "Segoe UI", "Noto Sans Georgian", "Sylfaen", Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; align-items: center; padding: 12px; background: #0a1633; }
        .toolbar button { border: 0; border-radius: 999px; padding: 9px 18px; font: inherit; font-weight: 700; cursor: pointer; background: #0b63f6; color: #fff; }
        .toolbar button.secondary { background: #fff; color: #0a1633; }
        .toolbar input { width: 190px; border: 0; border-radius: 999px; padding: 9px 14px; font: inherit; }
        .toolbar small { flex-basis: 100%; text-align: center; color: #9fb0cf; }
        .sheet { width: 210mm; min-height: 287mm; margin: 16px auto; padding: 14mm; background: #fff; box-shadow: 0 8px 30px rgba(10, 22, 51, .12); display: flex; flex-direction: column; }
        .head { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid #0a1633; }
        .brand { font-size: 22px; font-weight: 800; }
        .muted { color: #5b6b86; }
        .number { text-align: right; }
        .number strong { display: block; font-size: 22px; letter-spacing: .3px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 16px 0; }
        .box { border: 1px solid #d8e0ec; border-radius: 8px; padding: 10px 12px; }
        .box h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #5b6b86; }
        .box p { margin: 2px 0; }
        [data-buyer]:empty { display: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e3e9f2; text-align: left; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #5b6b86; background: #f4f7fb; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .totals { width: 300px; margin: 12px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totals .total { margin-top: 4px; padding-top: 6px; border-top: 2px solid #0a1633; font-size: 16px; font-weight: 800; }
        .state { margin-top: 6px; text-align: right; font-weight: 800; }
        .state.paid { color: #14804a; }
        .state.unpaid { color: #b36b00; }
        .bank { margin-top: 18px; }
        .bank h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #5b6b86; }
        .bank td { font-size: 12.5px; }
        .iban { font-family: Consolas, "Courier New", monospace; font-weight: 700; letter-spacing: .4px; }
        .note { margin-top: 12px; padding: 8px 12px; background: #f4f7fb; border-radius: 8px; white-space: pre-line; }
        .foot { margin-top: auto; padding-top: 36px; display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; }
        .sign { min-width: 220px; border-top: 1px solid #0a1633; padding-top: 6px; color: #5b6b86; font-size: 12px; }
        .stamp { max-height: 110px; max-width: 200px; object-fit: contain; }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
        }
        @media (max-width: 820px) {
            .sheet { width: auto; min-height: 0; margin: 10px; padding: 18px; }
            .grid { grid-template-columns: 1fr; }
            .head { flex-direction: column; }
            .number { text-align: left; }
            .totals { width: 100%; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        @if ($single)
            <input type="text" data-buyer-input="company" placeholder="{{ __('invoice.buyer_company') }}" maxlength="120" aria-label="{{ __('invoice.buyer_company') }}">
            <input type="text" data-buyer-input="code" placeholder="{{ __('invoice.buyer_code') }}" maxlength="20" aria-label="{{ __('invoice.buyer_code') }}">
        @endif
        <button type="button" onclick="window.print()">{{ __('invoice.print') }}</button>
        @if ($staff)
            <button type="button" class="secondary" onclick="window.close()">{{ __('invoice.close') }}</button>
        @endif
        @if ($single)
            <small>{{ __('invoice.buyer_hint') }}</small>
        @endif
    </div>

    @foreach ($orders as $order)
        @php
            $address = collect([$order->city ? __("checkout.cities.{$order->city}") : null, $order->address, $order->apartment])->filter()->implode(', ');
        @endphp
        <section class="sheet">
            <div class="head">
                <div>
                    <div class="brand">{{ $seller['name'] }}</div>
                    <div class="muted">{{ collect([$seller['phone'], $seller['email']])->filter()->implode(' · ') }}</div>
                </div>
                <div class="number">
                    <span class="muted">{{ __('invoice.number') }}</span>
                    <strong>{{ $order->invoiceNumber() }}</strong>
                    <span class="muted">{{ __('invoice.date') }}: {{ $order->created_at->format('d.m.Y') }} · {{ __('invoice.order') }} #{{ $order->number }}</span>
                </div>
            </div>

            <div class="grid">
                <div class="box">
                    <h3>{{ __('invoice.seller') }}</h3>
                    <p><strong>{{ $seller['name'] }}</strong></p>
                    @if ($seller['code'])
                        <p>{{ __('invoice.code') }}: {{ $seller['code'] }}</p>
                    @endif
                    @if ($seller['address'])
                        <p>{{ __('invoice.address') }}: {{ $seller['address'] }}</p>
                    @endif
                    @if ($seller['director'])
                        <p class="muted">{{ __('invoice.director') }}: {{ $seller['director'] }}</p>
                    @endif
                </div>
                <div class="box">
                    <h3>{{ __('invoice.buyer') }}</h3>
                    <p><strong data-buyer="company"></strong></p>
                    <p data-buyer-line="code" hidden>{{ __('invoice.code') }}: <span data-buyer="code"></span></p>
                    <p><strong>{{ trim($order->first_name.' '.$order->last_name) }}</strong></p>
                    <p>{{ __('invoice.phone') }}: {{ $order->phone }}</p>
                    @if ($order->email)
                        <p class="muted">{{ $order->email }}</p>
                    @endif
                    @if ($address)
                        <p class="muted">{{ $address }}</p>
                    @endif
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 32px">#</th>
                            <th>{{ __('invoice.product') }}</th>
                            <th class="num">{{ __('invoice.quantity') }}</th>
                            <th class="num">{{ __('invoice.price') }}</th>
                            <th class="num">{{ __('invoice.sum') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{ $item->name }}
                                    @if ($item->options)
                                        <div class="muted">{{ implode(' · ', (array) $item->options) }}</div>
                                    @endif
                                </td>
                                <td class="num">{{ $item->quantity }}</td>
                                <td class="num">{{ Catalog::money($item->unit_price) }}</td>
                                <td class="num">{{ Catalog::money($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="totals">
                <div><span>{{ __('invoice.subtotal') }}</span><span>{{ Catalog::money($order->subtotal) }}</span></div>
                @if ((float) $order->discount > 0)
                    <div><span>{{ __('invoice.discount') }}@if ($order->promo_code) ({{ $order->promo_code }})@endif</span><span>−{{ Catalog::money($order->discount) }}</span></div>
                @endif
                <div><span>{{ __('invoice.shipping') }}</span><span>{{ (float) $order->shipping > 0 ? Catalog::money($order->shipping) : __('invoice.free') }}</span></div>
                <div class="total"><span>{{ __('invoice.total') }}</span><span>{{ Catalog::money($order->total) }}</span></div>
            </div>
            <div @class(['state', 'paid' => $order->isPaid(), 'unpaid' => ! $order->isPaid()])>
                {{ $order->isPaid() ? __('invoice.paid').($order->paid_at ? ' · '.$order->paid_at->format('d.m.Y') : '') : __('invoice.unpaid') }}
            </div>

            @if ($accounts)
                <div class="bank">
                    <h3>{{ __('invoice.bank_details') }}</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('invoice.bank') }}</th>
                                <th>IBAN</th>
                                <th>SWIFT</th>
                                <th>{{ __('invoice.currency') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    <td>{{ $account['bank'] }}</td>
                                    <td class="iban">{{ trim(chunk_split($account['iban'], 4, ' ')) }}</td>
                                    <td>{{ $account['swift'] }}</td>
                                    <td>{{ $account['currency'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="muted" style="margin: 8px 0 0">{{ __('invoice.purpose') }}: <strong>{{ __('invoice.purpose_text', ['number' => $order->invoiceNumber()]) }}</strong></p>
                </div>
            @endif

            @if ($note)
                <div class="note">{{ $note }}</div>
            @endif

            <div class="foot">
                <div class="sign">{{ __('invoice.signature') }}@if ($seller['director']) — {{ $seller['director'] }}@endif</div>
                @if ($stamp)
                    <img src="{{ $stamp }}" alt="" class="stamp">
                @endif
            </div>
        </section>
    @endforeach

    <script>
        document.querySelectorAll('[data-buyer-input]').forEach((input) => {
            input.addEventListener('input', () => {
                const field = input.dataset.buyerInput;
                const value = input.value.trim();
                document.querySelectorAll(`[data-buyer="${field}"]`).forEach((node) => { node.textContent = value; });
                document.querySelectorAll(`[data-buyer-line="${field}"]`).forEach((node) => { node.hidden = value === ''; });
            });
        });
    </script>
</body>
</html>
