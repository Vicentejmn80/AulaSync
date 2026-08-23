<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cursos · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    @include('partials.director-ui-styles')
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8" x-data="{ selected: [] }">
        <header class="director-header">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-500 shadow-lg">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-indigo-600">Estructura escolar</p>
                    <h1 class="director-page-title">Cursos y secciones</h1>
                    <p class="director-page-subtitle">Crea materias, matricula el grado y asígnalas a un docente aunque aún no se haya registrado.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        @if(session('success'))
            <div class="director-alert-success mb-4">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="director-alert-warning mb-4">{{ session('warning') }}</div>
        @endif
        @if($errors->any())
            <div class="director-alert-error mb-4">{{ $errors->first() }}</div>
        @endif

        <section class="director-info-box mb-6">
            <p class="mb-1 font-semibold text-indigo-900">Cómo usar esta pantalla</p>
            <ul class="list-disc space-y-1 ps-5">
                <li><strong>Crear curso</strong>: define materia + grado y elige un docente (activo o pendiente DOC-).</li>
                <li><strong>Inscribir grado</strong>: mete en ese curso a todos los alumnos ya matriculados con el mismo grado (y sección si aplica).</li>
                <li><strong>Cambiar docente</strong>: reasigna el curso a otro profesor o a una invitación DOC- pendiente.</li>
            </ul>
        </section>

        <section class="director-card mb-6">
            <h2 class="director-section-title mb-4">Crear curso</h2>
            @if($teachers->isEmpty() && $pendingInvites->isEmpty())
                <p class="text-sm text-slate-600">Primero invita a un docente en <a class="director-link" href="{{ route('director.profesores') }}">Plantel docente</a>. Puedes asignarle el curso aunque todavía no se haya registrado.</p>
            @else
                <form method="POST"
                      action="{{ route('director.courses.store') }}"
                      class="grid gap-4 md:grid-cols-2 lg:grid-cols-6"
                      x-data="{ submitting: false }"
                      @submit="submitting = true">
                    @csrf
                    <div class="lg:col-span-2">
                        <label class="director-label" for="course-assignee">Docente *</label>
                        <select id="course-assignee" name="assignee" required class="director-select">
                            <option value="">Seleccionar docente…</option>
                            @if($pendingInvites->isNotEmpty())
                                <optgroup label="Pendientes de registro">
                                    @foreach($pendingInvites as $invite)
                                        <option value="invite:{{ $invite->id }}">{{ $invite->display_name }} · {{ $invite->invite_code }}</option>
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
                    </div>
                    <div>
                        <label class="director-label" for="course-subject">Materia *</label>
                        <input id="course-subject" name="subject_name" required placeholder="Ej: Matemática" class="director-input">
                    </div>
                    <div>
                        <label class="director-label" for="course-grade">Grado *</label>
                        <input id="course-grade" name="grade" required placeholder="Ej: 1ero" class="director-input">
                    </div>
                    <div>
                        <label class="director-label" for="course-section">Sección</label>
                        <input id="course-section" name="section" placeholder="Ej: A" class="director-input">
                    </div>
                    <div class="flex flex-col justify-end gap-3 lg:col-span-2">
                        <label class="director-checkbox-label">
                            <input type="checkbox" name="enroll_roster" value="1">
                            Inscribir alumnos del grado
                        </label>
                        <button type="submit" class="director-btn-primary w-full" :disabled="submitting">
                            <span x-show="!submitting">Crear curso</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                                <span class="director-spinner" aria-hidden="true"></span>
                                Creando…
                            </span>
                        </button>
                    </div>
                </form>
            @endif
        </section>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="director-section-title">Materias y cursos</h2>
                <form method="POST"
                      action="{{ route('director.courses.bulk-destroy') }}"
                      class="flex flex-wrap items-center gap-3"
                      x-show="selected.length"
                      x-cloak
                      onsubmit="return confirm('¿Eliminar los cursos seleccionados?')">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit" class="director-btn-danger !py-2 !text-xs">
                        <i class="fa-solid fa-trash-can"></i>
                        Eliminar seleccionados (<span x-text="selected.length"></span>)
                    </button>
                </form>
            </div>
            @if($courses->isNotEmpty())
                <label class="mb-1 inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <input type="checkbox"
                           class="h-4 w-4 accent-indigo-600"
                           @change="selected = $event.target.checked ? {{ $courses->pluck('id') }} : []"
                           :checked="selected.length && selected.length === {{ $courses->count() }}">
                    Seleccionar todos
                </label>
            @endif
            @forelse($courses as $course)
                <article class="director-card cursor-pointer"
                         onclick="window.novaContext={type:'director_course',id:{{ $course->id }},grade:@js($course->grade),section:@js($course->section),subject:@js($course->subject_name),name:@js($course->subject_name.' '.$course->grade.($course->section ? ' '.$course->section : ''))};window.AI_PAGE_CONTEXT=window.novaContext;window.dispatchEvent(new CustomEvent('ai-context-changed',{detail:window.novaContext}));">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <input type="checkbox"
                                   class="mt-1 h-4 w-4 accent-indigo-600"
                                   @change="selected = $event.target.checked ? [...selected, {{ $course->id }}] : selected.filter(id => id !== {{ $course->id }})"
                                   :checked="selected.includes({{ $course->id }})">
                            <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}</h2>
                            <p class="text-xs text-slate-600">
                                @if($course->teacher)
                                    Docente: {{ $course->teacher->name }}
                                @elseif($course->pendingInvite)
                                    Pendiente: {{ $course->pendingInvite->display_name }} · {{ $course->pendingInvite->invite_code }}
                                @else
                                    Sin asignar
                                @endif
                                · {{ $course->students_count }} alumnos
                            </p>
                            <p class="director-code mt-1">{{ $course->invite_code }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 sm:items-end">
                            <form method="POST" action="{{ route('director.courses.enroll_roster', $course) }}"
                                  onsubmit="return confirm('¿Inscribir en este curso a todos los alumnos del grado {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}?')">
                                @csrf
                                <button type="submit" class="director-btn-secondary w-full" title="Agrega a la nómina del curso los alumnos ya matriculados en ese grado">
                                    <i class="fa-solid fa-user-group"></i>
                                    Inscribir alumnos del grado
                                </button>
                            </form>
                            <form method="POST" action="{{ route('director.courses.assign', $course) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <select name="assignee" required class="director-select min-w-[12rem] text-xs">
                                    <option value="">Elegir docente…</option>
                                    @foreach($pendingInvites as $invite)
                                        <option value="invite:{{ $invite->id }}" @selected((int) $course->teacher_invite_id === (int) $invite->id && ! $course->teacher_id)>{{ $invite->display_name }} · {{ $invite->invite_code }}</option>
                                    @endforeach
                                    @foreach($teachers as $teacher)
                                        <option value="teacher:{{ $teacher->id }}" @selected($course->teacher_id === $teacher->id)>{{ $teacher->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="director-btn-secondary" title="Cambia quién imparte este curso">
                                    <i class="fa-solid fa-user-check"></i>
                                    Cambiar docente
                                </button>
                            </form>
                            <form method="POST" action="{{ route('director.courses.destroy', $course) }}" onsubmit="return confirm('¿Eliminar este curso?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="director-btn-danger w-full">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="director-card py-8 text-center text-slate-600">
                    Aún no hay cursos. Créalos aquí y asígnalos a un docente.
                </div>
            @endforelse
        </section>
    </main>
    @include('components.ai-assistant-bubble')
</body>
</html>
