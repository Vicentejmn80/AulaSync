<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Boletas Inteligentes · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display:none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background:var(--bg-primary); color:var(--text-primary); }
        .glass { background:linear-gradient(145deg,rgba(255,255,255,.105),rgba(255,255,255,.035)); border:1px solid rgba(255,255,255,.14); box-shadow:0 24px 80px rgba(0,0,0,.28); backdrop-filter:blur(22px); }
        .btn-primary { background:linear-gradient(135deg,#7c3aed,#06b6d4); color:#fff; font-weight:700; border-radius:.875rem; padding:.6rem 1.25rem; font-size:.85rem; cursor:pointer; transition:opacity .15s; }
        .btn-primary:hover { opacity:.88; }
        .btn-secondary { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.14); color:#e2e8f0; font-weight:600; border-radius:.875rem; padding:.6rem 1.25rem; font-size:.85rem; cursor:pointer; transition:opacity .15s; }
        .btn-secondary:hover { opacity:.8; }
        .badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .7rem; border-radius:9999px; font-size:.72rem; font-weight:700; }
        .badge-draft    { background:rgba(148,163,184,.15); color:#94a3b8; }
        .badge-active   { background:rgba(52,211,153,.15);  color:#34d399; }
        .badge-closed   { background:rgba(239,68,68,.12);   color:#f87171; }
        .badge-pub      { background:rgba(52,211,153,.15);  color:#34d399; }
        .badge-draft-rc { background:rgba(248,212,80,.12);  color:#fbbf24; }
        table { border-collapse:collapse; width:100%; }
        thead th { font-size:.7rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#64748b; padding:.75rem 1rem; text-align:left; border-bottom:1px solid rgba(255,255,255,.08); }
        tbody tr { border-bottom:1px solid rgba(255,255,255,.05); transition:background .12s; }
        tbody tr:hover { background:rgba(255,255,255,.035); }
        tbody td { padding:.7rem 1rem; font-size:.85rem; }
        .fixed-right-panel { position:fixed; top:0; right:0; height:100vh; width:min(480px,95vw); background:#0f172a; border-left:1px solid rgba(255,255,255,.1); box-shadow:-32px 0 80px rgba(0,0,0,.5); z-index:50; transform:translateX(100%); transition:transform .3s cubic-bezier(.4,0,.2,1); }
        .fixed-right-panel.open { transform:translateX(0); }
        .overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:40; }
        input[type=text],input[type=date],textarea,select { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); border-radius:.75rem; color:#e2e8f0; padding:.6rem 1rem; font-size:.875rem; width:100%; outline:none; }
        input:focus,textarea:focus,select:focus { border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.2); }
        .tab-btn { padding:.55rem 1.1rem; border-radius:.75rem; font-size:.82rem; font-weight:700; cursor:pointer; transition:.15s; color:#94a3b8; }
        .tab-btn.active { background:rgba(124,58,237,.2); color:#a78bfa; }
        .grade-cell { text-align:center; font-size:.78rem; font-weight:700; padding:.5rem .4rem; }
        .grade-a  { color:#34d399; }
        .grade-b  { color:#60a5fa; }
        .grade-c  { color:#fbbf24; }
        .grade-d  { color:#fb923c; }
        .grade-f  { color:#f87171; }
        .toast-bar { position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%); z-index:200; background:#1e293b; border:1px solid rgba(255,255,255,.12); border-radius:1rem; padding:.75rem 1.5rem; font-size:.85rem; color:#e2e8f0; box-shadow:0 16px 48px rgba(0,0,0,.4); display:flex; align-items:center; gap:.7rem; }
        .spinner { width:18px; height:18px; border:2px solid rgba(255,255,255,.2); border-top-color:#a78bfa; border-radius:50%; animation:spin .7s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen">

<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
    <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
    <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
    <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-indigo-600/20 blur-[100px]"></div>
</div>

<div x-data="boletasApp()" x-init="init()" class="mx-auto max-w-7xl px-4 py-6 lg:px-8">

    {{-- Toast --}}
    <div x-show="toast.show" x-cloak x-transition class="toast-bar">
        <i :class="toast.icon" class="text-sm"></i>
        <span x-text="toast.msg"></span>
    </div>

    {{-- Header --}}
    <header class="mb-6 flex flex-col gap-4 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('director.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                <i class="fa-solid fa-arrow-left text-white"></i>
            </a>
            <div>
                <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-300">Control académico</p>
                <h1 class="mt-1 text-2xl font-black text-white">Boletas Inteligentes</h1>
                <p class="text-sm text-slate-400">Períodos, acumulados y publicación oficial para representantes.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @include('components.user-control-panel')
            <button @click="openNewPeriodPanel()" class="btn-primary flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i> Nuevo período
            </button>
        </div>
    </header>

    {{-- Tabs --}}
    <div class="mb-6 flex gap-2 rounded-2xl border border-white/10 bg-white/[.03] p-1.5">
        <button class="tab-btn" :class="{ active: tab === 'periods' }" @click="tab = 'periods'">
            <i class="fa-regular fa-calendar mr-1.5"></i> Períodos
        </button>
        <button class="tab-btn" :class="{ active: tab === 'acumulados' }" @click="tab = 'acumulados'; loadSummary()"
                :disabled="!selectedPeriod">
            <i class="fa-solid fa-table-cells mr-1.5"></i> Acumulados
        </button>
        <button class="tab-btn" :class="{ active: tab === 'boletas' }" @click="tab = 'boletas'; loadCards()"
                :disabled="!selectedPeriod">
            <i class="fa-solid fa-file-invoice mr-1.5"></i> Boletas
        </button>
        <span class="flex-1"></span>
        <div x-show="selectedPeriod" class="flex items-center gap-2 px-2">
            <span class="text-xs text-slate-400">Período activo:</span>
            <span class="badge badge-active" x-text="selectedPeriod?.name"></span>
        </div>
    </div>

    {{-- ═══ TAB: Períodos ════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'periods'" x-cloak>
        <div x-show="loading.periods" class="flex justify-center py-16">
            <div class="spinner" style="width:36px;height:36px;border-width:3px;"></div>
        </div>
        <div x-show="!loading.periods" class="glass overflow-hidden rounded-[2rem]">
            <div class="p-5 border-b border-white/10 flex items-center justify-between">
                <h2 class="font-bold text-white text-lg">Períodos académicos</h2>
                <span class="text-sm text-slate-400" x-text="periods.length + ' períodos'"></span>
            </div>

            <div x-show="periods.length === 0" class="py-16 text-center text-slate-400">
                <i class="fa-regular fa-calendar-xmark text-4xl mb-3 block opacity-40"></i>
                <p class="font-semibold">Aún no tienes períodos creados.</p>
                <p class="text-sm mt-1">Crea el primer lapso para empezar a gestionar boletas.</p>
                <button @click="openNewPeriodPanel()" class="btn-primary mt-4 inline-flex items-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i> Crear primer período
                </button>
            </div>

            <table x-show="periods.length > 0">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Entrega</th>
                        <th>Estado</th>
                        <th>Boletas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="period in periods" :key="period.id">
                        <tr>
                            <td>
                                <div class="font-bold text-white" x-text="period.name"></div>
                            </td>
                            <td class="text-slate-300" x-text="fmtDate(period.start_date)"></td>
                            <td class="text-slate-300" x-text="fmtDate(period.end_date)"></td>
                            <td class="text-slate-400 text-xs" x-text="period.report_card_due_date ? fmtDate(period.report_card_due_date) : '—'"></td>
                            <td>
                                <span class="badge" :class="period.status === 'active' ? 'badge-active' : 'badge-closed'"
                                      x-text="period.status === 'active' ? 'Activo' : 'Cerrado'"></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-300 font-bold" x-text="period.published_count"></span>
                                    <span class="text-slate-500 text-xs">/ <span x-text="period.report_cards_count"></span> pub.</span>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <button @click="selectPeriod(period); tab = 'acumulados'; loadSummary()"
                                            class="text-xs font-bold text-cyan-400 hover:text-cyan-300 transition-colors">
                                        <i class="fa-solid fa-table-cells mr-1"></i> Ver acumulados
                                    </button>
                                    <button @click="openEditPeriodPanel(period)"
                                            class="text-xs font-bold text-violet-400 hover:text-violet-300 transition-colors">
                                        <i class="fa-solid fa-pen mr-1"></i> Editar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ TAB: Acumulados ══════════════════════════════════════════════════ --}}
    <div x-show="tab === 'acumulados'" x-cloak>
        <div x-show="!selectedPeriod" class="glass rounded-[2rem] p-16 text-center text-slate-400">
            <i class="fa-solid fa-hand-pointer text-4xl mb-3 block opacity-30"></i>
            <p class="font-semibold">Selecciona un período para ver los acumulados.</p>
        </div>

        <template x-if="selectedPeriod">
            <div>
                {{-- Period header bar --}}
                <div class="glass rounded-2xl p-4 mb-5 flex flex-wrap items-center gap-4 justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-cyan-400 flex items-center justify-center">
                            <i class="fa-solid fa-chart-line text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-semibold">Acumulados en vivo</p>
                            <p class="text-white font-bold" x-text="selectedPeriod.name"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button @click="loadSummary()" class="btn-secondary flex items-center gap-2 text-xs">
                            <i class="fa-solid fa-rotate-right text-xs"></i> Actualizar
                        </button>
                        <button @click="generateCards()" :disabled="generating"
                                class="btn-primary flex items-center gap-2">
                            <div x-show="generating" class="spinner"></div>
                            <i x-show="!generating" class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                            <span x-text="generating ? 'Generando…' : 'Generar boletas'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="loading.summary" class="flex justify-center py-16">
                    <div class="spinner" style="width:36px;height:36px;border-width:3px;"></div>
                </div>

                {{-- Summary stats --}}
                <div x-show="!loading.summary && summary" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                    <div class="glass rounded-2xl p-4 text-center">
                        <p class="text-2xl font-black text-violet-400" x-text="summary?.rows?.length ?? 0"></p>
                        <p class="text-xs text-slate-400 mt-1">Estudiantes</p>
                    </div>
                    <div class="glass rounded-2xl p-4 text-center">
                        <p class="text-2xl font-black text-cyan-400" x-text="summary?.columns?.length ?? 0"></p>
                        <p class="text-xs text-slate-400 mt-1">Materias</p>
                    </div>
                    <div class="glass rounded-2xl p-4 text-center">
                        <p class="text-2xl font-black text-green-400"
                           x-text="avgGlobal() !== null ? avgGlobal() + '%' : '—'"></p>
                        <p class="text-xs text-slate-400 mt-1">Promedio global</p>
                    </div>
                    <div class="glass rounded-2xl p-4 text-center">
                        <p class="text-2xl font-black text-amber-400" x-text="atRisk()"></p>
                        <p class="text-xs text-slate-400 mt-1">En riesgo (&lt;70%)</p>
                    </div>
                </div>

                {{-- Grades matrix table --}}
                <div x-show="!loading.summary && summary" class="glass rounded-[2rem] overflow-x-auto">
                    <div class="p-5 border-b border-white/10 flex items-center gap-3">
                        <i class="fa-solid fa-table text-violet-400"></i>
                        <h3 class="font-bold text-white">Matriz de notas acumuladas</h3>
                        <span class="text-xs text-slate-400">(calculado desde las actividades del período)</span>
                    </div>
                    <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                        <table>
                            <thead class="sticky top-0 bg-slate-900/90 backdrop-blur">
                                <tr>
                                    <th style="min-width:180px">Estudiante</th>
                                    <th style="min-width:80px">Grado</th>
                                    <template x-for="col in (summary?.columns ?? [])" :key="col.id">
                                        <th style="min-width:100px;text-align:center;" x-text="col.name"></th>
                                    </template>
                                    <th style="min-width:90px;text-align:center;">Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in (summary?.rows ?? [])" :key="row.student_id">
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 flex items-center justify-center text-white text-xs font-black"
                                                     x-text="row.student_name.charAt(0)"></div>
                                                <span class="font-semibold text-white" x-text="row.student_name"></span>
                                            </div>
                                        </td>
                                        <td class="text-slate-400 text-xs" x-text="(row.grade ?? '') + ' ' + (row.section ?? '')"></td>
                                        <template x-for="col in (summary?.columns ?? [])" :key="col.id">
                                            <td class="grade-cell">
                                                <template x-if="findCourse(row, col.id)">
                                                    <div>
                                                        <span :class="gradeColor(findCourse(row, col.id)?.average)"
                                                              x-text="findCourse(row, col.id)?.average !== null ? findCourse(row, col.id)?.average?.toFixed(1) + '%' : '—'"></span>
                                                        <div class="text-slate-500 text-[.65rem]"
                                                             x-text="findCourse(row, col.id)?.average !== null ? findCourse(row, col.id)?.letter : ''"></div>
                                                    </div>
                                                </template>
                                                <template x-if="!findCourse(row, col.id)">
                                                    <span class="text-slate-600">—</span>
                                                </template>
                                            </td>
                                        </template>
                                        <td class="grade-cell">
                                            <span :class="gradeColor(row.global_average)"
                                                  class="font-black"
                                                  x-text="row.global_average !== null ? row.global_average + '%' : '—'"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ═══ TAB: Boletas ═════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'boletas'" x-cloak>
        <div x-show="!selectedPeriod" class="glass rounded-[2rem] p-16 text-center text-slate-400">
            <i class="fa-solid fa-hand-pointer text-4xl mb-3 block opacity-30"></i>
            <p class="font-semibold">Selecciona un período para gestionar boletas.</p>
        </div>

        <template x-if="selectedPeriod">
            <div>
                {{-- Action bar --}}
                <div class="glass rounded-2xl p-4 mb-5 flex flex-wrap items-center gap-3 justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-file-invoice text-violet-400 text-lg"></i>
                        <div>
                            <p class="text-xs text-slate-400">Boletas del período</p>
                            <p class="text-white font-bold" x-text="selectedPeriod.name"></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a :href="'/director/api/periods/' + selectedPeriod.id + '/export-pdf'"
                           class="btn-secondary flex items-center gap-1.5 text-xs no-underline">
                            <i class="fa-solid fa-file-pdf text-red-400"></i> Exportar todo PDF
                        </a>
                        <button @click="publishAll()" :disabled="publishing"
                                class="btn-primary flex items-center gap-2">
                            <div x-show="publishing" class="spinner"></div>
                            <i x-show="!publishing" class="fa-solid fa-bullhorn text-xs"></i>
                            <span x-text="publishing ? 'Publicando…' : 'Publicar todos'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="loading.cards" class="flex justify-center py-16">
                    <div class="spinner" style="width:36px;height:36px;border-width:3px;"></div>
                </div>

                <div x-show="!loading.cards && cards.length === 0" class="glass rounded-[2rem] p-16 text-center text-slate-400">
                    <i class="fa-solid fa-file-circle-question text-4xl mb-3 block opacity-30"></i>
                    <p class="font-semibold">No hay boletas generadas para este período.</p>
                    <p class="text-sm mt-1">Ve a la pestaña "Acumulados" y haz clic en "Generar boletas".</p>
                </div>

                <div x-show="!loading.cards && cards.length > 0" class="glass overflow-hidden rounded-[2rem]">
                    <table>
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Grado</th>
                                <th>Promedio</th>
                                <th>Estado</th>
                                <th>Generada</th>
                                <th>Publicada</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="card in cards" :key="card.id">
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-cyan-400 flex items-center justify-center text-white text-xs font-black"
                                                 x-text="card.student?.name?.charAt(0) ?? '?'"></div>
                                            <span class="font-semibold text-white" x-text="card.student?.name ?? '—'"></span>
                                        </div>
                                    </td>
                                    <td class="text-slate-400 text-xs"
                                        x-text="(card.student?.grade ?? '') + ' ' + (card.student?.section ?? '')"></td>
                                    <td>
                                        <span :class="gradeColor(card.global_average)" class="font-bold"
                                              x-text="card.global_average !== null ? card.global_average + '%' : '—'"></span>
                                    </td>
                                    <td>
                                        <span class="badge"
                                              :class="card.status === 'published' ? 'badge-pub' : 'badge-draft-rc'"
                                              x-text="card.status_label"></span>
                                    </td>
                                    <td class="text-slate-400 text-xs" x-text="card.generated_at ?? '—'"></td>
                                    <td class="text-slate-400 text-xs" x-text="card.published_at ?? '—'"></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <button @click="openCardEdit(card.id)"
                                                    class="text-xs font-bold text-violet-400 hover:text-violet-300 transition-colors">
                                                <i class="fa-solid fa-pen mr-1"></i> Editar
                                            </button>
                                            <a :href="'/director/api/report-cards/' + card.id + '/pdf'"
                                               class="text-xs font-bold text-red-400 hover:text-red-300 transition-colors no-underline">
                                                <i class="fa-solid fa-file-pdf mr-1"></i> PDF
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- ═══ RIGHT PANEL: New / Edit Period ════════════════════════════════════════ --}}
<div x-data x-show="$store.panel.open" x-cloak @click.self="$store.panel.close()" class="overlay"></div>
<div :class="$store.panel.open ? 'open' : ''" class="fixed-right-panel flex flex-col"
     x-data x-show="$store.panel.open" x-cloak>
    <div class="flex items-center justify-between p-5 border-b border-white/10">
        <h3 class="font-black text-white text-lg" x-text="$store.panel.title"></h3>
        <button @click="$store.panel.close()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-300">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto p-5" x-html="$store.panel.content"></div>
</div>

{{-- ═══ MODAL: Edit Card ══════════════════════════════════════════════════════ --}}
<div x-show="modal.open" x-cloak @click.self="modal.open = false"
     class="fixed inset-0 z-50 flex items-start justify-center pt-16 bg-black/60 backdrop-blur-sm overflow-y-auto px-4 pb-8">
    <div @click.stop class="w-full max-w-3xl glass rounded-[2rem] p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-xs text-slate-400 font-semibold">Boleta individual</p>
                <h3 class="text-xl font-black text-white" x-text="modal.card?.student?.name ?? 'Boleta'"></h3>
                <p class="text-xs text-slate-400 mt-0.5"
                   x-text="(modal.card?.student?.grade ?? '') + ' ' + (modal.card?.student?.section ?? '') + ' · ' + (modal.card?.period?.name ?? '')"></p>
            </div>
            <button @click="modal.open = false" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-slate-300">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div x-show="modal.loading" class="flex justify-center py-10">
            <div class="spinner" style="width:32px;height:32px;border-width:3px;"></div>
        </div>

        <template x-if="!modal.loading && modal.card">
            <div>
                {{-- Status badge --}}
                <div class="mb-4 flex items-center gap-3">
                    <span class="badge" :class="modal.card.status === 'published' ? 'badge-pub' : 'badge-draft-rc'"
                          x-text="modal.card.status === 'published' ? 'Publicada' : 'Borrador'"></span>
                    <span class="text-sm text-slate-400">
                        Promedio global: <strong :class="gradeColor(modal.card.global_average)"
                                                  x-text="modal.card.global_average + '%'"></strong>
                    </span>
                </div>

                {{-- Grades table --}}
                <div class="rounded-2xl border border-white/10 overflow-hidden mb-5">
                    <table>
                        <thead>
                            <tr>
                                <th>Materia</th>
                                <th style="width:110px;text-align:center;">Nota (0-100)</th>
                                <th style="width:70px;text-align:center;">Literal</th>
                                <th>Observación del profesor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(grade, idx) in modal.editGrades" :key="grade.course_id">
                                <tr>
                                    <td class="font-semibold text-white text-sm" x-text="grade.course_name"></td>
                                    <td class="grade-cell">
                                        <input type="number" min="0" max="100" step="0.01"
                                               x-model.number="modal.editGrades[idx].grade"
                                               @input="modal.editGrades[idx].letter_grade = letterGrade(modal.editGrades[idx].grade)"
                                               class="w-20 text-center text-sm font-bold"
                                               :class="gradeColor(modal.editGrades[idx].grade)">
                                    </td>
                                    <td class="grade-cell">
                                        <span :class="gradeColor(modal.editGrades[idx].grade)"
                                              class="font-black text-sm"
                                              x-text="modal.editGrades[idx].letter_grade"></span>
                                    </td>
                                    <td>
                                        <input type="text" placeholder="Comentario del docente…"
                                               x-model="modal.editGrades[idx].teacher_observations"
                                               class="text-xs">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Observations --}}
                <div class="mb-5">
                    <label class="block text-xs font-bold text-violet-300 mb-2 uppercase tracking-wider">
                        Informe general del director
                    </label>
                    <textarea rows="4" placeholder="Escribe las observaciones generales del director para la boleta…"
                              x-model="modal.observations" class="resize-none"></textarea>
                </div>

                {{-- Audit trail --}}
                <div x-show="modal.card.audit_logs?.length > 0" class="mb-5 rounded-xl border border-white/10 p-3">
                    <p class="text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">Historial de cambios</p>
                    <div class="space-y-1">
                        <template x-for="log in (modal.card.audit_logs ?? [])" :key="log.id">
                            <div class="text-xs text-slate-500 flex gap-2">
                                <span class="text-violet-400" x-text="log.action"></span>
                                <span x-text="'por ' + (log.user?.name ?? '—')"></span>
                                <span x-text="log.created_at"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 justify-end">
                    <button @click="modal.open = false" class="btn-secondary">Cancelar</button>
                    <a :href="'/director/api/report-cards/' + modal.card.id + '/pdf'"
                       class="btn-secondary flex items-center gap-1.5 no-underline text-sm">
                        <i class="fa-solid fa-file-pdf text-red-400"></i> PDF
                    </a>
                    <button @click="saveCard()" :disabled="modal.saving" class="btn-primary flex items-center gap-2">
                        <div x-show="modal.saving" class="spinner"></div>
                        <i x-show="!modal.saving" class="fa-solid fa-floppy-disk text-xs"></i>
                        <span x-text="modal.saving ? 'Guardando…' : 'Guardar cambios'"></span>
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const BASE = '/director';

function post(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(data),
    }).then(r => r.json());
}
function put(url, data) {
    return fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(data),
    }).then(r => r.json());
}
function get(url) {
    return fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF } }).then(r => r.json());
}

document.addEventListener('alpine:init', () => {
    Alpine.store('panel', {
        open: false,
        title: '',
        content: '',
        onSave: null,
        close() { this.open = false; this.content = ''; },
    });
});

function boletasApp() {
    return {
        tab: 'periods',
        periods: [],
        selectedPeriod: null,
        summary: null,
        cards: [],
        loading: { periods: false, summary: false, cards: false },
        generating: false,
        publishing: false,
        toast: { show: false, msg: '', icon: 'fa-solid fa-circle-check text-green-400' },
        modal: {
            open: false, loading: false, saving: false,
            card: null, editGrades: [], observations: '',
        },

        async init() {
            await this.loadPeriods();
        },

        // ── Periods ──────────────────────────────────────────────────────────
        async loadPeriods() {
            this.loading.periods = true;
            const data = await get(`${BASE}/api/periods`).catch(() => []);
            this.periods = Array.isArray(data) ? data : [];
            this.loading.periods = false;
        },

        selectPeriod(period) {
            this.selectedPeriod = period;
        },

        openNewPeriodPanel() {
            this.$store.panel.title = 'Nuevo período académico';
            this.$store.panel.content = this.periodFormHtml({});
            this.$store.panel.open = true;
            this.$store.panel.onSave = () => this.savePeriodFromPanel();
        },

        openEditPeriodPanel(period) {
            this.$store.panel.title = 'Editar período';
            this.$store.panel.content = this.periodFormHtml(period);
            this.$store.panel.open = true;
            this.$store.panel.editId = period.id;
            this.$store.panel.onSave = () => this.savePeriodFromPanel(period.id);
        },

        periodFormHtml(p) {
            return `
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-violet-300 mb-1.5 uppercase tracking-wider">Nombre del período</label>
                    <input id="pf_name" type="text" value="${p.name ?? ''}" placeholder="Ej: 1er Lapso 2025-2026">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-violet-300 mb-1.5 uppercase tracking-wider">Fecha inicio</label>
                        <input id="pf_start" type="date" value="${p.start_date ?? ''}">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-violet-300 mb-1.5 uppercase tracking-wider">Fecha fin</label>
                        <input id="pf_end" type="date" value="${p.end_date ?? ''}">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-violet-300 mb-1.5 uppercase tracking-wider">Fecha entrega boletas</label>
                    <input id="pf_due" type="date" value="${p.report_card_due_date ?? ''}">
                </div>
                <div>
                    <label class="block text-xs font-bold text-violet-300 mb-1.5 uppercase tracking-wider">Estado</label>
                    <select id="pf_status">
                        <option value="active" ${(p.status ?? 'active') === 'active' ? 'selected' : ''}>Activo</option>
                        <option value="closed" ${p.status === 'closed' ? 'selected' : ''}>Cerrado</option>
                    </select>
                </div>
                <button onclick="window.boletasRef.savePeriodFromPanel(${p.id ?? ''})"
                        class="btn-primary w-full flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                    <span>${p.id ? 'Guardar cambios' : 'Crear período'}</span>
                </button>
            </div>`;
        },

        async savePeriodFromPanel(id) {
            const payload = {
                name:                  document.getElementById('pf_name')?.value,
                start_date:            document.getElementById('pf_start')?.value,
                end_date:              document.getElementById('pf_end')?.value,
                report_card_due_date:  document.getElementById('pf_due')?.value || null,
                status:                document.getElementById('pf_status')?.value,
            };
            const url = id ? `${BASE}/api/periods/${id}` : `${BASE}/api/periods`;
            const res = id ? await put(url, payload) : await post(url, payload);
            if (res.ok) {
                this.$store.panel.close();
                await this.loadPeriods();
                this.showToast(id ? 'Período actualizado.' : 'Período creado correctamente.', 'fa-solid fa-circle-check text-green-400');
            } else {
                this.showToast(res.message ?? 'Error al guardar.', 'fa-solid fa-circle-exclamation text-red-400');
            }
        },

        // ── Grades summary ────────────────────────────────────────────────────
        async loadSummary() {
            if (!this.selectedPeriod) return;
            this.loading.summary = true;
            this.summary = null;
            const data = await get(`${BASE}/api/periods/${this.selectedPeriod.id}/grades-summary`);
            this.summary = data;
            this.loading.summary = false;
        },

        findCourse(row, courseId) {
            return row.courses?.find(c => c.course_id === courseId) ?? null;
        },

        avgGlobal() {
            if (!this.summary?.rows?.length) return null;
            const avgs = this.summary.rows.map(r => r.global_average).filter(v => v !== null);
            if (!avgs.length) return null;
            return (avgs.reduce((a, b) => a + b, 0) / avgs.length).toFixed(1);
        },

        atRisk() {
            if (!this.summary?.rows) return 0;
            return this.summary.rows.filter(r => r.global_average !== null && r.global_average < 70).length;
        },

        // ── Generate ──────────────────────────────────────────────────────────
        async generateCards() {
            if (!this.selectedPeriod || this.generating) return;
            this.generating = true;
            const res = await post(`${BASE}/api/periods/${this.selectedPeriod.id}/generate`, {});
            this.generating = false;
            if (res.ok) {
                this.showToast(res.message ?? 'Boletas generadas.', 'fa-solid fa-wand-magic-sparkles text-violet-400');
                await this.loadPeriods();
                this.tab = 'boletas';
                await this.loadCards();
            } else {
                this.showToast(res.message ?? 'Error al generar.', 'fa-solid fa-circle-exclamation text-red-400');
            }
        },

        // ── Cards list ────────────────────────────────────────────────────────
        async loadCards() {
            if (!this.selectedPeriod) return;
            this.loading.cards = true;
            this.cards = [];
            const data = await get(`${BASE}/api/periods/${this.selectedPeriod.id}/cards`);
            this.cards = data.cards ?? [];
            this.loading.cards = false;
        },

        // ── Publish ───────────────────────────────────────────────────────────
        async publishAll() {
            if (!this.selectedPeriod || this.publishing) return;
            if (!confirm('¿Publicar todas las boletas en borrador? Los representantes recibirán una notificación.')) return;
            this.publishing = true;
            const res = await post(`${BASE}/api/periods/${this.selectedPeriod.id}/publish`, {});
            this.publishing = false;
            if (res.ok) {
                this.showToast(res.message ?? '¡Boletas publicadas!', 'fa-solid fa-bullhorn text-green-400');
                await this.loadCards();
                await this.loadPeriods();
            } else {
                this.showToast(res.message ?? 'Error al publicar.', 'fa-solid fa-circle-exclamation text-red-400');
            }
        },

        // ── Card edit modal ───────────────────────────────────────────────────
        async openCardEdit(cardId) {
            this.modal.open = true;
            this.modal.loading = true;
            this.modal.card = null;
            const data = await get(`${BASE}/api/report-cards/${cardId}`);
            this.modal.card = data;
            this.modal.editGrades = (data.grades ?? []).map(g => ({ ...g }));
            this.modal.observations = data.observations ?? '';
            this.modal.loading = false;
        },

        async saveCard() {
            if (!this.modal.card || this.modal.saving) return;
            this.modal.saving = true;
            const res = await put(`${BASE}/api/report-cards/${this.modal.card.id}`, {
                observations: this.modal.observations,
                grades: this.modal.editGrades.map(g => ({
                    course_id:             g.course_id,
                    grade:                 g.grade,
                    teacher_observations:  g.teacher_observations,
                })),
            });
            this.modal.saving = false;
            if (res.ok) {
                this.modal.open = false;
                this.showToast('Boleta guardada correctamente.', 'fa-solid fa-floppy-disk text-violet-400');
                await this.loadCards();
            } else {
                this.showToast(res.message ?? 'Error al guardar.', 'fa-solid fa-circle-exclamation text-red-400');
            }
        },

        // ── Helpers ───────────────────────────────────────────────────────────
        gradeColor(avg) {
            if (avg === null || avg === undefined) return 'text-slate-500';
            if (avg >= 90) return 'grade-a';
            if (avg >= 80) return 'grade-b';
            if (avg >= 70) return 'grade-c';
            if (avg >= 60) return 'grade-d';
            return 'grade-f';
        },

        letterGrade(score) {
            if (score >= 90) return 'A';
            if (score >= 80) return 'B+';
            if (score >= 70) return 'C+';
            if (score >= 60) return 'D';
            return 'F';
        },

        fmtDate(d) {
            if (!d) return '—';
            const parts = d.split('-');
            if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
            return d;
        },

        showToast(msg, icon) {
            this.toast = { show: true, msg, icon: icon ?? 'fa-solid fa-circle-check text-green-400' };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
    };
}

// Expose ref for panel buttons (injected HTML)
document.addEventListener('alpine:init', () => {
    setTimeout(() => {
        const el = document.querySelector('[x-data="boletasApp()"]');
        if (el) window.boletasRef = Alpine.$data(el);
    }, 500);
});
</script>
</body>
</html>
