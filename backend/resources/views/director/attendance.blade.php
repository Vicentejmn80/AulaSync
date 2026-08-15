<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asistencia · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
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
        :root:not(.dark) .bg-white\/10 { background: var(--bg-card); }
        :root:not(.dark) .border-white\/10 { border-color: var(--nova-glass-border); }
        :root:not(.dark) .text-cyan-200 { color: var(--nova-cyan); }
    </style>
</head>
<body class="min-h-screen">
@include('partials.theme-switcher')
<main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
    <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/5 p-5 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('director.dashboard') }}" class="text-sm font-bold text-cyan-200"><i class="fa-solid fa-arrow-left mr-2"></i>Centro de mando</a>
            <p class="mt-2 text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Attendance Plus</p>
            <h1 class="mt-1 text-3xl font-black text-white">Asistencia institucional</h1>
            <p class="mt-1 text-sm text-slate-400">Vista por grado y alerta de ausentismo crónico (más de 3 faltas en el mes).</p>
        </div>
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Grado</label>
                <select name="grade" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                    <option value="">Todos</option>
                    @foreach($grades as $grade)
                        <option value="{{ $grade }}" @selected($gradeFilter === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-bold text-white">Filtrar</button>
        </form>
    </header>

    <section class="mb-8 grid gap-4 md:grid-cols-4">
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Presentes hoy</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $today['present'] }}</p>
        </article>
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Ausentes hoy</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $today['absent'] }}</p>
        </article>
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Tardíos hoy</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $today['tardy'] }}</p>
        </article>
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Tasa de asistencia hoy</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $today['rate'] !== null ? $today['rate'].'%' : '—' }}</p>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-[1.2fr_.9fr]">
        <article class="glass-card rounded-[2rem] p-6">
            <h2 class="text-xl font-black text-white">Asistencia del mes por grado</h2>
            <p class="mt-1 text-sm text-slate-400">Desde {{ \Illuminate\Support\Carbon::parse($monthStart)->format('m/Y') }}</p>
            @if($byGrade->isEmpty())
                <p class="text-sm text-slate-400">Aún no hay marcas de asistencia este mes.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-slate-400">
                            <tr>
                                <th class="py-2">Grado</th>
                                <th>Presentes</th>
                                <th>Ausentes</th>
                                <th>Tardíos</th>
                                <th>Tasa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byGrade as $row)
                                <tr class="border-t border-white/10 text-slate-200">
                                    <td class="py-3 font-bold">{{ $row->grade ?: '—' }}</td>
                                    <td>{{ $row->presents }}</td>
                                    <td>{{ $row->absents }}</td>
                                    <td>{{ $row->tardies }}</td>
                                    <td>{{ $row->rate }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>

        <article class="glass-card rounded-[2rem] p-6">
            <h2 class="text-xl font-black text-white">Ausentismo crónico</h2>
            <p class="mb-4 text-sm text-slate-400">Estudiantes con más de 3 faltas en el mes.</p>
            @if($chronic->isEmpty())
                <p class="text-sm text-slate-400">Nadie supera el umbral este mes.</p>
            @else
                <div class="space-y-3">
                    @foreach($chronic as $student)
                        <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                            <div>
                                <p class="font-bold text-white">{{ $student->name }}</p>
                                <p class="text-xs text-slate-400">{{ $student->grade }}{{ $student->section ? ' / '.$student->section : '' }}</p>
                            </div>
                            <span class="rounded-full bg-rose-500/20 px-3 py-1 text-xs font-black text-rose-200">{{ $student->absences }} faltas</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
</main>
</body>
</html>
