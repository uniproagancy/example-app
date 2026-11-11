@if (config('bog.sdk_client_id'))
    @script
    <script>
        const state = window.ElioBogCheckout ??= { bound: false, sdk: null };
        const sdkUrl = @js('https://webstatic.bog.ge/bog-sdk/bog-sdk.js?version=2&client_id='.config('bog.sdk_client_id'));
        const failedMessage = @js(__('payments.failed'));
        const failed = () => window.alert(failedMessage);

        const loadSdk = () => {
            if (window.BOG?.Calculator) return Promise.resolve(window.BOG);
            if (state.sdk) return state.sdk;

            state.sdk = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                const timeout = setTimeout(() => reject(new Error('BOG SDK did not load')), 15000);
                script.src = sdkUrl;
                script.async = true;
                script.addEventListener('load', () => { clearTimeout(timeout); window.BOG?.Calculator ? resolve(window.BOG) : reject(new Error('BOG SDK unavailable')); }, { once: true });
                script.addEventListener('error', () => { clearTimeout(timeout); reject(new Error('BOG SDK did not load')); }, { once: true });
                document.head.append(script);
            }).catch(error => { state.sdk = null; throw error; });

            return state.sdk;
        };

        const openCalculator = (data) => loadSdk().then(BOG => {
            BOG.Calculator.open({
                bnpl: Boolean(data.bnpl),
                amount: data.amount,
                onRequest: (selected, success, close) => {
                    fetch(data.url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify({ month: selected.month }),
                    })
                        .then(response => response.ok ? response.json() : Promise.reject(response))
                        .then(body => success(body.orderId))
                        .catch(() => { close(); failed(); });

                    return false;
                },
                onComplete: () => false,
            });
        }).catch(failed);

        if (! state.bound) {
            state.bound = true;
            Livewire.on('bog:installment', data => openCalculator(Array.isArray(data) ? data[0] : data));
        }
    </script>
    @endscript
@endif
