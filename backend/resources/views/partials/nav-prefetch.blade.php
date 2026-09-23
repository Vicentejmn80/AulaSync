{{-- DNS only. No document prefetch/prerender of /login: on mobile Chrome the tap waits until that request finishes. --}}
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
@if(!empty($prefetchHub ?? false))
<link rel="prefetch" href="{{ url('/teacher/hub') }}" as="document">
@endif
@if(!empty($prefetchLogin ?? false))
<script>
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).then(function (reg) {
            var pending = reg.waiting || reg.installing;
            if (pending) pending.postMessage({ type: 'SKIP_WAITING' });
            reg.addEventListener('updatefound', function () {
                var installing = reg.installing;
                if (!installing) return;
                installing.addEventListener('statechange', function () {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                        installing.postMessage({ type: 'SKIP_WAITING' });
                    }
                });
            });
        }).catch(function () {});
    }
</script>
@endif
