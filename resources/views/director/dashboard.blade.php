<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Centro de Mando · {{ $settings?->nombre_institucion ?? 'Nova Academy' }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        [x-cloak]  { display: none !important; }
        body       { font-family: 'Inter', system-ui, sans-serif; background: #f8fafc; }
        html.dark body { background: #0c0118; }

        .stat-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #f1f5f9;
            transition: transform .18s, box-shadow .18s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(139,92,246,.10);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: .5rem .875rem;
            border-radius: .875rem;
            font-size: .8125rem;
            font-weight: 500;
            color: #64748b;
            transition: background .15s, color .15s;
            text-decoration: none;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: #ede9fe;
            color: #7c3aed;
        }

        .risk-row { transition: background .15s; }
        .risk-row:hover { background: #fff7ed; }

        .bar-fill { transition: width .6s cubic-bezier(.4,0,.2,1); }

        @keyframes fadeSlideUp {
            from { opacity:0; transform:translateY(12px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .animate-in { animation: fadeSlideUp .4s ease both; }
        .delay-1 { animation-delay: .06s; }
        .delay-2 { animation-delay: .12s; }
        .delay-3 { animation-delay: .18s; }
        .delay-4 { animation-delay: .24s; }

        [x-cloak] { display: none !important; }

        @media (max-width: 767px) {
            .director-sidebar {
                transform: translateX(-100%);
                transition: transform 0.25s ease;
            }
            .director-sidebar.director-sidebar-open {
                transform: translateX(0);
            }
        }

        html.dark aside { background:#120424 !important; border-color:rgba(124,58,237,.2) !important; }
        html.dark .sidebar-link { color:#d8b4fe; }
        html.dark .sidebar-link:hover, html.dark .sidebar-link.active { background:rgba(124,58,237,.2); color:#fff; }
        html.dark .stat-card,
        html.dark .bg-white { background:rgba(255,255,255,.04) !important; border-color:rgba(124,58,237,.18) !important; }
        html.dark .text-slate-800, html.dark .text-slate-700, html.dark .text-slate-600, html.dark .text-slate-500 { color:#fff !important; }
        html.dark .text-slate-400, html.dark .text-slate-300 { color:#d8b4fe !important; }
        html.dark .bg-slate-50, html.dark .bg-slate-100 { background:rgba(255,255,255,.06) !important; }
    </style>
    <script>
        if (localStorage.getItem('sp-dark-mode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="min-h-screen">

<div class="flex min-h-screen overflow-y-auto overflow-x-hidden relative" x-data="{ sidebarOpen: false }">

    <div
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-black/50 md:hidden"
        x-cloak
    ></div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- SIDEBAR                                                    --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <aside
        class="director-sidebar fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-slate-100 bg-white px-4 py-6 md:relative md:z-auto md:translate-x-0 md:shrink-0"
        :class="{ 'director-sidebar-open': sidebarOpen }"
    >
        {{-- Brand --}}
        <div class="mb-8 px-2">
            <span class="text-lg font-extrabold text-violet-700 tracking-tight">
                <i class="fa-solid fa-school mr-1.5"></i>Nova Academy
            </span>
            <p class="text-[10px] text-slate-400 font-medium uppercase tracking-widest mt-0.5">
                Centro de Mando
            </p>
        </div>

        {{-- Institution badge --}}
        @if($settings?->nombre_institucion)
        <div class="bg-violet-50 rounded-2xl px-3 py-2.5 mb-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-violet-400 mb-0.5">Institución</p>
            <p class="text-sm font-semibold text-violet-800">{{ $settings->nombre_institucion }}</p>
            @if($settings->modelo_pedagogico)
            <p class="text-xs text-violet-500 mt-0.5">{{ ucfirst($settings->modelo_pedagogico) }}</p>
            @endif
        </div>
        @endif

        {{-- Nav —— Director: solo lectura analítica, sin acciones operacionales --}}
        <nav class="flex flex-col gap-1 flex-1" @click="if ($event.target.closest('a')) sidebarOpen = false">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 px-2 mb-1">Reportes</p>
            <a href="{{ route('director.dashboard') }}" class="sidebar-link active">
                <i class="fa-solid fa-chart-pie w-4 text-center"></i> Visión General
            </a>
            <a href="{{ route('director.dashboard') }}?tab=rendimiento" class="sidebar-link">
                <i class="fa-solid fa-chart-bar w-4 text-center"></i> Rendimiento por Curso
            </a>
            <a href="{{ route('director.dashboard') }}?tab=riesgo" class="sidebar-link">
                <i class="fa-solid fa-triangle-exclamation w-4 text-center"></i> Alumnos en Riesgo
            </a>

            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 px-2 mb-1 mt-5">Gestión</p>
            {{-- Links de gestión sin acceso a operaciones de docente --}}
            <a href="#" class="sidebar-link opacity-40 cursor-not-allowed" title="Próximamente">
                <i class="fa-solid fa-users w-4 text-center"></i> Docentes
                <span class="ml-auto text-[9px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded-full">Soon</span>
            </a>
            <a href="#" class="sidebar-link opacity-40 cursor-not-allowed" title="Próximamente">
                <i class="fa-solid fa-project-diagram w-4 text-center"></i> Malla Curricular
                <span class="ml-auto text-[9px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded-full">Soon</span>
            </a>
            <a href="#" class="sidebar-link opacity-40 cursor-not-allowed" title="Próximamente">
                <i class="fa-solid fa-file-export w-4 text-center"></i> Exportar Reportes
                <span class="ml-auto text-[9px] bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded-full">Soon</span>
            </a>

            <div class="mt-4 pt-3 border-t border-slate-100">
                <p class="text-[10px] font-bold uppercase tracking-widest text-violet-400 px-2 mb-2">Asistente IA</p>
                <div class="space-y-1.5 px-1">
                    <button onclick="document.querySelector('#ai-bubble-btn')?.click()"
                            class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold
                                   text-white transition hover:opacity-90"
                            style="background:linear-gradient(135deg,#7c3aed,#c026d3);">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Asistente Inteligente
                    </button>
                </div>
            </div>
        </nav>

        {{-- User controls --}}
        <div class="border-t border-slate-100 pt-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                     style="background:linear-gradient(135deg,#7c3aed,#c026d3);">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-700 break-words">{{ $user->name }}</p>
                    <p class="text-[10px] text-slate-400">Director</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- MAIN CONTENT                                               --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <main class="min-w-0 flex-1 overflow-y-auto overflow-x-hidden">

        {{-- ── Top bar ─────────────────────────────────────────── --}}
        <div class="sticky top-0 z-20 flex flex-col gap-3 border-b border-slate-100 bg-white/80 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-4"
             style="padding-top: max(0.75rem, env(safe-area-inset-top));">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 md:hidden"
                    @click="sidebarOpen = !sidebarOpen"
                    :aria-expanded="sidebarOpen"
                    aria-label="Menú"
                >
                    <i class="fa-solid text-lg" :class="sidebarOpen ? 'fa-xmark' : 'fa-bars'"></i>
                </button>
                <div class="min-w-0">
                <h1 class="truncate text-base font-bold text-slate-800 sm:text-lg">Panel de Gestión Institucional</h1>
                <p class="mt-0.5 truncate text-xs text-slate-400">
                    Última actualización: {{ now()->format('d/m/Y H:i') }}
                </p>
                </div>
            </div>

            {{-- ── Filters ──────────────────────────────────────── --}}
            <form method="GET" action="{{ route('director.dashboard') }}"
                  class="flex flex-wrap items-center gap-2 sm:gap-3">
                @if($filters['grades']->isNotEmpty())
                <select name="grade"
                        onchange="this.form.submit()"
                        class="text-xs border border-slate-200 rounded-xl py-2 px-3 bg-white
                               focus:outline-none focus:border-violet-400 transition">
                    <option value="">Todos los grados</option>
                    @foreach($filters['grades'] as $g)
                        <option value="{{ $g }}" {{ $filterGrade === $g ? 'selected' : '' }}>
                            {{ $g }}
                        </option>
                    @endforeach
                </select>
                @endif

                @if($filters['sections']->isNotEmpty())
                <select name="section"
                        onchange="this.form.submit()"
                        class="text-xs border border-slate-200 rounded-xl py-2 px-3 bg-white
                               focus:outline-none focus:border-violet-400 transition">
                    <option value="">Todas las secciones</option>
                    @foreach($filters['sections'] as $s)
                        <option value="{{ $s }}" {{ $filterSection === $s ? 'selected' : '' }}>
                            Sección {{ $s }}
                        </option>
                    @endforeach
                </select>
                @endif

                @if($filterGrade || $filterSection)
                    <a href="{{ route('director.dashboard') }}"
                       class="text-xs text-violet-600 hover:underline flex items-center gap-1">
                        <i class="fa-solid fa-xmark"></i> Limpiar
                    </a>
                @endif
            </form>
        </div>

        <div class="px-4 py-5 sm:px-8 sm:py-7">

            {{-- ══════════════════════════════════════════════════ --}}
            {{-- EMPTY STATE                                         --}}
            {{-- ══════════════════════════════════════════════════ --}}
            @if($stats['totalStudents'] === 0 && $stats['totalCourses'] === 0)
            <div class="flex flex-col items-center justify-center py-28 text-center">
                <div class="w-24 h-24 bg-violet-50 rounded-3xl flex items-center justify-center mb-6">
                    <i class="fa-solid fa-school text-4xl text-violet-300"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-700 mb-2">Esperando actividad docente</h2>
                <p class="text-slate-400 text-sm max-w-sm mb-6 leading-relaxed">
                    Aún no hay cursos ni alumnos registrados por los docentes.
                    Una vez que los profesores carguen sus cursos y alumnos, verás aquí
                    los reportes y estadísticas institucionales.
                </p>
                <div class="inline-flex items-center gap-2 bg-slate-100 text-slate-500
                            text-sm font-medium px-5 py-3 rounded-2xl">
                    <i class="fa-solid fa-hourglass-half text-slate-400"></i>
                    Los docentes cargan los datos desde su panel
                </div>
            </div>

            @else

            {{-- ══════════════════════════════════════════════════ --}}
            {{-- STATS CARDS                                         --}}
            {{-- ══════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

                {{-- Total alumnos --}}
                <div class="stat-card p-5 animate-in">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">
                            Total Alumnos
                        </span>
                        <div class="w-9 h-9 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-users text-indigo-500 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-extrabold text-slate-800">{{ $stats['totalStudents'] }}</p>
                    <p class="text-xs text-slate-400 mt-1">estudiantes registrados</p>
                </div>

                {{-- Total cursos --}}
                <div class="stat-card p-5 animate-in delay-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">
                            Cursos Activos
                        </span>
                        <div class="w-9 h-9 bg-violet-50 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-chalkboard text-violet-500 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-extrabold text-slate-800">{{ $stats['totalCourses'] }}</p>
                    <p class="text-xs text-slate-400 mt-1">secciones configuradas</p>
                </div>

                {{-- Promedio institucional --}}
                <div class="stat-card p-5 animate-in delay-2">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">
                            Promedio Inst.
                        </span>
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center
                            {{ $stats['institutionalAvg'] >= 70 ? 'bg-emerald-50' : ($stats['institutionalAvg'] >= 50 ? 'bg-amber-50' : 'bg-red-50') }}">
                            <i class="fa-solid fa-chart-line text-sm
                               {{ $stats['institutionalAvg'] >= 70 ? 'text-emerald-500' : ($stats['institutionalAvg'] >= 50 ? 'text-amber-500' : 'text-red-500') }}">
                            </i>
                        </div>
                    </div>
                    <p class="text-3xl font-extrabold
                       {{ $stats['institutionalAvg'] >= 70 ? 'text-emerald-600' : ($stats['institutionalAvg'] >= 50 ? 'text-amber-600' : 'text-red-600') }}">
                        {{ $stats['institutionalAvg'] > 0 ? $stats['institutionalAvg'] . '%' : '—' }}
                    </p>
                    <p class="text-xs text-slate-400 mt-1">rendimiento general</p>
                </div>

                {{-- Actividades esta semana --}}
                <div class="stat-card p-5 animate-in delay-3">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-400">
                            Act. Esta Semana
                        </span>
                        <div class="w-9 h-9 bg-pink-50 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-clipboard-check text-pink-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-3xl font-extrabold text-slate-800">{{ $stats['activitiesWeek'] }}</p>
                    <p class="text-xs text-slate-400 mt-1">creadas desde el lunes</p>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════ --}}
            {{-- ROW 2: CHART + DISTRIBUTION                        --}}
            {{-- ══════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                {{-- Course performance chart --}}
                <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-100 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Rendimiento por Curso</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Promedio (% del puntaje máximo)</p>
                        </div>
                        <div class="w-8 h-8 bg-violet-50 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-bar-chart text-violet-400 text-xs"></i>
                        </div>
                    </div>

                    @php $hasChartData = collect($courseChart)->where('has_data', true)->isNotEmpty(); @endphp

                    @if($hasChartData)
                        <div class="relative" style="height:220px">
                            <canvas id="courseChart"></canvas>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-40 text-center">
                            <i class="fa-solid fa-chart-bar text-3xl text-slate-200 mb-3"></i>
                            <p class="text-sm text-slate-400">Sin notas registradas aún.</p>
                            <a href="{{ route('teacher.grades.index') }}"
                               class="text-xs text-violet-600 hover:underline mt-2">
                                Ir a cargar notas →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Grade distribution --}}
                <div class="bg-white rounded-3xl border border-slate-100 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="font-bold text-slate-800 text-sm">Distribución de Notas</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Porcentaje por rango</p>
                        </div>
                        <div class="w-8 h-8 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-chart-pie text-indigo-400 text-xs"></i>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @foreach($distribution as $bucket)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-medium text-slate-600">{{ $bucket['label'] }}</span>
                                <span class="text-xs font-bold
                                    {{ $bucket['color'] === 'emerald' ? 'text-emerald-600' :
                                       ($bucket['color'] === 'violet' ? 'text-violet-600' :
                                       ($bucket['color'] === 'amber'  ? 'text-amber-600'  : 'text-red-600')) }}">
                                    {{ $bucket['pct'] }}%
                                    <span class="font-normal text-slate-400">({{ $bucket['count'] }})</span>
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="bar-fill h-2 rounded-full
                                    {{ $bucket['color'] === 'emerald' ? 'bg-emerald-400' :
                                       ($bucket['color'] === 'violet' ? 'bg-violet-400' :
                                       ($bucket['color'] === 'amber'  ? 'bg-amber-400'  : 'bg-red-400')) }}"
                                     style="width: {{ $bucket['pct'] }}%">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════ --}}
            {{-- ROW 3: AT-RISK ALERTS + TOP/BOTTOM RANKING        --}}
            {{-- ══════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- At-risk students --}}
                <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fa-solid fa-triangle-exclamation text-red-400 text-sm"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-sm">Alumnos en Riesgo</h3>
                                <p class="text-[11px] text-slate-400">Promedio < 50% en actividades recientes</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-red-500 bg-red-50 px-2.5 py-1 rounded-full">
                            {{ $atRisk->count() }}
                        </span>
                    </div>

                    @if($atRisk->isEmpty())
                        <div class="flex flex-col items-center justify-center py-10 text-center px-6">
                            <i class="fa-solid fa-circle-check text-4xl text-emerald-300 mb-3"></i>
                            <p class="text-sm font-semibold text-slate-600">¡Excelente! Sin alertas activas</p>
                            <p class="text-xs text-slate-400 mt-1">
                                Todos los alumnos superan el umbral mínimo.
                            </p>
                        </div>
                    @else
                        <div class="divide-y divide-slate-50">
                            @foreach($atRisk as $student)
                            <div class="risk-row flex items-center gap-4 px-6 py-3">
                                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center
                                            text-red-500 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($student->name, 0, 2)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-700 break-words leading-tight">{{ $student->name }}</p>
                                    <p class="text-[11px] text-slate-400 break-words leading-tight">
                                        {{ $student->subject_name }} · {{ $student->grade }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-extrabold text-red-500">
                                        {{ round($student->avg_pct, 1) }}%
                                    </p>
                                    <p class="text-[10px] text-slate-400">promedio</p>
                                </div>
                                <div class="w-20 shrink-0">
                                    <div class="w-full bg-red-100 rounded-full h-1.5">
                                        <div class="bg-red-400 h-1.5 rounded-full bar-fill"
                                             style="width: {{ min($student->avg_pct, 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Top 5 / Bottom 5 ranking --}}
                <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden"
                     x-data="{ tab: 'top' }">
                    <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 bg-violet-50 rounded-xl flex items-center justify-center">
                                <i class="fa-solid fa-ranking-star text-violet-400 text-sm"></i>
                            </div>
                            <h3 class="font-bold text-slate-800 text-sm">Ranking de Estudiantes</h3>
                        </div>
                        {{-- Tab toggle --}}
                        <div class="flex bg-slate-100 rounded-xl p-0.5">
                            <button @click="tab = 'top'"
                                    :class="tab === 'top' ? 'bg-white shadow-sm text-violet-700' : 'text-slate-400'"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl transition">
                                Top 5
                            </button>
                            <button @click="tab = 'bottom'"
                                    :class="tab === 'bottom' ? 'bg-white shadow-sm text-red-600' : 'text-slate-400'"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl transition">
                                Riesgo 5
                            </button>
                        </div>
                    </div>

                    {{-- Top 5 --}}
                    <div x-show="tab === 'top'" class="divide-y divide-slate-50">
                        @forelse($ranking['top'] as $i => $student)
                        <div class="flex items-center gap-4 px-6 py-3">
                            <span class="w-6 text-center text-xs font-extrabold
                                {{ $i === 0 ? 'text-amber-500' : ($i === 1 ? 'text-slate-400' : ($i === 2 ? 'text-orange-400' : 'text-slate-300')) }}">
                                #{{ $i + 1 }}
                            </span>
                            <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center
                                        text-violet-600 text-xs font-bold shrink-0">
                                {{ strtoupper(substr($student->student_name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-700 break-words leading-tight">{{ $student->student_name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $student->grade }}</p>
                            </div>
                            <span class="text-sm font-extrabold text-emerald-600">
                                {{ round($student->avg_pct, 1) }}%
                            </span>
                        </div>
                        @empty
                        <div class="flex flex-col items-center justify-center py-10 text-center px-6">
                            <i class="fa-solid fa-user-graduate text-3xl text-slate-200 mb-3"></i>
                            <p class="text-sm text-slate-400">Sin datos suficientes aún.</p>
                        </div>
                        @endforelse
                    </div>

                    {{-- Bottom 5 --}}
                    <div x-show="tab === 'bottom'" x-cloak class="divide-y divide-slate-50">
                        @forelse($ranking['bottom'] as $i => $student)
                        <div class="flex items-center gap-4 px-6 py-3">
                            <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center
                                        text-red-500 text-xs font-bold shrink-0">
                                {{ strtoupper(substr($student->student_name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-700 break-words leading-tight">{{ $student->student_name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $student->grade }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-extrabold text-red-500">
                                    {{ round($student->avg_pct, 1) }}%
                                </span>
                                <p class="text-[10px] text-slate-400">{{ $student->grade_count }} notas</p>
                            </div>
                        </div>
                        @empty
                        <div class="flex flex-col items-center justify-center py-10 text-center px-6">
                            <i class="fa-solid fa-face-smile text-3xl text-slate-200 mb-3"></i>
                            <p class="text-sm text-slate-400">Sin estudiantes en riesgo.</p>
                        </div>
                        @endforelse
                    </div>
                </div>

            </div>
            {{-- end if has data --}}
            @endif

        </div>{{-- end px-8 --}}
    </main>
</div>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- CHART.JS INITIALIZATION                                     --}}
{{-- ══════════════════════════════════════════════════════════ --}}
@php
    $chartLabels = collect($courseChart)->pluck('label');
    $chartData   = collect($courseChart)->map(fn($c) => $c['has_data'] ? $c['avg_pct'] : null);
    $chartColors = $chartData->map(fn($v) => match(true) {
        $v === null   => 'rgba(203,213,225,0.6)',
        $v >= 70      => 'rgba(139,92,246,0.75)',
        $v >= 50      => 'rgba(251,191,36,0.75)',
        default       => 'rgba(239,68,68,0.75)',
    });
@endphp

@if(collect($courseChart)->where('has_data', true)->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('courseChart')?.getContext('2d');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartLabels) !!},
            datasets: [{
                label: 'Promedio (%)',
                data: {!! json_encode($chartData) !!},
                backgroundColor: {!! json_encode($chartColors) !!},
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 48,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.y !== null
                            ? `Promedio: ${ctx.parsed.y}%`
                            : 'Sin notas',
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: v => v + '%',
                        font: { size: 11 },
                    },
                    grid: {
                        color: 'rgba(241,245,249,1)',
                    },
                },
                x: {
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 35,
                    },
                    grid: { display: false },
                },
            },
        },
    });
});
</script>
@endif

@include('components.ai-assistant-bubble')
</body>
</html>
