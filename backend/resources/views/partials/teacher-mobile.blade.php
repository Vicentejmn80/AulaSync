{{-- Estilos móviles compartidos para pantallas internas del docente --}}
<style>
    html { overflow-x: hidden; -webkit-text-size-adjust: 100%; }
    body { overflow-x: hidden; max-width: 100%; }
    input, select, textarea { font-size: 16px !important; }

    @media (max-width: 767px) {
        .wrap, .page, .max-w-6xl, .max-w-5xl {
            padding-left: 16px !important;
            padding-right: 16px !important;
        }
        .title, h1 { word-break: break-word; }
        .tabs, .type-tabs, .links-row {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            max-width: 100%;
        }
        .tabs::-webkit-scrollbar, .type-tabs::-webkit-scrollbar { display: none; }
        .tab, .type-tab, .chip-link { flex: 0 0 auto; white-space: nowrap; }
        .btn, .btn-gradient { min-height: 44px; }
        .hero-actions, .top-actions { width: 100%; }
        .hero-actions .btn, .top-actions .btn { width: 100%; justify-content: center; }
    }
</style>
