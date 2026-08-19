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
    @include('partials.director-ui-styles')
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    @php
        $withGrades = collect($rows)->where('has_grades', true)->count();
        $withoutGrades = collect($rows)->where('has_grades', false)->count();
        $averages = collect($rows)->where('has_grades', true)->pluck('globalAverage')->filter();
        $schoolAvg = $averages->isNotEmpty() ? round($averages->avg(), 1) : null;
    @endphp

    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="director-header">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 shadow-sm">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600">Gestión académica</p>
                    <h1 class="director-page-title">Boletines de calificaciones</h1>
                    <p class="director-page-subtitle">Las mismas notas que carga el docente, en un informe por alumno.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        <section class="mb-6 grid gap-3 sm:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Con notas</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ $withGrades }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Pendientes</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ $withoutGrades }}</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-700">Promedio del listado</p>
                <p class="mt-1 text-3xl font-black text-slate-900">{{ $schoolAvg !== null ? $schoolAvg.'%' : '—' }}</p>
            </article>
        </section>

        <form method="GET" class="director-card mb-6 flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar alumno…"
                   class="director-input min-w-[200px] flex-1">
            <select name="grade" class="director-select w-auto min-w-[10rem]">
                <option value="">Todos los grados</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
            <button class="director-btn-primary">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
        </form>

        <section class="space-y-3 md:hidden">
            @forelse($rows as $row)
                @php
                    $student = $row['student'];
                    $avg = $row['globalAverage'];
                    $ready = (bool) $row['has_grades'];
                @endphp
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-900">{{ $student->name }}</p>
                            <span class="mt-1 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-bold text-indigo-800">
                                {{ $student->grade }}{{ $student->section ? ' / '.$student->section : '' }}
                            </span>
                        </div>
                        @if($ready)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">{{ $avg }}%</span>
                        @else
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800">Pendiente</span>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ $row['courses'] }} curso(s)</p>
                    <div class="mt-3 flex gap-2">
                        <a href="{{ route('director.report-card', $student->id) }}" class="director-btn-secondary flex-1 justify-center !text-xs">Ver</a>
                        <a href="{{ route('director.report-card.pdf', $student->id) }}" class="director-btn-primary flex-1 justify-center !text-xs">
                            <i class="fa-solid fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </article>
            @empty
                <div class="director-card py-12 text-center text-slate-500">No hay alumnos para mostrar.</div>
            @endforelse
        </section>

        <section class="director-card hidden overflow-hidden !p-0 md:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Alumno</th>
                        <th class="px-5 py-3">Grado</th>
                        <th class="px-5 py-3">Cursos</th>
                        <th class="px-5 py-3">Estado</th>
                        <th class="px-5 py-3">Promedio</th>
                        <th class="px-5 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        @php
                            $student = $row['student'];
                            $avg = $row['globalAverage'];
                            $ready = (bool) $row['has_grades'];
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ $student->name }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-bold text-indigo-800">
                                    {{ $student->grade }}{{ $student->section ? ' / '.$student->section : '' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $row['courses'] }}</td>
                            <td class="px-5 py-4">
                                @if($ready)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Generado</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800">Pendiente</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($ready)
                                    <strong class="text-slate-900">{{ $avg }}%</strong>
                                @else
                                    <span class="text-slate-400">Sin notas aún</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('director.report-card', $student->id) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Ver</a>
                                <a href="{{ route('director.report-card.pdf', $student->id) }}" class="inline-flex rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">No hay alumnos para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="mt-6">{{ $students->links() }}</div>
    </main>
</body>
</html>
