{{-- Prefetch de las rutas calientes Landing → Login → Hub. No cachea HTML de auth. --}}
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
@if(!empty($prefetchLogin ?? true))
<link rel="prefetch" href="{{ url('/login') }}" as="document">
@endif
@if(!empty($prefetchHub ?? false))
<link rel="prefetch" href="{{ url('/teacher/hub') }}" as="document">
@endif
<script>
    (function () {
        var urls = @json($idlePrefetch ?? []);
        if (!urls.length) return;
        var run = function () {
            urls.forEach(function (href) {
                if (!href) return;
                var link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = href;
                link.as = 'document';
                document.head.appendChild(link);
            });
        };
        if ('requestIdleCallback' in window) requestIdleCallback(run, { timeout: 1800 });
        else setTimeout(run, 400);
    })();
</script>
