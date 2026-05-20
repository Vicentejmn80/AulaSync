<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Director · {{ $institution['name'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .glass-card {
            background: linear-gradient(145deg, rgba(255,255,255,.105), rgba(255,255,255,.035));
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
            backdrop-filter: blur(22px);
        }
        .executive-grid {
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 48px 48px;
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden bg-[#070816] text-slate-100">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-fuchsia-600/20 blur-[110px]"></div>
        <div class="executive-grid absolute inset-0 opacity-40"></div>
    </div>

    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 shadow-2xl shadow-black/20 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg shadow-cyan-500/20">
                    <i class="fa-solid fa-building-columns text-xl text-white"></i>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Nova Executive Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white md:text-3xl">{{ $institution['name'] }}</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Período {{ $institution['period'] }} · {{ $institution['campuses'] }} sede{{ (int) $institution['campuses'] === 1 ? '' : 's' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('dashboard') }}" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                    <i class="fa-solid fa-layer-group mr-2 text-cyan-300"></i>App
                </a>
                @include('components.user-control-panel')
            </div>
        </header>

        <section class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($kpis as $kpi)
                <article class="glass-card group rounded-[1.75rem] p-5 transition duration-300 hover:-translate-y-1">
                    <div class="mb-5 flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br {{ $kpi['accent'] }} shadow-lg shadow-black/20">
                            <i class="fa-solid {{ $kpi['icon'] }} text-white"></i>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-300">Live</span>
                    </div>
                    <p class="text-sm font-semibold text-slate-400">{{ $kpi['label'] }}</p>
                    <p class="mt-2 text-4xl font-black tracking-tight text-white">{{ $kpi['value'] }}</p>
                    <p class="mt-3 text-xs leading-5 text-slate-400">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.45fr_.9fr]">
            <article class="glass-card rounded-[2rem] p-6">
                <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Rendimiento Académico</p>
                        <h2 class="mt-2 text-xl font-black text-white">Promedio por año escolar</h2>
                    </div>
                    <p class="text-sm text-slate-400">Escala institucional normalizada a 100%</p>
                </div>

                <div class="h-[340px]">
                    <canvas id="gradePerformanceChart"></canvas>
                </div>
            </article>

            <aside class="glass-card rounded-[2rem] p-6">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-fuchsia-500 to-violet-500">
                        <i class="fa-solid fa-wand-magic-sparkles text-white"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.22em] text-fuchsia-200">Nova AI Alerts</p>
                        <h2 class="text-lg font-black text-white">Salones con menor rendimiento</h2>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse($lowPerformingRooms as $room)
                        <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-white">{{ $room['name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $room['grades_count'] }} notas analizadas</p>
                                </div>
                                <span class="rounded-full border border-amber-300/30 bg-amber-400/10 px-3 py-1 text-sm font-black text-amber-200">{{ $room['average'] }}%</span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $room['recommendation'] }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-white/10 bg-white/[.045] p-5 text-sm text-slate-300">
                            Nova AI todavía no detecta alertas. Carga notas para activar el análisis predictivo.
                        </div>
                    @endforelse
                </div>
            </aside>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="glass-card rounded-[2rem] p-6 lg:col-span-2">
                <p class="text-xs font-bold uppercase tracking-[.25em] text-violet-200">Management Brief</p>
                <h2 class="mt-2 text-xl font-black text-white">Lectura ejecutiva</h2>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                        <p class="text-3xl font-black text-cyan-200">{{ $teachersWithPendingGrades }}</p>
                        <p class="mt-1 text-sm text-slate-400">docentes con pendientes por calificar</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                        <p class="text-3xl font-black text-violet-200">{{ count($lowPerformingRooms) }}</p>
                        <p class="mt-1 text-sm text-slate-400">salones requieren seguimiento</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                        <p class="text-3xl font-black text-emerald-200">{{ now()->format('d/m') }}</p>
                        <p class="mt-1 text-sm text-slate-400">corte analítico actualizado</p>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-[2rem] p-6">
                <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Siguiente acción</p>
                <h2 class="mt-2 text-xl font-black text-white">Reunión de seguimiento</h2>
                <p class="mt-4 text-sm leading-6 text-slate-300">
                    Prioriza los salones alertados por Nova AI y solicita evidencias de recuperación a los docentes con notas pendientes.
                </p>
            </div>
        </section>
    </main>

    <script>
        const performanceData = @json($gradePerformance);
        const ctx = document.getElementById('gradePerformanceChart');

        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: performanceData.map(item => item.grade),
                    datasets: [{
                        label: 'Promedio (%)',
                        data: performanceData.map(item => item.average),
                        borderRadius: 18,
                        borderSkipped: false,
                        backgroundColor: performanceData.map(item => item.has_data
                            ? 'rgba(34, 211, 238, 0.72)'
                            : 'rgba(148, 163, 184, 0.22)'
                        ),
                        borderColor: 'rgba(255,255,255,.22)',
                        borderWidth: 1,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15,23,42,.95)',
                            borderColor: 'rgba(34,211,238,.35)',
                            borderWidth: 1,
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#cbd5e1', font: { weight: 700 } }
                        },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(255,255,255,.08)' },
                            ticks: { color: '#94a3b8', callback: value => value + '%' }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
