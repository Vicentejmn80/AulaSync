<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Plantel Docente · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        select.director-select,
        select.director-select option,
        select.director-select optgroup {
            background-color: #0f172a;
            color: #f8fafc;
        }
    </style>
</head>
<body class="min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)]">
    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-6 flex flex-col gap-4 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Gestión institucional</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white">Plantel docente</h1>
                    <p class="mt-1 text-sm text-slate-400">Invita docentes con un código DOC-, créales el curso y matricula alumnos antes de que ellos se registren. Al entrar con ese código, heredan todo.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        @if(session('success'))
            <div class="mb-4 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-200">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="mb-6 rounded-3xl border border-white/10 bg-white/[.045] p-5">
            <h2 class="mb-3 text-lg font-bold text-white">Invitar docente</h2>
            <form method="POST" action="{{ route('director.profesores.invite') }}" class="grid gap-3 md:grid-cols-2">
                @csrf
                <input name="name" required placeholder="Nombre del docente *" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                <input name="email" type="email" placeholder="Correo (opcional)" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                <input name="subject_name" placeholder="Materia a asignar, ej. Robótica" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                <div class="grid grid-cols-2 gap-3">
                    <input name="grade" placeholder="Grado, ej. 2do" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                    <input name="section" placeholder="Sección" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Cursos existentes a asignar</label>
                    <select name="course_ids[]" multiple class="director-select min-h-[88px] w-full rounded-xl border border-white/20 bg-slate-900 px-3 py-2 text-sm text-white">
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button class="rounded-xl bg-gradient-to-r from-violet-500 to-cyan-400 px-4 py-2 text-sm font-bold text-white">
                        Generar código DOC-
                    </button>
                    <a href="{{ route('director.courses') }}" class="ml-3 text-sm font-semibold text-cyan-300">Gestionar cursos →</a>
                </div>
            </form>
        </section>

        @if($invites->isNotEmpty())
            <section class="mb-6 space-y-3">
                <h2 class="text-lg font-bold text-white">Códigos de invitación</h2>
                @foreach($invites as $invite)
                    <article class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-bold text-white">{{ $invite->name }}</p>
                                <p class="text-xs text-slate-400">{{ $invite->email ?: 'Sin correo' }} · {{ $invite->subject_name ? $invite->subject_name.' '.$invite->grade : 'Sin materia nueva' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-mono text-lg font-black tracking-wide text-cyan-200">{{ $invite->invite_code }}</p>
                                <p class="text-xs {{ $invite->isClaimed() ? 'text-emerald-300' : 'text-amber-300' }}">
                                    {{ $invite->isClaimed() ? 'Vinculado' : 'Pendiente de registro' }}
                                </p>
                            </div>
                        </div>
                        @if($invite->courses->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($invite->courses as $course)
                                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                                        {{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}
                                        · {{ $course->students_count }} alumno(s)
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-white">Docentes activos</h2>
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
                    Invita al primer docente con un código DOC-.
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
