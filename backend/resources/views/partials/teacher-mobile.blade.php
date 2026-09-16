{{-- Estilos móviles compartidos para pantallas internas del docente --}}
<style>
    html { -webkit-text-size-adjust: 100%; }
    body { max-width: 100%; }
    input, select, textarea { font-size: 16px !important; min-height: 48px; }
    button, .btn, a.btn, .btn-gradient { min-height: 48px; }

    @media (max-width: 767px) {
        .wrap, .page, .max-w-6xl, .max-w-5xl {
            padding-left: 16px !important;
            padding-right: 16px !important;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .title, h1 { word-break: break-word; }
        .tabs, .type-tabs, .links-row, table, .table-wrap, .calendar-grid {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        .tabs, .type-tabs, .links-row {
            display: flex;
            flex-wrap: nowrap;
            scrollbar-width: thin;
        }
        .tab, .type-tab, .chip-link { flex: 0 0 auto; white-space: nowrap; }
        .btn, .btn-gradient { min-height: 48px; }
        .hero-actions, .top-actions { width: 100%; overflow-x: auto; }
        .hero-actions .btn, .top-actions .btn { min-width: max-content; justify-content: center; }
    }
</style>
