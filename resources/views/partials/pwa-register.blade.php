@once
    <script>
        window.addEventListener('load', () => {
            if (!('serviceWorker' in navigator) || !window.isSecureContext) {
                return;
            }

            navigator.serviceWorker.register(@json(asset('sw.js')), { scope: '/' }).catch((error) => {
                console.warn('Service worker registration failed.', error);
            });
        });
    </script>
@endonce
