<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cursos · Director</title>
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
        select.director-select option:disabled {
            color: #94a3b8;
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
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Estructura escolar</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white">Cursos y secciones</h1>
                    <p class="mt-1 text-sm text-slate-400">Crea materias, matricula el grado y asígnalas a un docente aunque aún no se haya registrado.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        @if(session('success'))
            <div class="mb-4 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-200">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="mb-4 rounded-2xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm font-semibold text-amber-200">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">{{ $errors->first() }}</div>
        @endif

        <section class="mb-4 rounded-2xl border border-cyan-400/20 bg-cyan-400/5 p-4 text-sm text-slate-300">
            <p class="font-semibold text-cyan-200 mb-1">Cómo usar esta pantalla</p>
            <ul class="list-disc ps-5 space-y-1 text-slate-400">
                <li><strong class="text-slate-200">Crear curso</strong>: define materia + grado y elige un docente (activo o pendiente DOC-).</li>
                <li><strong class="text-slate-200">Inscribir grado</strong>: mete en ese curso a todos los alumnos ya matriculados con el mismo grado (y sección si aplica).</li>
                <li><strong class="text-slate-200">Cambiar docente</strong>: reasigna el curso a otro profesor o a una invitación DOC- pendiente.</li>
            </ul>
        </section>

        <section class="mb-6 rounded-3xl border border-white/10 bg-white/[.045] p-5">
            <h2 class="mb-3 text-lg font-bold text-white">Crear curso</h2>
            @if($teachers->isEmpty() && $pendingInvites->isEmpty())
                <p class="text-sm text-slate-400">Primero invita a un docente en <a class="text-cyan-300" href="{{ route('director.profesores') }}">Plantel docente</a>. Puedes asignarle el curso aunque todavía no se haya registrado.</p>
            @else
                <form method="POST" action="{{ route('director.courses.store') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
                    @csrf
                    <select name="assignee" required class="director-select rounded-xl border border-white/20 bg-slate-900 px-3 py-2 text-sm text-white">
                        <option value="">Docente *</option>
                        @if($pendingInvites->isNotEmpty())
                            <optgroup label="Pendientes de registro">
                                @foreach($pendingInvites as $invite)
                                    <option value="invite:{{ $invite->id }}">{{ $invite->name }} · {{ $invite->invite_code }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($teachers->isNotEmpty())
                            <optgroup label="Docentes activos">
                                @foreach($teachers as $teacher)
                                    <option value="teacher:{{ $teacher->id }}">{{ $teacher->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <input name="subject_name" required placeholder="Materia *" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500">
                    <input name="grade" required placeholder="Grado * (ej. 1ero)" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500">
                    <input name="section" placeholder="Sección (ej. A)" class="rounded-xl border border-white/10 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500">
                    <label class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-300">
                        <input type="checkbox" name="enroll_roster" value="1" class="rounded border-white/20">
                        Inscribir alumnos del grado
                    </label>
                    <button class="rounded-xl bg-gradient-to-r from-violet-500 to-cyan-400 px-4 py-2 text-sm font-bold text-white">Crear curso</button>
                </form>
            @endif
        </section>

        <section class="space-y-3">
            @forelse($courses as $course)
                <article class="rounded-2xl border border-white/10 bg-white/[.045] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-white">{{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}</h2>
                            <p class="text-xs text-slate-400">
                                @if($course->teacher)
                                    Docente: {{ $course->teacher->name }}
                                @elseif($course->pendingInvite)
                                    Pendiente: {{ $course->pendingInvite->name }} · {{ $course->pendingInvite->invite_code }}
                                @else
                                    Sin asignar
                                @endif
                                · {{ $course->students_count }} alumnos
                            </p>
                            <p class="mt-1 font-mono text-sm font-bold tracking-wide text-cyan-200">{{ $course->invite_code }}</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:items-end">
                            <form method="POST" action="{{ route('director.courses.enroll_roster', $course) }}"
                                  onsubmit="return confirm('¿Inscribir en este curso a todos los alumnos del grado {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}?')">
                                @csrf
                                <button type="submit" class="w-full rounded-xl border border-cyan-400/30 bg-cyan-400/10 px-3 py-2 text-xs font-semibold text-cyan-100 hover:bg-cyan-400/20" title="Agrega a la nómina del curso los alumnos ya matriculados en ese grado">
                                    <i class="fa-solid fa-user-group mr-1"></i>Inscribir alumnos del grado
                                </button>
                            </form>
                            <form method="POST" action="{{ route('director.courses.assign', $course) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <select name="assignee" required class="director-select min-w-[12rem] rounded-xl border border-white/20 bg-slate-900 px-2 py-2 text-xs text-white">
                                    <option value="">Elegir docente…</option>
                                    @foreach($pendingInvites as $invite)
                                        <option value="invite:{{ $invite->id }}" @selected((int) $course->teacher_invite_id === (int) $invite->id && ! $course->teacher_id)>{{ $invite->name }} · {{ $invite->invite_code }}</option>
                                    @endforeach
                                    @foreach($teachers as $teacher)
                                        <option value="teacher:{{ $teacher->id }}" @selected($course->teacher_id === $teacher->id)>{{ $teacher->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-xl border border-violet-400/30 bg-violet-400/10 px-3 py-2 text-xs font-semibold text-violet-100 hover:bg-violet-400/20" title="Cambia quién imparte este curso">
                                    <i class="fa-solid fa-user-check mr-1"></i>Cambiar docente
                                </button>
                            </form>
                            <form method="POST" action="{{ route('director.courses.destroy', $course) }}" onsubmit="return confirm('¿Eliminar este curso?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-xl border border-rose-400/30 px-3 py-2 text-xs font-semibold text-rose-200">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-white/10 bg-white/[.045] p-8 text-center text-slate-400">
                    Aún no hay cursos. Créalos aquí y asígnalos a un docente.
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
