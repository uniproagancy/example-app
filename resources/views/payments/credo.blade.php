<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('payments.redirecting') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; background: #F4F6FA; color: #0A1633 }
        .wrap { text-align: center; padding: 24px }
        .spin { width: 34px; height: 34px; margin: 0 auto 16px; border-radius: 50%; border: 3px solid #E6EBF3; border-top-color: #0B63F6; animation: spin .8s linear infinite }
        @keyframes spin { to { transform: rotate(360deg) } }
        p { font-size: 14px; color: #5B6B86; margin: 0 0 18px }
        button { padding: 10px 20px; border: 0; border-radius: 10px; background: #0B63F6; color: #fff; font-size: 14px; cursor: pointer }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="spin" aria-hidden="true"></div>
        <p>{{ __('payments.redirecting_to', ['name' => $label]) }}</p>
        <form action="{{ $action }}" method="post" id="credoForm">
            <input type="hidden" name="{{ $field }}" value="{{ $data }}">
            <button type="submit">{{ __('payments.continue') }}</button>
        </form>
    </div>
    <script>document.getElementById('credoForm').submit();</script>
</body>
</html>
