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
        .glass-card {
            background: linear-gradient(145deg, rgba(255,255,255,.105), rgba(255,255,255,.035));
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
            backdrop-filter: blur(22px);
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
    </div>

    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                    <i class="fa-solid fa-arrow-left text-xl text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Gestión académica</p>
                    <h1 class="mt-1 text-2xl font-black text-white">Boletines</h1>
                    <p class="text-sm text-slate-400">Las mismas notas que carga el docente, en un informe por alumno.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        <form method="GET" class="mb-6 flex flex-wrap gap-3">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar alumno…"
                   class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white">
            <select name="grade" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white">
                <option value="">Todos los grados</option>
                @foreach($grades as $grade)
                    <option value="{{ $grade }}" @selected(request('grade') === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-gradient-to-r from-violet-500 to-cyan-500 px-4 py-2.5 text-sm font-bold text-white">Filtrar</button>
        </form>

        <section class="glass-card overflow-hidden rounded-[2rem]">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Alumno</th>
                        <th class="px-5 py-3">Grado</th>
                        <th class="px-5 py-3">Cursos</th>
                        <th class="px-5 py-3">Promedio</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($rows as $row)
                        @php $student = $row['student']; @endphp
                        <tr class="hover:bg-white/[.04]">
                            <td class="px-5 py-4 font-semibold text-white">{{ $student->name }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $student->grade }}{{ $student->section ? ' / '.$student->section : '' }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ $row['courses'] }}</td>
                            <td class="px-5 py-4">
                                @if($row['has_grades'])
                                    <strong class="text-cyan-200">{{ $row['globalAverage'] }}%</strong>
                                @else
                                    <span class="text-slate-500">Sin notas aún</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('director.report-card', $student->id) }}" class="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">Ver</a>
                                <a href="{{ route('director.report-card.pdf', $student->id) }}" class="rounded-xl bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">No hay alumnos para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <div class="mt-6">{{ $students->links() }}</div>
    </main>
</body>
</html>
