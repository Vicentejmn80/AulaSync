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
    @include('partials.director-ui-styles')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="director-header">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-500 shadow-lg">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-indigo-600">Gestión institucional</p>
                    <h1 class="director-page-title">Plantel docente</h1>
                    <p class="director-page-subtitle">Invita docentes con un código DOC-, créales el curso y matricula alumnos antes de que ellos se registren. Al entrar con ese código, heredan todo.</p>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        @if(session('success'))
            <div class="director-alert-success mb-4">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="director-alert-error mb-4">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="director-card mb-6">
            <h2 class="director-section-title mb-4">Invitar docente</h2>
            <form method="POST"
                  action="{{ route('director.profesores.invite') }}"
                  class="grid gap-4 md:grid-cols-2"
                  x-data="{ submitting: false }"
                  @submit="submitting = true">
                @csrf
                <div>
                    <label class="director-label" for="invite-name">Nombre del docente *</label>
                    <input id="invite-name" name="name" required placeholder="Ej: María López" class="director-input">
                </div>
                <div>
                    <label class="director-label" for="invite-email">Correo (opcional)</label>
                    <input id="invite-email" name="email" type="email" placeholder="docente@colegio.edu" class="director-input">
                </div>
                <div>
                    <label class="director-label" for="invite-subject">Materia a asignar</label>
                    <input id="invite-subject" name="subject_name" placeholder="Ej: Robótica" class="director-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="director-label" for="invite-grade">Grado</label>
                        <input id="invite-grade" name="grade" placeholder="Ej: 2do" class="director-input">
                    </div>
                    <div>
                        <label class="director-label" for="invite-section">Sección</label>
                        <input id="invite-section" name="section" placeholder="Ej: A" class="director-input">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="director-label" for="invite-courses">Cursos existentes a asignar</label>
                    <select id="invite-courses" name="course_ids[]" multiple class="director-select min-h-[88px]">
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Mantén Ctrl (Windows) o Cmd (Mac) para seleccionar varios cursos.</p>
                </div>
                <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                    <button type="submit" class="director-btn-primary" :disabled="submitting">
                        <span x-show="!submitting">Generar código DOC-</span>
                        <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                            <span class="director-spinner" aria-hidden="true"></span>
                            Generando…
                        </span>
                    </button>
                    <a href="{{ route('director.courses') }}" class="director-link">Gestionar cursos →</a>
                </div>
            </form>
        </section>

        @if($invites->isNotEmpty())
            <section class="mb-6 space-y-3">
                <h2 class="director-section-title">Códigos de invitación</h2>
                @foreach($invites as $invite)
                    <article class="director-card">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-900">{{ $invite->name }}</p>
                                <p class="text-xs text-slate-600">{{ $invite->email ?: 'Sin correo' }} · {{ $invite->subject_name ? $invite->subject_name.' '.$invite->grade : 'Sin materia nueva' }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1 text-right">
                                <p class="director-code">{{ $invite->invite_code }}</p>
                                @if($invite->isClaimed())
                                    <span class="director-badge-active">Vinculado</span>
                                @else
                                    <span class="director-badge-pending">Pendiente de registro</span>
                                @endif
                            </div>
                        </div>
                        @if($invite->courses->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($invite->courses as $course)
                                    <span class="director-chip">
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
            <h2 class="director-section-title">Docentes activos</h2>
            @forelse($teachers as $teacher)
                <article class="director-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">{{ $teacher->name }}</h3>
                            <p class="text-xs text-slate-600">{{ $teacher->email }}</p>
                        </div>
                        <span class="director-badge-active">
                            {{ $teacher->courses->count() }} curso(s)
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse($teacher->courses as $course)
                            <span class="director-chip">
                                {{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / ' . $course->section : '' }}
                            </span>
                        @empty
                            <span class="text-xs italic text-slate-500">Sin cursos asignados</span>
                        @endforelse
                    </div>
                </article>
            @empty
                <div class="director-card py-8 text-center text-slate-600">
                    Invita al primer docente con un código DOC-.
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
