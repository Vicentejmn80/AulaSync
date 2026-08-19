<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mis Cursos · AulaSync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.teacher-mobile')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, sans-serif; overflow-x: hidden; }
        .card-hover { transition: transform .18s, box-shadow .18s; }
        .card-hover:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(139,92,246,.12); }
        @media (max-width: 767px) {
            nav .max-w-6xl { overflow-x: auto; scrollbar-width: none; }
            nav a { white-space: nowrap; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

{{-- ── Top nav tabs ─────────────────────────────────────── --}}
<nav class="bg-white border-b border-slate-100 sticky top-0 z-30">
    <div class="max-w-6xl mx-auto px-4 flex items-center gap-1 h-14">
        <a href="{{ route('dashboard') }}"
           class="text-slate-400 hover:text-violet-600 mr-3 transition text-sm">
            <i class="fa-solid fa-arrow-left text-xs mr-1"></i> Dashboard
        </a>
        <span class="text-slate-200 mx-1">|</span>
        <a href="{{ route('teacher.courses.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-semibold bg-violet-100 text-violet-700">
            <i class="fa-solid fa-chalkboard mr-1.5"></i> Cursos
        </a>
        <a href="{{ route('teacher.activities.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium text-slate-500 hover:bg-slate-100 transition">
            <i class="fa-solid fa-clipboard-list mr-1.5"></i> Actividades
        </a>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-8" x-data="coursesPage()">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Mis Cursos</h1>
            <p class="text-sm text-slate-500 mt-1">
                Cursos asignados por el director. Puedes vincular alumnos de la nómina del colegio.
            </p>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-6 flex items-center gap-3 bg-amber-50 border border-amber-200
                    text-amber-800 rounded-2xl px-5 py-4 text-sm font-medium">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ session('error') }}
        </div>
    @endif

    {{-- ── Flash ──────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200
                    text-emerald-700 rounded-2xl px-5 py-4 text-sm font-medium">
            <i class="fa-solid fa-circle-check text-emerald-500"></i> {{ session('success') }}
        </div>
    @endif

    {{-- ── Empty state ────────────────────────────────────── --}}
    @if($courses->isEmpty())
        <div class="text-center py-20">
            <div class="w-20 h-20 bg-violet-50 rounded-3xl flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-chalkboard text-3xl text-violet-300"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 mb-2">Aún no tienes cursos asignados</h3>
            <p class="text-slate-400 text-sm mb-6">El director debe crearte la materia y darte un código DOC- para vincularte.</p>
        </div>
    @else

    {{-- ── Course cards grid ──────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($courses as $course)
        <div class="bg-white rounded-3xl border border-slate-100 p-6 card-hover
                    flex flex-col gap-4"
             x-data="courseCard({{ $course->id }})">

            {{-- Card header --}}
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <span class="inline-block text-xs font-bold uppercase tracking-widest
                                 text-violet-500 mb-1">
                        {{ $course->grade }}
                        @if($course->section) · Sección {{ $course->section }} @endif
                    </span>
                    <h3 class="text-base font-bold text-slate-800 truncate">{{ $course->subject_name }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $course->school_year }}</p>
                </div>
                <div class="flex items-center gap-2 ml-3">
                    {{-- Import students button --}}
                    <button @click="openImport = true"
                            title="Vincular alumnos de la nómina"
                            class="w-8 h-8 rounded-xl bg-indigo-50 hover:bg-indigo-100
                                   text-indigo-500 flex items-center justify-center transition">
                        <i class="fa-solid fa-file-import text-xs"></i>
                    </button>
                </div>
            </div>

            {{-- Stats row --}}
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5">
                    <i class="fa-solid fa-users text-violet-400"></i>
                    <strong class="text-slate-700">{{ $course->students_count }}</strong> alumnos
                </span>
                <span class="flex items-center gap-1.5">
                    <i class="fa-solid fa-clipboard-list text-violet-400"></i>
                    <strong class="text-slate-700">{{ $course->activities->count() }}</strong> actividades
                </span>
            </div>

            {{-- Student list preview --}}
            @if($course->students_count > 0)
            <div class="border-t border-slate-50 pt-3">
                <div x-show="!expanded" class="flex flex-wrap gap-1.5">
                    @foreach($course->students->take(5) as $student)
                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600
                                     text-xs font-medium px-2.5 py-1 rounded-full">
                            <i class="fa-solid fa-user text-slate-400 text-[10px]"></i>
                            {{ $student->name }}
                        </span>
                    @endforeach
                    @if($course->students_count > 5)
                        <button @click="expanded = true"
                                class="text-xs text-violet-500 hover:underline px-2">
                            +{{ $course->students_count - 5 }} más
                        </button>
                    @endif
                </div>
                <div x-show="expanded" x-cloak class="space-y-1 max-h-48 overflow-y-auto">
                    @foreach($course->students as $student)
                        <div class="flex items-center justify-between py-1
                                    border-b border-slate-50 last:border-0">
                            <span class="text-xs text-slate-700">{{ $student->name }}</span>
                            <button
                                @click="removeStudent({{ $student->id }}, $el.closest('div'))"
                                class="text-red-300 hover:text-red-500 transition text-xs ml-2">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    @endforeach
                    <button @click="expanded = false"
                            class="text-xs text-slate-400 hover:text-slate-600 mt-1">
                        Mostrar menos
                    </button>
                </div>
            </div>
            @endif

            {{-- Actions footer --}}
            <div class="flex gap-2 mt-auto pt-2 border-t border-slate-50">
                <a href="{{ route('teacher.activities.index') }}?course={{ $course->id }}"
                   class="flex-1 text-center text-xs font-semibold text-violet-600
                          hover:bg-violet-50 py-2 rounded-xl transition">
                    <i class="fa-solid fa-clipboard-list mr-1"></i> Actividades
                </a>
                <a href="{{ route('teacher.hub', ['course' => $course->id, 'open_grades' => 1]) }}"
                   class="flex-1 text-center text-xs font-semibold text-cyan-300
                          hover:bg-cyan-500/10 py-2 rounded-xl transition border border-cyan-400/20">
                    <i class="fa-solid fa-table-cells mr-1"></i> Cargar Notas
                </a>
            </div>

            {{-- ── Import modal (per-card) ───────────────── --}}
            <div x-show="openImport" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background:rgba(15,23,42,.55);backdrop-filter:blur(4px);">
                <div @click.outside="openImport = false"
                     class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">

                    <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
                        <h3 class="font-bold">Importación Rápida de Alumnos</h3>
                        <p class="text-xs text-indigo-200 mt-0.5">Curso: {{ $course->full_name }}</p>
                    </div>

                    <div class="px-6 py-5">
                        <p class="text-xs text-slate-500 mb-3">
                            <i class="fa-solid fa-lightbulb text-amber-400 mr-1"></i>
                            Pega la lista de nombres, <strong>uno por línea</strong>.
                            Si un alumno ya existe en otro curso, solo se inscribirá aquí sin duplicarse.
                        </p>

                        <textarea
                            x-model="importNames"
                            rows="8"
                            placeholder="María López&#10;Juan Pérez&#10;Carlos Rodríguez&#10;Ana Martínez"
                            class="w-full border border-slate-200 rounded-2xl p-4 text-sm
                                   font-mono text-slate-700 resize-none focus:outline-none
                                   focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition"
                        ></textarea>

                        <div x-show="importResult" x-cloak
                             class="mt-3 bg-emerald-50 border border-emerald-200 rounded-xl
                                    px-4 py-3 text-sm text-emerald-700"
                             x-text="importResult">
                        </div>

                        <div x-show="importError" x-cloak
                             class="mt-3 bg-red-50 border border-red-200 rounded-xl
                                    px-4 py-3 text-sm text-red-600"
                             x-text="importError">
                        </div>

                        <div class="flex items-center justify-between mt-5 pt-4 border-t border-slate-100">
                            <button @click="openImport = false; importResult = ''; importError = ''"
                                    class="text-sm text-slate-400 hover:text-slate-600">
                                Cerrar
                            </button>
                            <button
                                @click="doImport({{ $course->id }})"
                                :disabled="importing || !importNames.trim()"
                                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       text-white font-semibold px-5 py-2.5 rounded-xl transition"
                            >
                                <i class="fa-solid" :class="importing ? 'fa-spinner fa-spin' : 'fa-file-import'"></i>
                                <span x-text="importing ? 'Importando…' : 'Importar lista'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            {{-- end import modal --}}

        </div>
        @endforeach
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- CREATE COURSE MODAL                                    --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div x-show="openCreate" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(15,23,42,.55);backdrop-filter:blur(4px);"
         @keydown.escape.window="openCreate = false">
        <div @click.outside="openCreate = false"
             class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">

            <div class="px-6 py-4 bg-gradient-to-r from-violet-600 to-indigo-600 text-white">
                <h3 class="font-bold">Crear nuevo curso</h3>
                <p class="text-xs text-violet-200 mt-0.5">Define la materia y la sección</p>
            </div>

            <form method="POST" action="{{ route('teacher.courses.store') }}" class="px-6 py-5 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                        Materia <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="subject_name" required
                           placeholder="Ej: Matemáticas, Inglés, Ciencias…"
                           value="{{ old('subject_name') }}"
                           class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm
                                  focus:outline-none focus:border-violet-400 focus:ring-2
                                  focus:ring-violet-100 transition">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                            Grado <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="grade" required
                               placeholder="Ej: 3ro Primaria"
                               value="{{ old('grade') }}"
                               class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm
                                      focus:outline-none focus:border-violet-400 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1.5">Sección</label>
                        <input type="text" name="section" maxlength="10"
                               placeholder="A, B, C…"
                               value="{{ old('section') }}"
                               class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm
                                      focus:outline-none focus:border-violet-400 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Año escolar</label>
                    <input type="text" name="school_year" maxlength="9"
                           placeholder="{{ date('Y') }}-{{ date('Y') + 1 }}"
                           value="{{ old('school_year', date('Y').'-'.(date('Y')+1)) }}"
                           class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm
                                  focus:outline-none focus:border-violet-400 transition">
                </div>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-600">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button type="button" @click="openCreate = false"
                            class="text-sm text-slate-400 hover:text-slate-600">Cancelar</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700
                                   text-white font-semibold px-5 py-2.5 rounded-xl transition">
                        <i class="fa-solid fa-plus"></i> Crear curso
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function coursesPage() {
    return {
        openCreate: {{ $errors->any() ? 'true' : 'false' }},
    };
}

function courseCard(courseId) {
    return {
        openImport:   false,
        importing:    false,
        importNames:  '',
        importResult: '',
        importError:  '',
        expanded:     false,

        async doImport(id) {
            if (!this.importNames.trim()) return;
            this.importing    = true;
            this.importResult = '';
            this.importError  = '';

            try {
                const res = await fetch(`/teacher/courses/${id}/import-students`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify({ names: this.importNames }),
                });
                const json = await res.json();

                if (!res.ok) {
                    this.importError = json.error ?? 'Error al importar.';
                } else {
                    this.importResult = json.message;
                    this.importNames  = '';
                    // Reload page after 1.8s so cards refresh with new student counts
                    setTimeout(() => window.location.reload(), 1800);
                }
            } catch {
                this.importError = 'No se pudo conectar con el servidor.';
            } finally {
                this.importing = false;
            }
        },

        async removeStudent(studentId, rowEl) {
            if (!confirm('¿Quitar este alumno del curso?')) return;
            const res = await fetch(
                `/teacher/courses/${courseId}/students/${studentId}`,
                { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } }
            );
            if (res.ok) rowEl.remove();
        },

        async deleteCourse() {
            if (!confirm('¿Eliminar este curso? Se perderán las actividades y notas asociadas.')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/teacher/courses/${courseId}`;
            form.innerHTML = `<input name="_token" value="${CSRF}">
                              <input name="_method" value="DELETE">`;
            document.body.appendChild(form);
            form.submit();
        },
    };
}
</script>
@include('components.ai-assistant-bubble')
</body>
</html>
