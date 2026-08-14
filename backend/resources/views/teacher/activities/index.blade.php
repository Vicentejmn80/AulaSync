<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Actividades · Aulasync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        :root {
            --grad-primary: linear-gradient(135deg, #7c3aed 0%, #c026d3 100%);
            --grad-nav:     linear-gradient(135deg, rgba(124,58,237,.12), rgba(192,38,211,.08));
        }
        html.dark {
            --grad-nav:     linear-gradient(160deg, #1e0f3c 0%, #2d1569 60%, #4a1072 100%);
        }
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, sans-serif; background:var(--bg-primary); color:var(--text-primary); }
        .weight-bar { transition: width .4s ease; }
        .btn-gradient {
            background: var(--grad-primary); color:#fff; font-weight:700;
            border-radius:.875rem; border:none; cursor:pointer;
            transition: opacity .15s, transform .15s;
        }
        .btn-gradient:hover { opacity:.9; transform:translateY(-1px); }
        /* Type badges */
        .badge-clase { background:linear-gradient(135deg,#ede9fe,#fce7f3); color:#6d28d9; }
        .badge-actividad { background:linear-gradient(135deg,#fce7f3,#ede9fe); color:#a21caf; }
        .badge-tarea { background:linear-gradient(135deg,#fdf4ff,#fecdd3); color:#c026d3; }
        .type-tab { border:none; border-radius:.875rem; font-size:.8rem; font-weight:600; padding:.45rem 1rem; cursor:pointer; transition:all .15s; }
        .type-tab.active-all { background:linear-gradient(135deg,#7c3aed,#a21caf); color:#fff; }
        .type-tab.active-clase { background:linear-gradient(135deg,#7c3aed,#a21caf); color:#fff; }
        .type-tab.active-actividad { background:linear-gradient(135deg,#c026d3,#db2777); color:#fff; }
        .type-tab:not([class*="active"]) { background:var(--bg-tertiary); color:var(--text-secondary); border:1px solid var(--nova-glass-border); }
    /* Light mode overrides for hardcoded dark Tailwind classes */
    :root:not(.dark) .bg-slate-800\/50,
    :root:not(.dark) .bg-slate-800\/60,
    :root:not(.dark) .bg-slate-900\/40,
    :root:not(.dark) .bg-slate-900\/70 { background: var(--bg-card) !important; }
    :root:not(.dark) .border-slate-700\/50,
    :root:not(.dark) .border-slate-700\/60,
    :root:not(.dark) .border-slate-600\/60 { border-color: var(--nova-glass-border) !important; }
    :root:not(.dark) .hover\:bg-slate-700\/30:hover { background: var(--nova-glass) !important; }
    :root:not(.dark) .hover\:bg-white\/10:hover { background: rgba(124,58,237,.08) !important; }
    :root:not(.dark) .text-slate-100 { color: var(--text-primary); }
    :root:not(.dark) .text-slate-200 { color: var(--text-secondary); }
    :root:not(.dark) .text-slate-300 { color: var(--text-secondary); }
    :root:not(.dark) .text-slate-400 { color: var(--text-tertiary); }
    :root:not(.dark) .text-slate-500 { color: var(--text-tertiary); }
    :root:not(.dark) .text-slate-600 { color: var(--text-secondary); }
    :root:not(.dark) .shadow-black\/20 { box-shadow: var(--nova-shadow); }
    :root:not(.dark) nav .text-purple-300 { color: #6D28D9; }
    :root:not(.dark) nav .text-purple-700 { color: #6D28D9; }
    :root:not(.dark) nav .bg-white\/15 { background: rgba(124,58,237,.12); }
    :root:not(.dark) .text-purple-300 { color: #6D28D9; }
    :root:not(.dark) .text-violet-200 { color: #6D28D9; }
    :root:not(.dark) .text-violet-300 { color: #6D28D9; }
    :root:not(.dark) .text-violet-600 { color: #7C3AED; }
    :root:not(.dark) .text-purple-600 { color: #7C3AED; }
    :root:not(.dark) .border-violet-400\/20 { border-color: rgba(124,58,237,.25); }
    :root:not(.dark) .bg-violet-500\/10 { background: rgba(124,58,237,.08); }
    :root:not(.dark) .bg-violet-600 { background: #7C3AED; }
    :root:not(.dark) .hover\:bg-violet-700:hover { background: #6D28D9; }
    /* Modal base */
    .modal-bg { background: var(--bg-primary); color: var(--text-primary); }
    :root:not(.dark) .bg-white { background: var(--bg-secondary) !important; }
    html.dark .bg-white { background: var(--bg-secondary) !important; }
    :root:not(.dark) .border-slate-200 { border-color: var(--nova-glass-border); }
    :root:not(.dark) .text-slate-600.label { color: var(--text-secondary); }
    :root:not(.dark) .text-slate-400.label { color: var(--text-tertiary); }
    :root:not(.dark) .border-slate-100 { border-color: var(--nova-glass-border); }
    </style>
</head>
<body class="min-h-screen">

{{-- ── Top nav bar ─────────────────────────────────────── --}}
<nav class="sticky top-0 z-30" style="background:var(--grad-nav);">
    <div class="max-w-6xl mx-auto px-4 flex items-center gap-1 h-14">
        <a href="{{ route('teacher.hub') }}"
           class="text-purple-300 hover:text-white mr-3 transition text-sm flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left text-xs"></i> Hub
        </a>
        <span class="text-purple-700 mx-1">|</span>
        <a href="{{ route('teacher.courses.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-medium text-purple-300 hover:text-white hover:bg-white/10 transition">
            <i class="fa-solid fa-chalkboard mr-1.5"></i> Cursos
        </a>
        <a href="{{ route('teacher.activities.index') }}"
           class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-white/15">
            <i class="fa-solid fa-clipboard-list mr-1.5"></i> Actividades
        </a>
        <div class="ml-auto">
            @include('components.ai-assistant-bubble')
        </div>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-8" x-data="activitiesPage()" x-init="init()">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black" style="background:var(--grad-primary);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                Clases &amp; Actividades
            </h1>
            <p class="text-sm text-slate-300 mt-1">
                Lecciones teóricas y evaluaciones de todos tus cursos.
            </p>
        </div>
        <button @click="openCreate = true"
                class="btn-gradient inline-flex items-center gap-2 px-5 py-3 shadow-lg shrink-0"
                style="box-shadow:0 4px 16px rgba(124,58,237,.3);">
            <i class="fa-solid fa-plus"></i> Nueva
        </button>
    </div>

    {{-- ── Type filter tabs ───────────────────────────────── --}}
    <div class="flex gap-2 mb-5">
        <button class="type-tab"
                :class="filterType === 'all' ? 'active-all' : ''"
                @click="filterType = 'all'">
            ✦ Todas
            <span class="ml-1.5 text-[10px] font-bold opacity-70"
                  x-text="countByType('all')"></span>
        </button>
        <button class="type-tab"
                :class="filterType === 'clase' ? 'active-clase' : ''"
                @click="filterType = 'clase'">
            🏫 Clases
            <span class="ml-1.5 text-[10px] font-bold opacity-70"
                  x-text="countByType('clase')"></span>
        </button>
        <button class="type-tab"
                :class="filterType === 'tarea' ? 'active-actividad' : ''"
                @click="filterType = 'tarea'">
            🧩 Tareas
            <span class="ml-1.5 text-[10px] font-bold opacity-70"
                  x-text="countByType('tarea')"></span>
        </button>
        <button class="type-tab"
                :class="filterType === 'evaluacion' ? 'active-actividad' : ''"
                @click="filterType = 'evaluacion'">
            📊 Evaluaciones
            <span class="ml-1.5 text-[10px] font-bold opacity-70"
                  x-text="countByType('evaluacion')"></span>
        </button>
    </div>

    {{-- ── Flash ──────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-emerald-500/10 border border-emerald-400/30
                    text-emerald-200 rounded-2xl px-5 py-4 text-sm font-medium">
            <i class="fa-solid fa-circle-check text-emerald-500"></i> {{ session('success') }}
        </div>
    @endif

    {{-- ── Course filter pills ──────────────────────────────── --}}
    @if($courses->isNotEmpty())
    <div class="flex flex-wrap gap-2 mb-6">
        <button
            @click="filterCourse = null"
            :class="filterCourse === null ? 'bg-violet-500/20 text-violet-200 font-semibold border-violet-400/30' : 'bg-slate-800/60 text-slate-300 hover:bg-slate-700/70 border-slate-600/60'"
            class="px-3 py-1.5 rounded-full text-xs border transition"
        >
            Todos los cursos
        </button>
        @foreach($courses as $course)
        <button
            @click="filterCourse = {{ $course->id }}"
            :class="filterCourse === {{ $course->id }} ? 'bg-violet-500/20 text-violet-200 font-semibold border-violet-400/30' : 'bg-slate-800/60 text-slate-300 hover:bg-slate-700/70 border-slate-600/60'"
            class="px-3 py-1.5 rounded-full text-xs border transition"
        >
            {{ $course->subject_name }} · {{ $course->grade }}
            @if($course->section) / {{ $course->section }}@endif
        </button>
        @endforeach
    </div>
    @endif

    {{-- ── Empty state ────────────────────────────────────── --}}
    @if($activities->isEmpty())
        <div class="text-center py-20">
            <div class="w-20 h-20 bg-violet-500/10 rounded-3xl flex items-center justify-center mx-auto mb-5 border border-violet-400/20">
                <i class="fa-solid fa-clipboard-list text-3xl text-violet-300"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-100 mb-2">Sin actividades aún</h3>
            <p class="text-slate-300 text-sm mb-6">
                @if($courses->isEmpty())
                    Primero <a href="{{ route('teacher.courses.index') }}" class="text-violet-300 underline">crea un curso</a>
                    para poder asignarle actividades.
                @else
                    Crea tu primera actividad y empieza a registrar notas.
                @endif
            </p>
            @if($courses->isNotEmpty())
            <button @click="openCreate = true"
                    class="inline-flex items-center gap-2 bg-violet-600 text-white
                           font-semibold px-5 py-3 rounded-2xl hover:bg-violet-700 transition">
                <i class="fa-solid fa-plus"></i> Crear actividad
            </button>
            @endif
        </div>
    @else

    {{-- ── Activities table ───────────────────────────────── --}}
    <div class="bg-slate-800/50 rounded-3xl border border-slate-700/60 overflow-hidden shadow-2xl shadow-black/20">

        {{-- Table header --}}
        <div class="grid grid-cols-12 gap-3 px-6 py-3 border-b border-slate-700/60
                    text-xs font-bold uppercase tracking-wider text-slate-300"
             style="background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(56,189,248,.08))">
            <div class="col-span-4">Clase / Actividad</div>
            <div class="col-span-2">Curso</div>
            <div class="col-span-1 text-center">Máx.</div>
            <div class="col-span-2 text-center">Peso</div>
            <div class="col-span-2">Entrega</div>
            <div class="col-span-1 text-center">Acción</div>
        </div>

        {{-- Rows --}}
        <template x-for="activity in filteredActivities()" :key="activity.id">
            <div x-data="{ expanded: false }"
                class="grid grid-cols-12 gap-3 items-center px-6 py-4 border-b border-slate-700/50 last:border-0 hover:bg-slate-700/30 transition"
            >
                    <div class="col-span-4 flex items-start gap-3">
                        <button @click="expanded = !expanded"
                                class="w-8 h-8 rounded-xl border border-slate-600 bg-slate-900/70 flex items-center justify-center text-slate-200 hover:border-cyan-400 transition transform duration-300"
                                :class="expanded ? 'rotate-180' : ''">
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="flex-1">
                            <div class="flex items-center gap-1.5 mb-0.5">
                                <span class="text-[9px] font-bold px-2 py-0.5 rounded-full"
                                      :class="activity.type === 'clase'
                                              ? 'badge-clase'
                                              : ((activity.is_homework || activity.type === 'tarea') ? 'badge-tarea' : 'badge-actividad')">
                                    <span x-text="activity.type === 'clase'
                                                    ? '🏫 Clase'
                                                    : ((activity.is_homework || activity.type === 'tarea') ? '🧩 Tarea' : '📊 Evaluación')"></span>
                                </span>
                            </div>
                            <p class="font-semibold text-slate-100 text-sm truncate" x-text="activity.title"></p>
                        </div>
                    </div>

                <div class="col-span-2">
                    <span class="inline-block bg-violet-500/10 text-violet-200 text-xs font-semibold px-2.5 py-1 rounded-full truncate border border-violet-400/20"
                          x-text="activity.course_name"></span>
                </div>

                <div class="col-span-1 text-center">
                    <span class="text-sm font-bold text-slate-100" x-text="activity.max_score"></span>
                    <span class="text-xs text-slate-400"> pts</span>
                </div>

                <div class="col-span-2">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-slate-700 rounded-full h-1.5 overflow-hidden">
                            <div class="weight-bar h-1.5 rounded-full"
                                 :style="`width:${Math.min(activity.weight_percentage,100)}%;background:${activity.type === 'clase' ? '#7c3aed' : '#c026d3'}`">
                            </div>
                        </div>
                        <span class="text-xs font-bold text-purple-600 w-9 text-right"
                              x-text="Math.round(activity.weight_percentage ?? 0) + '%'"></span>
                    </div>
                </div>

                <div class="col-span-2">
                    <span class="text-xs text-slate-300 font-medium" x-text="formatDate(activity.due_date)"></span>
                </div>

                <div class="col-span-1 flex items-center justify-center gap-1.5">
                    <template x-if="activity.type !== 'clase'">
                        <a :href="`{{ route('teacher.hub') }}?course=${activity.course_id}&open_grades=1&activity=${activity.id}`"
                           title="Cargar notas"
                           class="btn-gradient inline-flex items-center gap-1 text-xs font-bold px-3 py-1.5 whitespace-nowrap">
                            <i class="fa-solid fa-table-cells"></i>
                            <span class="hidden sm:inline">Cargar Notas</span>
                        </a>
                        <button
                            type="button"
                            @click="editActivityWithAI(activity.id, activity.title)"
                            title="Editar con IA"
                            class="w-7 h-7 rounded-lg bg-violet-50 hover:bg-violet-100
                                   text-violet-600 flex items-center justify-center transition">
                            <i class="fa-solid fa-wand-magic-sparkles text-xs"></i>
                        </button>
                    </template>
                    <template x-if="activity.type === 'clase'">
                        <span class="text-[10px] text-purple-300 italic px-2">Teórica</span>
                    </template>
                    <button
                        @click="deleteActivity(activity.id)"
                        title="Eliminar"
                        class="w-7 h-7 rounded-lg bg-fuchsia-50 hover:bg-fuchsia-100
                               text-fuchsia-500 flex items-center justify-center transition">
                        <i class="fa-solid fa-trash-alt text-xs"></i>
                    </button>
                </div>

                <div x-show="expanded" x-transition class="col-span-12 px-6 pb-4 border-t border-slate-700/50 bg-slate-900/40">
                    <div class="grid gap-4 grid-cols-1" :class="activity.tareas?.length ? 'md:grid-cols-2' : 'md:grid-cols-1'">
                        <div class="bg-slate-800/60 rounded-2xl p-4 text-sm text-slate-300 border border-slate-700/60 shadow-sm">
                            <p class="font-semibold text-slate-100 mb-1">Descripción</p>
                            <p x-text="activity.description || 'Sin descripción adicional.'"></p>
                        </div>
                        <div x-show="activity.nee_adaptation" class="bg-emerald-50/80 rounded-2xl p-4 text-sm text-emerald-700 border border-emerald-100 shadow-sm">
                            <p class="font-semibold text-emerald-800 mb-1">
                                <i class="fa-solid fa-book-open-reader mr-1"></i>
                                <span x-text="`📘 Guía de Adaptación para ${activity.nee_type || 'NEE'}`"></span>
                            </p>
                            <p x-text="activity.nee_adaptation"></p>
                        </div>
                        <div>
                            <template x-if="activity.tareas?.length">
                                <div class="space-y-3">
                                    <template x-for="tarea in activity.tareas" :key="tarea.id">
                                        <div class="bg-white/90 border border-violet-100 rounded-2xl px-4 py-3 shadow-sm">
                                            <div class="flex items-center justify-between gap-4">
                                                <div>
                                                    <p class="font-semibold text-sm" x-text="tarea.titulo"></p>
                                                    <p class="text-[11px] text-slate-500" x-text="tarea.descripcion || 'Sin descripción.'"></p>
                                                </div>
                                                <div class="text-right text-xs text-slate-400">
                                                    <p><strong class="text-slate-600" x-text="tarea.puntos + ' pts'"></strong></p>
                                                    <p x-text="tarea.fecha_entrega ? 'Entrega ' + formatDate(tarea.fecha_entrega) : 'Sin fecha'"></p>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between text-[11px]">
                                                <span>Nota: <strong class="text-slate-700" x-text="tarea.calificacion !== null ? Number(tarea.calificacion).toFixed(2) : 'Pendiente'"></strong></span>
                                                <button class="text-violet-600 font-semibold" @click="openGradeModal(tarea)">
                                                    Calificar
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <div x-show="!activity.tareas?.length" class="text-xs text-slate-500 mt-1">
                                <p>No hay detalles adicionales para esta actividad.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Pagination --}}
        @if($activities->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">
                {{ $activities->links() }}
            </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════ --}}
    {{-- CREATE ACTIVITY MODAL                                  --}}
    {{-- ══════════════════════════════════════════════════════ --}}
    <div x-show="openCreate" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(2,6,23,.72);backdrop-filter:blur(8px);"
         @keydown.escape.window="openCreate = false">
        <div @click.outside="openCreate = false"
             class="w-full max-w-2xl rounded-3xl border border-slate-700/60 bg-slate-900/95 shadow-2xl shadow-black/40 overflow-hidden">

            <div class="px-6 py-5 border-b border-slate-700/60 bg-slate-900">
                <h3 class="font-semibold text-lg text-slate-100">Crear actividad</h3>
                <p class="text-xs text-slate-400 mt-1">Flujo rápido para crear clases y evaluaciones con diseño limpio.</p>
            </div>

            <form method="POST" action="{{ route('teacher.activities.store') }}"
                  class="px-6 py-5 space-y-4" x-data="activityForm()">
                @csrf

                {{-- Campos esenciales --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">
                        Curso <span class="text-red-400">*</span>
                    </label>
                    @if($courses->isEmpty())
                        <div class="bg-amber-500/10 border border-amber-400/35 rounded-xl px-4 py-3 text-xs text-amber-200">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            No tienes cursos. <a href="{{ route('teacher.courses.index') }}"
                            class="underline font-semibold">Crea uno primero</a>.
                        </div>
                    @else
                        <select name="course_id" required x-model="selectedCourseId"
                                class="w-full border border-slate-700/60 bg-slate-950/70 text-slate-100 rounded-xl py-2.5 px-4 text-sm
                                       focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/30
                                       transition-all duration-200">
                            <option value="">Selecciona un curso…</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}"
                                    {{ old('course_id', request()->query('course')) == $course->id ? 'selected' : '' }}>
                                    {{ $course->subject_name }} · {{ $course->grade }}
                                    {{ $course->section ? '/ '.$course->section : '' }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Tipo de actividad</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 border rounded-xl px-3 py-2 cursor-pointer transition-all duration-200"
                               :class="createType === 'clase' ? 'border-violet-500/60 bg-violet-500/10' : 'border-slate-700/60 bg-slate-950/60 hover:border-violet-400/40'">
                            <input type="radio" name="type" value="clase" x-model="createType" class="accent-violet-600">
                            <span class="text-sm font-semibold text-violet-200">🏫 Clase</span>
                            <span class="text-[10px] text-slate-400 ml-auto">Teórica</span>
                        </label>
                        <label class="flex items-center gap-2 border rounded-xl px-3 py-2 cursor-pointer transition-all duration-200"
                               :class="createType === 'actividad' ? 'border-fuchsia-500/60 bg-fuchsia-500/10' : 'border-slate-700/60 bg-slate-950/60 hover:border-fuchsia-400/40'">
                            <input type="radio" name="type" value="actividad" x-model="createType" class="accent-fuchsia-600">
                            <span class="text-sm font-semibold text-fuchsia-200">📊 Actividad</span>
                            <span class="text-[10px] text-slate-400 ml-auto">Evaluación</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">
                        Título <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="title" required
                           x-model="title"
                           :placeholder="createType === 'clase' ? 'Ej: Introducción a la célula, Repaso Unidad 2…' : 'Ej: Parcial 1, Tarea 3, Proyecto grupal…'"
                           value="{{ old('title') }}"
                           class="w-full border border-slate-700/60 bg-slate-950/70 text-slate-100 rounded-xl py-2.5 px-4 text-sm
                                  focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/30
                                  transition-all duration-200">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Descripción / Instrucciones</label>
                        <button type="button"
                                @click="generateDescription()"
                                :disabled="loadingDescription || !title.trim()"
                                class="inline-flex items-center gap-1 text-[11px] px-2.5 py-1 rounded-lg
                                       bg-violet-500/15 text-violet-200 hover:bg-violet-500/25 disabled:opacity-40 transition-all duration-200">
                            <i class="fa-solid" :class="loadingDescription ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                            Varita Mágica
                        </button>
                    </div>
                    <textarea name="description" rows="4" x-model="description"
                           placeholder="Objetivo, instrucciones y criterios de ejecución."
                           class="w-full border border-slate-700/60 bg-slate-950/70 text-slate-100 rounded-xl py-2.5 px-4 text-sm
                                  focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/30 transition-all duration-200"></textarea>
                    <div class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                        <input type="checkbox" name="is_homework" id="is_homework" class="h-4 w-4 accent-cyan-500">
                        <label for="is_homework" class="font-medium text-slate-300">Marcar como tarea (HomeWork)</label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Fecha</label>
                    <input type="date" name="due_date"
                           value="{{ old('due_date') }}"
                           class="w-full border border-slate-700/60 bg-slate-950/70 text-slate-100 rounded-xl py-2.5 px-4 text-sm
                                  focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/30 transition-all duration-200">
                </div>

                <input type="hidden" name="max_score" value="{{ old('max_score', 20) }}">
                <input type="hidden" name="weight_percentage" value="{{ old('weight_percentage', 20) }}">

                {{-- Adaptación curricular condicional --}}
                <div class="rounded-2xl border border-slate-700/60 bg-slate-950/60 p-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="has_adaptation" value="1" x-model="adaptationEnabled" class="h-4 w-4 accent-emerald-500">
                        <span class="text-sm font-semibold text-slate-200">Activar adaptación curricular</span>
                    </label>
                    <p class="text-[11px] text-slate-400 mt-1">Al activarla, podrás definir estrategia pedagógica y alumnos específicos.</p>

                    <div x-show="adaptationEnabled" x-transition class="mt-4 space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Tipo de adaptación</label>
                            <select name="nee_type"
                                    class="w-full border border-slate-700/60 bg-slate-950/70 text-slate-100 rounded-xl py-2.5 px-4 text-sm
                                           focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/30 transition-all duration-200">
                                <option value="">Personalizada</option>
                                <option value="TDAH" {{ old('nee_type') === 'TDAH' ? 'selected' : '' }}>TDAH</option>
                                <option value="TEA/Autismo" {{ old('nee_type') === 'TEA/Autismo' ? 'selected' : '' }}>TEA/Autismo</option>
                                <option value="Dislexia" {{ old('nee_type') === 'Dislexia' ? 'selected' : '' }}>Dislexia</option>
                                <option value="Discalculia" {{ old('nee_type') === 'Discalculia' ? 'selected' : '' }}>Discalculia</option>
                                <option value="Otro" {{ old('nee_type') === 'Otro' ? 'selected' : '' }}>Otro</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Detalle pedagógico</label>
                            <textarea name="nee_adaptation" rows="3"
                                      placeholder="Describe en detalle la adaptación pedagógica."
                                      class="w-full border border-slate-700/60 bg-slate-950/70 text-slate-100 rounded-xl py-2.5 px-4 text-sm
                                             focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/30 transition-all duration-200">{{ old('nee_adaptation') }}</textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Aplicar a estudiantes</label>
                            <div class="max-h-36 overflow-y-auto rounded-xl border border-slate-700/60 bg-slate-950/55 p-3 space-y-2">
                                <template x-if="selectedCourseStudents().length === 0">
                                    <p class="text-xs text-slate-500">Este curso no tiene estudiantes vinculados.</p>
                                </template>
                                <template x-for="student in selectedCourseStudents()" :key="student.id">
                                    <label class="flex items-center gap-2 text-sm text-slate-200">
                                        <input type="checkbox" name="nee_students[]" :value="student.id" class="h-4 w-4 accent-emerald-500">
                                        <span x-text="student.name"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                @if($errors->any())
                    <div class="bg-red-500/10 border border-red-400/40 rounded-xl px-4 py-3 text-sm text-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="flex items-center justify-between pt-3 border-t border-slate-700/60">
                    <button type="button" @click="openCreate = false"
                            class="text-sm text-slate-400 hover:text-slate-200 transition-all duration-200">Cancelar</button>
                    <button type="submit"
                            :disabled="{{ $courses->isEmpty() ? 'true' : 'false' }}"
                            class="btn-gradient inline-flex items-center gap-2
                                   disabled:opacity-40 disabled:cursor-not-allowed
                                   font-semibold px-5 py-2.5">
                        <i class="fa-solid fa-plus"></i>
                        <span x-text="createType === 'clase' ? 'Crear Clase' : 'Crear Actividad'">Crear</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Grade task modal --}}
    <div x-show="gradeModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(15,23,42,.55);backdrop-filter:blur(4px);"
         @keydown.escape.window="gradeModalOpen = false">
        <div @click.outside="gradeModalOpen = false"
             class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 text-white" style="background:linear-gradient(135deg,#6d28d9,#c026d3)">
                <h3 class="font-bold">Calificar tarea</h3>
                <p class="text-xs text-purple-200 mt-0.5" x-text="selectedTask?.titulo || 'Tarea seleccionada'"></p>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Calificación</label>
                    <input type="number" min="0" step="0.01"
                           x-model.number="gradeForm.calificacion"
                           class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm
                                  focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5">Feedback</label>
                    <textarea rows="3" x-model="gradeForm.feedback"
                              class="w-full border border-slate-200 rounded-xl py-2.5 px-4 text-sm
                                     focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition"
                              placeholder="Comentario pedagógico opcional"></textarea>
                </div>
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button type="button" @click="gradeModalOpen = false"
                            class="text-sm text-slate-400 hover:text-slate-600">Cancelar</button>
                    <button type="button" @click="saveTaskGrade()"
                            :disabled="gradeSaving || !selectedTask?.id"
                            class="btn-gradient inline-flex items-center gap-2 font-semibold px-5 py-2.5
                                   disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid" :class="gradeSaving ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                        Guardar nota
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

@php
    $activityPayload = collect($activities->items())->map(function ($a) {
        $course = $a->course;
        $tareas = $a->tareas->map(function ($t) {
            return [
                'id' => $t->id,
                'titulo' => $t->titulo,
                'descripcion' => $t->descripcion,
                'fecha_entrega' => optional($t->fecha_entrega)->format('Y-m-d'),
                'puntos' => $t->puntos,
                'calificacion' => $t->calificacion,
                'feedback' => $t->feedback,
            ];
        })->values()->toArray();
        return [
            'id' => $a->id,
            'title' => $a->title,
            'description' => $a->description,
            'type' => $a->type ?? 'actividad',
            'max_score' => $a->max_score,
            'weight_percentage' => $a->weight_percentage,
            'due_date' => optional($a->due_date)->format('Y-m-d'),
            'course_id' => $a->course_id,
            'course_name' => $course ? ($course->subject_name . ($course->grade ? ' · ' . $course->grade : '')) : '—',
            'is_homework' => (bool) $a->is_homework,
            'nee_type' => $a->nee_type,
            'nee_adaptation' => $a->nee_adaptation,
            'tareas_count' => $a->tareas_count ?? count($tareas),
            'tareas' => $tareas,
        ];
    })->values();
@endphp

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function activitiesPage() {
    return {
        openCreate:   {{ $errors->any() ? 'true' : 'false' }},
        filterCourse: null,
        filterType:   'all',
        gradeModalOpen: false,
        gradeSaving: false,
        selectedTask: null,
        activities: @json($activityPayload),
        gradeForm: {
            calificacion: null,
            feedback: '',
        },

        init() {
            window.addEventListener('ai-canvas-refresh', () => this.refreshData());
        },

        activityMatchesType(activity, type) {
            if (type === 'all') return true;
            if (type === 'clase') return activity.type === 'clase';
            if (type === 'tarea') {
                return activity.is_homework || activity.type === 'tarea';
            }
            if (type === 'evaluacion') return activity.type !== 'clase' && !activity.is_homework && activity.type !== 'tarea';
            return true;
        },

        filteredActivities() {
            return (this.activities || []).filter(activity => {
                const courseOk = this.filterCourse === null || activity.course_id === this.filterCourse;
                const typeOk = this.activityMatchesType(activity, this.filterType);
                return courseOk && typeOk;
            });
        },

        countByType(type) {
            return (this.activities || []).filter(activity => {
                const courseOk = this.filterCourse === null || activity.course_id === this.filterCourse;
                const typeOk = this.activityMatchesType(activity, type);
                return courseOk && typeOk;
            }).length;
        },

        async refreshData() {
            try {
                const res = await fetch('{{ route('teacher.activities.index') }}', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                this.activities = Array.isArray(data) ? data : [];
            } catch (e) {
                console.error('refreshData', e);
            }
        },

        openGradeModal(task) {
            this.selectedTask = task;
            this.gradeForm.calificacion = task?.calificacion ?? null;
            this.gradeForm.feedback = task?.feedback ?? '';
            this.gradeModalOpen = true;
        },

        async saveTaskGrade() {
            if (!this.selectedTask?.id) return;

            this.gradeSaving = true;
            try {
                const res = await fetch(`/teacher/tareas/${this.selectedTask.id}/grade`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify({
                        calificacion: this.gradeForm.calificacion,
                        feedback: this.gradeForm.feedback,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || json.error || 'No se pudo guardar la calificación');
                }

                const badge = document.getElementById(`task-grade-${this.selectedTask.id}`);
                if (badge) {
                    badge.textContent = Number(json.tarea.calificacion).toFixed(2);
                }

                this.gradeModalOpen = false;
            } catch (e) {
                alert(e.message || 'Error al guardar calificación');
            } finally {
                this.gradeSaving = false;
            }
        },

        formatDate(date) {
            if (!date) return '—';
            const d = new Date(date);
            if (Number.isNaN(d)) return date;
            return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },
    };
}

function activityForm() {
    return {
        coursesWithStudents: {{ Js::from($coursesJson) }},
        selectedCourseId: '{{ old('course_id', request()->query('course')) }}',
        createType: '{{ old('type', 'actividad') }}',
        title: '{{ old('title', '') }}',
        description: '{{ old('description', '') }}',
        adaptationEnabled: {{ old('has_adaptation') || old('nee_type') || old('nee_adaptation') ? 'true' : 'false' }},
        loadingDescription: false,
        selectedCourseStudents() {
            const courseId = Number(this.selectedCourseId || 0);
            const course = this.coursesWithStudents.find((c) => Number(c.id) === courseId);
            return course?.students ?? [];
        },
        async generateDescription() {
            if (!this.title.trim() || this.loadingDescription) return;
            this.loadingDescription = true;
            try {
                const res = await fetch('{{ route('teacher.activities.ai_description') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify({
                        title: this.title,
                        type: this.createType || 'actividad',
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.error || 'No se pudo generar');
                this.description = json.description || '';
            } catch (e) {
                alert(e.message || 'Error al generar descripción');
            } finally {
                this.loadingDescription = false;
            }
        },
    };
}

// Funciones globales
window.deleteActivity = function(id) {
    if (!confirm('¿Eliminar esta actividad y todas sus notas?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/teacher/activities/${id}`;
    form.innerHTML = `<input name="_token" value="${CSRF}">
                      <input name="_method" value="DELETE">`;
    document.body.appendChild(form);
    form.submit();
};

window.editActivityWithAI = function(id, title) {
    const instruction = prompt(`¿Qué deseas mejorar en "${title}"?\nEj: "más formal", "más desafiante", "enfoque práctico"`);
    if (!instruction || !instruction.trim()) return;

    fetch(`/teacher/activities/${id}/ai-edit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF,
        },
        body: JSON.stringify({ instruction }),
    })
    .then(res => res.json())
    .then(json => {
        if (!json.success) throw new Error(json.error || 'No se pudo editar');
        alert('Descripción actualizada con IA. Se refrescará la vista.');
        window.location.reload();
    })
    .catch(e => {
        alert(e.message || 'Error al editar con IA');
    });
};
</script>
@include('components.ai-assistant-bubble')
</body>
</html>