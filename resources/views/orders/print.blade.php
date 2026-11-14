<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('admin.print.title') }} — {{ $orders->pluck('number')->implode(', ') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #0a1633; font: 13px/1.45 "Segoe UI", "Noto Sans Georgian", "Sylfaen", Arial, sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 12px; background: #0a1633; }
        .toolbar button { border: 0; border-radius: 999px; padding: 9px 18px; font: inherit; font-weight: 700; cursor: pointer; background: #0b63f6; color: #fff; }
        .toolbar button.secondary { background: #fff; color: #0a1633; }
        .slip { width: 210mm; min-height: 287mm; margin: 16px auto; padding: 14mm 14mm 12mm; background: #fff; box-shadow: 0 8px 30px rgba(10, 22, 51, .12); }
        .head { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 12px; border-bottom: 2px solid #0a1633; }
        .brand { font-size: 22px; font-weight: 800; letter-spacing: .3px; }
        .muted { color: #5b6b86; }
        .number { text-align: right; }
        .number strong { display: block; font-size: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 16px 0; }
        .box { border: 1px solid #d8e0ec; border-radius: 8px; padding: 10px 12px; }
        .box h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #5b6b86; }
        .box p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e3e9f2; text-align: left; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #5b6b86; background: #f4f7fb; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin: 12px 0 0 auto; }
        .totals div { display: flex; justify-content: space-between; padding: 3px 0; }
        .totals .total { margin-top: 4px; padding-top: 6px; border-top: 2px solid #0a1633; font-size: 16px; font-weight: 800; }
        .collect { margin-top: 14px; padding: 12px 14px; border: 2px dashed #0a1633; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 16px; font-weight: 800; }
        .collect.paid { border-color: #14804a; color: #14804a; }
        .note { margin-top: 12px; padding: 8px 12px; background: #fff8e6; border-radius: 8px; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 46px; }
        .signatures div { border-top: 1px solid #0a1633; padding-top: 6px; color: #5b6b86; font-size: 12px; }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .slip { margin: 0; box-shadow: none; page-break-after: always; }
            .slip:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('admin.print.print') }}</button>
        <button type="button" class="secondary" onclick="window.close()">{{ __('admin.print.close') }}</button>
    </div>

    @foreach ($orders as $order)
        @php
            $address = collect([$order->city ? __("checkout.cities.{$order->city}") : null, $order->address, $order->apartment])->filter()->implode(', ');
            $toCollect = $order->isPaid() ? 0 : (float) $order->total;
        @endphp
        <section class="slip">
            <div class="head">
                <div>
                    <div class="brand">{{ App\Support\Site::name() }}</div>
                    <div class="muted">{{ collect([App\Support\Site::phone(), App\Support\Site::email()])->filter()->implode(' · ') }}</div>
                </div>
                <div class="number">
                    <span class="muted">{{ __('admin.print.order') }}</span>
                    <strong>{{ $order->number }}</strong>
                    <span class="muted">{{ $order->created_at->format('d.m.Y H:i') }}</span>
                </div>
            </div>

            <div class="grid">
                <div class="box">
                    <h3>{{ __('admin.print.customer') }}</h3>
                    <p><strong>{{ trim($order->first_name.' '.$order->last_name) }}</strong></p>
                    <p>{{ $order->phone }}</p>
                    @if ($order->email)
                        <p class="muted">{{ $order->email }}</p>
                    @endif
                </div>
                <div class="box">
                    <h3>{{ __('admin.print.delivery') }}</h3>
                    <p><strong>{{ $address ?: __('admin.print.no_address') }}</strong></p>
                    <p class="muted">{{ __('admin.print.payment') }}: {{ $order->paymentLabel() ?? $order->payment_method }} · {{ __("payments.statuses.{$order->payment_status}") }}</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 32px">#</th>
                        <th>{{ __('admin.print.product') }}</th>
                        <th class="num">{{ __('admin.print.quantity') }}</th>
                        <th class="num">{{ __('admin.print.price') }}</th>
                        <th class="num">{{ __('admin.print.sum') }}</th>
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
                            <td class="num">{{ App\Support\Catalog::money($item->unit_price) }}</td>
                            <td class="num">{{ App\Support\Catalog::money($item->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <div><span>{{ __('admin.print.subtotal') }}</span><span>{{ App\Support\Catalog::money($order->subtotal) }}</span></div>
                @if ((float) $order->discount > 0)
                    <div><span>{{ __('admin.print.discount') }}@if ($order->promo_code) ({{ $order->promo_code }})@endif</span><span>−{{ App\Support\Catalog::money($order->discount) }}</span></div>
                @endif
                <div><span>{{ __('admin.print.shipping') }}</span><span>{{ (float) $order->shipping > 0 ? App\Support\Catalog::money($order->shipping) : __('admin.orders.free') }}</span></div>
                <div class="total"><span>{{ __('admin.print.total') }}</span><span>{{ App\Support\Catalog::money($order->total) }}</span></div>
            </div>

            <div @class(['collect', 'paid' => $toCollect <= 0])>
                <span>{{ __('admin.orders.export.to_collect') }}</span>
                <span>{{ $toCollect > 0 ? App\Support\Catalog::money($toCollect) : __('admin.print.already_paid') }}</span>
            </div>

            @if ($order->note)
                <div class="note"><strong>{{ __('admin.print.note') }}:</strong> {{ $order->note }}</div>
            @endif

            <div class="signatures">
                <div>{{ __('admin.print.courier_signature') }}</div>
                <div>{{ __('admin.print.customer_signature') }}</div>
            </div>
        </section>
    @endforeach

    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
</body>
</html>
