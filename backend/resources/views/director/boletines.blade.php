<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Boletines · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
    </style>
</head>
<body class="min-h-screen">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-emerald-600/15 blur-[110px]"></div>
    </div>

    @php
        $withGrades = collect($rows)->where('has_grades', true)->count();
        $withoutGrades = collect($rows)->where('has_grades', false)->count();
        $averages = collect($rows)->where('has_grades', true)->pluck('globalAverage')->filter();
        $schoolAvg = $averages->isNotEmpty() ? round($averages->avg(), 1) : null;
    @endphp

    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-6 flex flex-col gap-5 overflow-visible rounded-[2rem] border border-cyan-400/20 bg-gradient-to-r from-violet-600/20 via-slate-900/40 to-cyan-500/15 p-5 shadow-2xl shadow-violet-950/30 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg shadow-cyan-500/20">
                    <i class="fa-solid fa-arrow-left text-xl text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-300">Gestión académica</p>
                    <h1 class="mt-1 text-2xl font-black text-white">Boletines de calificaciones</h1>
                    <p class="text-sm text-slate-300">Las mismas notas que carga el docente, en un informe por alumno.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        <section class="mb-6 grid gap-3 sm:grid-cols-3">
            <article class="rounded-2xl border border-emerald-400/25 bg-emerald-400/10 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-300">Con notas</p>
                <p class="mt-1 text-2xl font-black text-white">{{ $withGrades }}</p>
            </article>
            <article class="rounded-2xl border border-amber-400/25 bg-amber-400/10 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-300">Sin notas</p>
                <p class="mt-1 text-2xl font-black text-white">{{ $withoutGrades }}</p>
            </article>
            <article class="rounded-2xl border border-cyan-400/25 bg-cyan-400/10 p-4">
                <p class="text-[11px] font-bold uppercase tracking-wider text-cyan-300">Promedio del listado</p>
                <p class="mt-1 text-2xl font-black text-white">{{ $schoolAvg !== null ? $schoolAvg.'%' : '—' }}</p>
            </article>
        </section>

        <form method="GET" class="mb-6 flex flex-wrap gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar alumno…"
                   class="min-w-[200px] flex-1 rounded-xl border border-white/15 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder:text-slate-500">
            <select name="grade" class="rounded-xl border border-white/15 bg-slate-950 px-3 py-2.5 text-sm text-white">
                <option value="">Todos los grados</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-gradient-to-r from-violet-500 to-cyan-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-900/30">Filtrar</button>
        </form>

        <section class="space-y-3 md:hidden">
            @forelse($rows as $row)
                @php
                    $student = $row['student'];
                    $avg = $row['globalAverage'];
                    $tone = ! $row['has_grades'] ? 'slate' : ($avg >= 80 ? 'emerald' : ($avg >= 60 ? 'amber' : 'rose'));
                @endphp
                <article class="rounded-2xl border p-4
                    {{ $tone === 'emerald' ? 'border-emerald-400/30 bg-emerald-400/10' : '' }}
                    {{ $tone === 'amber' ? 'border-amber-400/30 bg-amber-400/10' : '' }}
                    {{ $tone === 'rose' ? 'border-rose-400/30 bg-rose-400/10' : '' }}
                    {{ $tone === 'slate' ? 'border-white/10 bg-white/[.04]' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-white">{{ $student->name }}</p>
                            <span class="mt-1 inline-flex rounded-full border border-violet-400/30 bg-violet-400/10 px-2 py-0.5 text-[11px] font-bold text-violet-200">
                                {{ $student->grade }}{{ $student->section ? ' / '.$student->section : '' }}
                            </span>
                        </div>
                        @if($row['has_grades'])
                            <span class="rounded-full px-2.5 py-1 text-xs font-black
                                {{ $tone === 'emerald' ? 'bg-emerald-400 text-emerald-950' : '' }}
                                {{ $tone === 'amber' ? 'bg-amber-400 text-amber-950' : '' }}
                                {{ $tone === 'rose' ? 'bg-rose-400 text-rose-950' : '' }}">{{ $avg }}%</span>
                        @else
                            <span class="rounded-full bg-slate-700 px-2.5 py-1 text-[11px] font-bold text-slate-200">Sin notas</span>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-slate-300">{{ $row['courses'] }} curso(s)</p>
                    <div class="mt-3 flex gap-2">
                        <a href="{{ route('director.report-card', $student->id) }}" class="flex-1 rounded-xl border border-white/15 bg-white/5 py-2 text-center text-xs font-semibold text-slate-100">Ver</a>
                        <a href="{{ route('director.report-card.pdf', $student->id) }}" class="flex-1 rounded-xl bg-gradient-to-r from-violet-500 to-cyan-500 py-2 text-center text-xs font-bold text-white">PDF</a>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/[.04] px-5 py-12 text-center text-slate-400">No hay alumnos para mostrar.</div>
            @endforelse
        </section>

        <section class="hidden overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950/40 md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-gradient-to-r from-violet-500/20 to-cyan-500/10 text-xs uppercase tracking-wider text-cyan-200">
                    <tr>
                        <th class="px-5 py-3">Alumno</th>
                        <th class="px-5 py-3">Grado</th>
                        <th class="px-5 py-3">Cursos</th>
                        <th class="px-5 py-3">Estado</th>
                        <th class="px-5 py-3">Promedio</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($rows as $row)
                        @php
                            $student = $row['student'];
                            $avg = $row['globalAverage'];
                            $tone = ! $row['has_grades'] ? 'slate' : ($avg >= 80 ? 'emerald' : ($avg >= 60 ? 'amber' : 'rose'));
                        @endphp
                        <tr class="hover:bg-white/[.04]">
                            <td class="px-5 py-4 font-semibold text-white">{{ $student->name }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full border border-violet-400/30 bg-violet-400/10 px-2.5 py-1 text-xs font-bold text-violet-200">
                                    {{ $student->grade }}{{ $student->section ? ' / '.$student->section : '' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-300">{{ $row['courses'] }}</td>
                            <td class="px-5 py-4">
                                @if($row['has_grades'])
                                    <span class="rounded-full bg-emerald-400/15 px-2.5 py-1 text-[11px] font-bold text-emerald-300">Con notas</span>
                                @else
                                    <span class="rounded-full bg-amber-400/15 px-2.5 py-1 text-[11px] font-bold text-amber-300">Pendiente</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($row['has_grades'])
                                    <strong class="{{ $tone === 'emerald' ? 'text-emerald-300' : ($tone === 'amber' ? 'text-amber-300' : 'text-rose-300') }}">{{ $avg }}%</strong>
                                @else
                                    <span class="text-slate-500">Sin notas aún</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('director.report-card', $student->id) }}" class="rounded-xl border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 hover:bg-cyan-400/20">Ver</a>
                                <a href="{{ route('director.report-card.pdf', $student->id) }}" class="rounded-xl bg-gradient-to-r from-violet-500 to-cyan-500 px-3 py-1.5 text-xs font-semibold text-white">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">No hay alumnos para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="mt-6">{{ $students->links() }}</div>
    </main>
</body>
</html>
