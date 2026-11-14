@if (App\Support\Consent::tracksVisitors() && App\Support\Consent::choice() === null)
    <div
        x-data="{
            open: ! window.elioConsent,
            choose(value) {
                document.cookie = 'cookie_consent=' + value + '; path=/; max-age=' + (60 * 60 * 24 * 180) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
                window.elioConsent = value;

                if (window.gtag) {
                    const state = value === 'granted' ? 'granted' : 'denied';
                    gtag('consent', 'update', { analytics_storage: state, ad_storage: state, ad_user_data: state, ad_personalization: state });
                }

                if (window.fbq) {
                    fbq('consent', value === 'granted' ? 'grant' : 'revoke');
                }

                this.open = false;
            },
        }"
        x-show="open"
        x-cloak
        role="dialog"
        aria-live="polite"
        aria-label="{{ __('shop.consent.title') }}"
        style="position:fixed;left:16px;right:16px;bottom:calc(80px + env(safe-area-inset-bottom));z-index:70;max-width:560px;margin:0 auto;padding:16px 18px;border-radius:18px;background:#0A1633;color:#fff;box-shadow:0 20px 50px -16px rgba(10,22,51,.6);font-size:14px;line-height:1.5"
    >
        <p style="margin:0 0 12px">
            {{ __('shop.consent.text') }}
            @if ($privacyUrl = App\Models\Page::link('privacy'))
                <a href="{{ $privacyUrl }}" style="color:#14B4FF;font-weight:700;text-decoration:underline">{{ __('shop.consent.more') }}</a>
            @endif
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" x-on:click="choose('granted')" style="border:0;border-radius:999px;padding:10px 20px;background:#0B63F6;color:#fff;font-weight:700;cursor:pointer">{{ __('shop.consent.accept') }}</button>
            <button type="button" x-on:click="choose('denied')" style="border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:10px 20px;background:transparent;color:#fff;font-weight:700;cursor:pointer">{{ __('shop.consent.decline') }}</button>
        </div>
    </div>
@endif
