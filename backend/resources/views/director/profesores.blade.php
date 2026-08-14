<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plantel Docente · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
</head>
<body class="min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)]">
    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-6 flex flex-col gap-4 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Gestión Institucional</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white">Plantel Docente</h1>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        <section class="space-y-3">
            @forelse($teachers as $teacher)
                <article class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-white">{{ $teacher->name }}</h2>
                            <p class="text-xs text-slate-400">{{ $teacher->email }}</p>
                        </div>
                        <span class="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200">
                            {{ $teacher->courses->count() }} curso(s)
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse($teacher->courses as $course)
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                                {{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / ' . $course->section : '' }}
                            </span>
                        @empty
                            <span class="text-xs italic text-slate-500">Sin cursos asignados</span>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/[.045] p-8 text-center text-slate-400">
                    No hay docentes registrados para este colegio.
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
