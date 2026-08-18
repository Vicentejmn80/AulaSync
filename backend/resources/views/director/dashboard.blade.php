<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Director · {{ $institution['name'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:var(--bg-primary); color:var(--text-primary); }
        .glass-card {
            background: linear-gradient(145deg, rgba(255,255,255,.105), rgba(255,255,255,.035));
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
            backdrop-filter: blur(22px);
        }
        :root:not(.dark) .glass-card {
            background: var(--bg-card);
            border-color: var(--nova-glass-border);
            box-shadow: var(--nova-shadow);
        }
        :root:not(.dark) .text-white,
        :root:not(.dark) .text-white\/80,
        :root:not(.dark) .text-slate-100,
        :root:not(.dark) .text-slate-200,
        :root:not(.dark) .text-slate-300,
        :root:not(.dark) .text-slate-400 { color: var(--text-primary); }
        :root:not(.dark) .bg-white\/5,
        :root:not(.dark) .bg-white\/\[\.045\],
        :root:not(.dark) .bg-white\/10 { background: var(--bg-card); }
        :root:not(.dark) .border-white\/10 { border-color: var(--nova-glass-border); }
        :root:not(.dark) .text-cyan-200,
        :root:not(.dark) .text-violet-200,
        :root:not(.dark) .text-fuchsia-200,
        :root:not(.dark) .text-emerald-200,
        :root:not(.dark) .text-amber-200 { color: var(--nova-violet); }
        :root:not(.dark) .text-cyan-300 { color: var(--nova-cyan); }
        :root:not(.dark) .shadow-black\/20 { box-shadow: var(--nova-shadow); }
        .executive-grid {
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 48px 48px;
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-fuchsia-600/20 blur-[110px]"></div>
        <div class="executive-grid absolute inset-0 opacity-40"></div>
    </div>

    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 shadow-2xl shadow-black/20 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between" style="position:relative;z-index:100">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg shadow-cyan-500/20">
                    <i class="fa-solid fa-building-columns text-xl text-white"></i>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">AulaSync Intelligence</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white md:text-3xl">{{ $institution['name'] }}</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Período {{ $institution['period'] }} · {{ $institution['campuses'] }} sede{{ (int) $institution['campuses'] === 1 ? '' : 's' }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('director.periodos') }}" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                    <i class="fa-solid fa-file-invoice mr-2 text-cyan-300"></i>Boletas
                </a>
                <a href="{{ route('director.boletines') }}" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                    <i class="fa-solid fa-file-lines mr-2 text-violet-300"></i>Ver notas
                </a>
                <a href="{{ route('director.attendance') }}" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                    <i class="fa-solid fa-clipboard-user mr-2 text-cyan-300"></i>Asistencia
                </a>
                <a href="{{ route('director.evaluation_plans') }}" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                    <i class="fa-solid fa-scale-balanced mr-2 text-cyan-300"></i>Planes de Evaluación
                </a>
                <a href="{{ route('dashboard') }}" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                    <i class="fa-solid fa-layer-group mr-2 text-cyan-300"></i>App
                </a>
                @include('components.user-control-panel')
            </div>
        </header>

        <section class="mb-8 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 shadow-2xl shadow-black/20 backdrop-blur-2xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[.25em] text-cyan-300">Acceso institucional</p>
                    <h2 class="mt-1 text-xl font-black text-white">Código del colegio</h2>
                    <p class="mt-1 max-w-xl text-sm text-slate-400">
                        Compártelo con docentes y representantes. Está protegido con el PIN del colegio: se revela 20 segundos y vuelve a bloquearse.
                        PIN por defecto: los <strong class="text-slate-200">últimos 4 dígitos</strong> del código de invitación.
                    </p>
                </div>
                <div class="flex flex-col items-start gap-3 sm:items-end">
                    @if($colegio)
                        <x-code-reveal type="school" label="Código de colegio" />
                    @else
                        <p class="text-sm text-slate-500">Sin colegio vinculado.</p>
                    @endif
                </div>
            </div>
        </section>

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
            <article class="glass-card rounded-[2rem] p-6" x-data="gradeBars(@js($gradePerformance))" x-init="init()">
                <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Rendimiento Académico</p>
                        <h2 class="mt-2 text-xl font-black text-white">Promedio por año escolar</h2>
                    </div>
                    <p class="text-sm text-slate-400">Escala institucional normalizada a 100%</p>
                </div>

                <div class="relative h-[340px]">
                    <div class="absolute inset-0 flex items-end justify-between gap-3 rounded-2xl border border-white/10 bg-white/[.03] px-4 pb-4 pt-10">
                        <template x-for="(bar, idx) in bars" :key="`${bar.grade}-${idx}`">
                            <div class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                <div class="relative flex h-full w-full items-end justify-center">
                                    <div class="absolute -top-7 left-1/2 -translate-x-1/2 rounded-md border border-cyan-300/30 bg-slate-900 px-2 py-1 text-[11px] font-semibold text-cyan-200 opacity-0 transition group-hover:opacity-100">
                                        Promedio: <span x-text="bar.average.toFixed(1)"></span>%
                                    </div>
                                    <div class="w-full max-w-[54px] rounded-t-xl bg-gradient-to-t from-indigo-600 to-violet-500 shadow-lg shadow-indigo-900/30 transition-all duration-700 ease-out"
                                         :class="{ 'animate-pulse': loading }"
                                         :style="`height:${Math.max(8, bar.animatedValue)}%; opacity:${bar.has_data ? 1 : 0.4};`"></div>
                                </div>
                                <p class="mt-2 truncate text-center text-[11px] font-semibold text-slate-300" x-text="bar.grade"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </article>

            <aside class="glass-card rounded-[2rem] p-6">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-fuchsia-500 to-violet-500">
                        <i class="fa-solid fa-wand-magic-sparkles text-white"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.22em] text-fuchsia-200">AulaSync AI Alerts</p>
                        <h2 class="text-lg font-black text-white">Alertas inteligentes</h2>
                    </div>
                </div>

                <div class="space-y-3">
                    @if($alertsWithContent->isNotEmpty())
                        @foreach($alertsWithContent as $alert)
                            <div class="rounded-2xl border border-white/10 bg-white/[.045] p-4
                                {{ $alert['type'] === 'stuck' ? 'border-l-4 border-l-amber-400' : '' }}
                                {{ $alert['type'] === 'revision' ? 'border-l-4 border-l-cyan-400' : '' }}
                                {{ $alert['type'] === 'inactive' ? 'border-l-4 border-l-rose-400' : '' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-white text-sm flex items-center gap-2">
                                            <i class="fa-solid {{ $alert['icon'] }}
                                                {{ $alert['type'] === 'stuck' ? 'text-amber-200' : '' }}
                                                {{ $alert['type'] === 'revision' ? 'text-cyan-200' : '' }}
                                                {{ $alert['type'] === 'inactive' ? 'text-rose-200' : '' }}"></i>
                                            {{ $alert['title'] }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $alert['body'] }}</p>
                                    </div>
                                </div>
                                @if($alert['action_url'])
                                    <a href="{{ $alert['action_url'] }}"
                                       class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-white/10 px-3 py-1.5 text-xs font-semibold text-cyan-200 transition hover:bg-white/10">
                                        <i class="fa-solid fa-arrow-right"></i>{{ $alert['action_text'] }}
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @else
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
                                AulaSync AI no detecta alertas. Todos los indicadores están estables.
                            </div>
                        @endforelse
                    @endif
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
                <h2 class="mt-2 text-xl font-black text-white">Prioridades AulaSync</h2>

                @if($planificacionesPendientesRevision > 0)
                    <p class="mt-4 text-sm leading-6 text-cyan-200">
                        <i class="fa-solid fa-rotate-right mr-1.5"></i>
                        <strong>{{ $planificacionesPendientesRevision }} planificación(es)</strong> fueron corregidas por docentes y esperan nueva revisión.
                    </p>
                    <a href="{{ route('director.planificaciones', ['status' => 'pendiente_revision']) }}"
                       class="mt-3 inline-flex items-center gap-2 rounded-xl border border-cyan-400/30 bg-cyan-400/10 px-4 py-2 text-sm font-bold text-cyan-200 transition hover:bg-cyan-400/20">
                        <i class="fa-solid fa-rotate-right"></i>
                        Revisar correcciones
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-cyan-300 text-[10px] font-black text-cyan-950">{{ $planificacionesPendientesRevision }}</span>
                    </a>
                @elseif($stuckCount > 0)
                    <p class="mt-4 text-sm leading-6 text-amber-200">
                        <i class="fa-solid fa-clock mr-1.5"></i>
                        <strong>{{ $stuckCount }} planificación(es)</strong> estancada(s) hace más de 48 horas esperando revisión.
                    </p>
                    <a href="{{ route('director.planificaciones', ['status' => 'pendiente']) }}"
                       class="mt-3 inline-flex items-center gap-2 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-bold text-amber-200 transition hover:bg-amber-400/20">
                        <i class="fa-solid fa-clock"></i>
                        Revisar {{ $stuckCount }} planificación(es) estancada(s)
                    </a>
                @elseif($inactiveTeachersCount > 0)
                    <p class="mt-4 text-sm leading-6 text-rose-200">
                        <i class="fa-solid fa-user-slash mr-1.5"></i>
                        <strong>{{ $inactiveTeachersCount }} docente(s)</strong> sin registrar actividades esta semana.
                    </p>
                    <a href="{{ route('director.planificaciones') }}"
                       class="mt-3 inline-flex items-center gap-2 rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-2 text-sm font-bold text-rose-200 transition hover:bg-rose-400/20">
                        <i class="fa-solid fa-user-slash"></i>
                        Ver docentes sin actividad
                    </a>
                @elseif($planificacionesPendientes > 0)
                    <p class="mt-4 text-sm leading-6 text-slate-300">
                        <i class="fa-regular fa-clock mr-1.5"></i>
                        Hay <strong>{{ $planificacionesPendientes }} planificación(es)</strong> pendientes de revisión.
                    </p>
                    <a href="{{ route('director.planificaciones', ['status' => 'pendiente']) }}"
                       class="mt-3 inline-flex items-center gap-2 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-bold text-amber-200 transition hover:bg-amber-400/20">
                        <i class="fa-solid fa-list-check"></i>
                        Revisar planificaciones pendientes
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-400 text-[10px] font-black text-amber-900">{{ $planificacionesPendientes }}</span>
                    </a>
                @else
                    <p class="mt-4 text-sm leading-6 text-emerald-200">
                        <i class="fa-solid fa-circle-check mr-1.5"></i>
                        Todo está al día. No hay alertas pendientes.
                    </p>
                @endif

                <a href="{{ route('director.planificaciones') }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-regular fa-eye"></i>
                    Panel de auditoría completo
                </a>
            </div>
        </section>

        {{-- Planificaciones Recientes Feed --}}
        <section class="mt-6 glass-card rounded-[2rem] p-6">
            <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Feed en Vivo</p>
                    <h2 class="mt-2 text-xl font-black text-white">Planificaciones Recientes</h2>
                </div>
                <div class="flex gap-3 text-sm text-slate-400">
                    <span><i class="fa-regular fa-calendar mr-1"></i>{{ $planificacionesCountEsteMes }} este mes</span>
                    <span><i class="fa-regular fa-clock mr-1"></i>{{ $planificacionesPendientes }} pendientes</span>
                    <span><i class="fa-solid fa-rotate-right mr-1"></i>{{ $planificacionesPendientesRevision }} correcciones</span>
                    <span><i class="fa-solid fa-chalkboard-user mr-1"></i>{{ $totalTeachers }} docentes</span>
                </div>
            </div>

            <div class="space-y-2">
                @forelse($planificacionesRecientes as $plan)
                    <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[.035] p-4 transition hover:bg-white/[.06]">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                            @if($plan->status === 'aprobado') bg-emerald-400/20 text-emerald-200
                            @elseif($plan->status === 'rechazado') bg-rose-400/20 text-rose-200
                            @elseif($plan->status === 'pendiente_revision') bg-cyan-400/20 text-cyan-200
                            @else bg-amber-400/20 text-amber-200 @endif">
                            @if($plan->status === 'aprobado') <i class="fa-solid fa-check"></i>
                            @elseif($plan->status === 'rechazado') <i class="fa-solid fa-xmark"></i>
                            @elseif($plan->status === 'pendiente_revision') <i class="fa-solid fa-rotate-right"></i>
                            @else <i class="fa-regular fa-clock"></i> @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-white truncate">{{ $plan->tema }}</p>
                            <p class="text-xs text-slate-500">
                                <i class="fa-solid fa-user mr-1"></i>{{ $plan->user?->name ?? '—' }}
                                <span class="mx-1.5">&middot;</span>
                                {{ $plan->created_at->diffForHumans() }}
                                <span class="mx-1.5">&middot;</span>
                                <span class="uppercase text-[10px] tracking-wider
                                    @if($plan->status === 'aprobado') text-emerald-200
                                    @elseif($plan->status === 'rechazado') text-rose-200
                                    @elseif($plan->status === 'pendiente_revision') text-cyan-200
                                    @else text-amber-200 @endif">
                                    {{ $plan->status === 'pendiente_revision' ? 'pendiente de revisión' : ($plan->status ?? 'pendiente') }}
                                </span>
                            </p>
                        </div>
                        <a href="{{ route('director.planificaciones', $plan->status ? ['status' => $plan->status] : []) }}"
                           class="shrink-0 rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/10">
                            <i class="fa-solid fa-arrow-right mr-1"></i>Auditar
                        </a>
                    </div>
                @empty
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-6 text-center text-sm text-slate-400">
                        <i class="fa-regular fa-calendar mr-2"></i>Aún no hay planificaciones registradas.
                    </div>
                @endforelse
            </div>

            <div class="mt-4 flex gap-3">
                <a href="{{ route('director.planificaciones') }}"
                   class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-regular fa-eye mr-2"></i>Ver todas las planificaciones
                </a>
                <a href="{{ route('director.periodos') }}"
                   class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-solid fa-file-invoice mr-2"></i>Boletas inteligentes
                </a>
            </div>
        </section>

        {{-- Actividades Recientes Feed --}}
        <section class="mt-6 glass-card rounded-[2rem] p-6">
            <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Feed de Actividades</p>
                    <h2 class="mt-2 text-xl font-black text-white">Actividades Recientes</h2>
                </div>
                <div class="flex gap-3 text-sm text-slate-400">
                    <span><i class="fa-regular fa-file-lines mr-1"></i>{{ $actividadesRecientes->count() }} recientes</span>
                </div>
            </div>

            <div class="space-y-2">
                @forelse($actividadesRecientes as $act)
                    <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[.035] p-4 transition hover:bg-white/[.06]">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                            @if($act->type === 'tarea') bg-rose-400/20 text-rose-200
                            @elseif($act->type === 'clase') bg-violet-400/20 text-violet-200
                            @else bg-emerald-400/20 text-emerald-200 @endif">
                            @if($act->type === 'tarea') <i class="fa-solid fa-book"></i>
                            @elseif($act->type === 'clase') <i class="fa-solid fa-chalkboard-user"></i>
                            @else <i class="fa-solid fa-pen-to-square"></i> @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-white truncate">{{ $act->title }}</p>
                            <p class="text-xs text-slate-500">
                                <i class="fa-solid fa-user mr-1"></i>{{ $act->teacher?->name ?? '—' }}
                                <span class="mx-1.5">&middot;</span>
                                @if($act->course)
                                    {{ $act->course->subject_name }} · {{ $act->course->grade }}
                                @else
                                    <span class="text-slate-600">Sin curso</span>
                                @endif
                                <span class="mx-1.5">&middot;</span>
                                <span class="capitalize">{{ $act->type }}</span>
                                @if($act->due_date)
                                    <span class="mx-1.5">&middot;</span>
                                    <span>{{ $act->due_date->format('d/m/Y') }}</span>
                                @endif
                            </p>
                        </div>
                        @if($act->director_notes)
                            <span class="shrink-0 rounded-full border border-amber-300/30 bg-amber-400/10 px-2.5 py-1 text-[10px] font-semibold text-amber-200">
                                <i class="fa-solid fa-message mr-1"></i>Nota
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-6 text-center text-sm text-slate-400">
                        <i class="fa-regular fa-calendar mr-2"></i>Aún no hay actividades registradas.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Plantel Docente --}}
        <section class="mt-6 glass-card rounded-[2rem] p-6">
            <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Gestión Institucional</p>
                    <h2 class="mt-2 text-xl font-black text-white">Plantel Docente y Materias</h2>
                </div>
                <div class="flex gap-3 text-sm text-slate-400">
                    <span><i class="fa-solid fa-chalkboard-user mr-1"></i>{{ $profesores->count() }} docente(s)</span>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($profesores as $docente)
                    <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4 transition hover:bg-white/[.06]">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-white">{{ $docente->name }}</p>
                                <p class="text-xs text-slate-500">{{ $docente->email }}</p>
                            </div>
                            <span class="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">
                                {{ $docente->courses->count() }} curso(s)
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse($docente->courses as $course)
                                <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                                    {{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / ' . $course->section : '' }}
                                </span>
                            @empty
                                <span class="text-xs italic text-slate-600">Sin cursos asignados</span>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-6 text-center text-sm text-slate-400">
                        <i class="fa-solid fa-chalkboard-user mr-2"></i>No hay docentes registrados para este colegio.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                <a href="{{ route('director.profesores') }}"
                   class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-regular fa-eye mr-2"></i>Ver plantel completo
                </a>
                <a href="{{ route('director.courses') }}"
                   class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-solid fa-chalkboard mr-2"></i>Cursos y secciones
                </a>
                <a href="{{ route('director.periodos') }}"
                   class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-solid fa-file-invoice mr-2"></i>Boletas
                </a>
                <a href="{{ route('director.attendance') }}"
                   class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-solid fa-clipboard-user mr-2"></i>Asistencia
                </a>
            </div>
        </section>
    </main>

    <script>
        function gradeBars(performanceData) {
            return {
                loading: true,
                bars: (performanceData || []).map((item) => ({
                    grade: item.grade || 'N/A',
                    average: Number(item.average || 0),
                    has_data: !!item.has_data,
                    animatedValue: 0,
                })),
                init() {
                    setTimeout(() => {
                        this.bars = this.bars.map((bar) => ({
                            ...bar,
                            animatedValue: Math.min(100, Math.max(0, Number(bar.average))),
                        }));
                        this.loading = false;
                    }, 180);
                },
            };
        }
    </script>

    @include('components.ai-assistant-bubble')
</body>
</html>
