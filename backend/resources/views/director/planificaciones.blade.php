<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Planificaciones · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background:var(--bg-primary); color:var(--text-primary); }
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
        :root:not(.dark) .bg-white\/\[\.045\],
        :root:not(.dark) .bg-white\/10 { background: var(--bg-card); }
        :root:not(.dark) .border-white\/10 { border-color: var(--nova-glass-border); }
        .slide-over {
            position: fixed; top: 0; right: 0; bottom: 0; width: 100%; max-width: 520px;
            z-index: 60; transform: translateX(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }
        .slide-over.open { transform: translateX(0); }
        .slide-over-backdrop {
            position: fixed; inset: 0; z-index: 59;
            background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .slide-over-backdrop.open { opacity: 1; pointer-events: auto; }
    </style>
</head>
<body class="min-h-screen" x-data="planificacionesApp()">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-fuchsia-600/20 blur-[110px]"></div>
    </div>

    {{-- Slide-over backdrop --}}
    <div class="slide-over-backdrop" :class="slideOpen ? 'open' : ''" @click="closeSlide()"></div>

    {{-- Slide-over panel --}}
    <div class="slide-over glass-card rounded-l-[2rem] border-r-0 p-6" :class="slideOpen ? 'open' : ''">
        <template x-if="slideLoading">
            <div class="flex flex-col items-center justify-center h-full gap-3 text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-cyan-300"></i>
                <p class="text-sm">Cargando planificación...</p>
            </div>
        </template>
        <template x-if="!slideLoading && slideError">
            <div class="flex flex-col items-center justify-center h-full gap-4 px-4 text-center">
                <i class="fa-solid fa-triangle-exclamation text-3xl text-amber-300"></i>
                <p class="text-sm text-slate-300" x-text="slideError"></p>
                <button @click="closeSlide()" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/10 transition">
                    Cerrar
                </button>
            </div>
        </template>
        <template x-if="!slideLoading && !slideError && slidePlan">
            <div>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.25em] text-cyan-200">Detalle de planificación</p>
                        <h3 class="mt-1 text-lg font-black text-white" x-text="slidePlan.tema"></h3>
                    </div>
                    <button @click="closeSlide()" class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-slate-300 hover:bg-white/10 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="mb-5 flex flex-wrap gap-3 text-sm text-slate-400">
                    <span><i class="fa-solid fa-user mr-1.5 text-cyan-300"></i><span x-text="slidePlan.teacher_name"></span></span>
                    <span><i class="fa-regular fa-calendar mr-1.5"></i><span x-text="slidePlan.created_at"></span></span>
                    <span x-show="slidePlan.course_name"><i class="fa-regular fa-file-lines mr-1.5"></i><span x-text="slidePlan.course_name"></span></span>
                    <span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider"
                              :class="slidePlan.status === 'aprobado' ? 'bg-emerald-400/20 text-emerald-200' : (slidePlan.status === 'rechazado' ? 'bg-rose-400/20 text-rose-200' : (slidePlan.status === 'pendiente_revision' ? 'bg-cyan-400/20 text-cyan-200' : 'bg-amber-400/20 text-amber-200'))"
                              x-text="slidePlan.status === 'pendiente_revision' ? 'pendiente de revisión' : (slidePlan.status || 'pendiente')"></span>
                    </span>
                </div>

                {{-- Rejection feedback --}}
                <template x-if="slidePlan.rechazo_motivo">
                    <div class="mb-5 rounded-2xl border border-rose-400/30 bg-rose-400/10 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-rose-200">Feedback de rechazo</p>
                        <p class="mt-1 text-sm text-slate-300" x-text="slidePlan.rechazo_motivo"></p>
                    </div>
                </template>

                {{-- Sessions --}}
                <p class="mb-3 text-xs font-bold uppercase tracking-[.2em] text-slate-400">
                    Clases vinculadas <span class="text-cyan-200" x-text="'(' + (slidePlan.sessions?.length || 0) + ')'"></span>
                </p>

                <div class="space-y-3">
                    <template x-for="(session, idx) in slidePlan.sessions" :key="session.uid || idx">
                        <article class="overflow-hidden rounded-2xl border border-white/10 bg-white/[.035]">
                            <button class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-white/[.05]"
                                    @click="toggleSessionAccordion(session.uid)">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-white truncate" x-text="session.title || ('Clase ' + session.index)"></p>
                                    <p class="mt-1 text-[11px] text-slate-400">
                                        <span class="inline-flex items-center gap-1 rounded-full border border-cyan-400/30 bg-cyan-400/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-cyan-200">
                                            <i class="fa-solid fa-sparkles"></i>
                                            Clase <span x-text="session.index"></span>
                                        </span>
                                        <span class="ml-2" x-text="session.date || 'Sin fecha'"></span>
                                    </p>
                                </div>
                                <i class="fa-solid fa-chevron-down text-slate-400 transition"
                                   :class="{ 'rotate-180': isSessionOpen(session.uid) }"></i>
                            </button>

                            <div x-show="isSessionOpen(session.uid)"
                                 x-transition
                                 x-cloak
                                 class="space-y-3 border-t border-white/10 px-4 py-4">
                                <div class="rounded-xl border border-cyan-400/20 bg-cyan-400/5 p-3">
                                    <p class="text-xs font-bold uppercase tracking-wider text-cyan-200">🎯 Inicio</p>
                                    <p class="mt-1 text-sm text-slate-300" x-text="session.inicio || 'Sin contenido de inicio.'"></p>
                                </div>
                                <div class="rounded-xl border border-violet-400/20 bg-violet-400/5 p-3">
                                    <p class="text-xs font-bold uppercase tracking-wider text-violet-200">⚙️ Desarrollo</p>
                                    <p class="mt-1 text-sm text-slate-300" x-text="session.desarrollo || 'Sin contenido de desarrollo.'"></p>
                                </div>
                                <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-3">
                                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-200">🏁 Cierre</p>
                                    <p class="mt-1 text-sm text-slate-300" x-text="session.cierre || 'Sin contenido de cierre.'"></p>
                                </div>

                                <div class="rounded-xl border border-white/10 bg-slate-900/40 p-3">
                                    <textarea x-model="session.feedbackDraft"
                                              class="w-full rounded-xl border border-white/10 bg-white/5 p-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                                              rows="2"
                                              placeholder="Notas del director para esta clase..."></textarea>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button @click="saveFeedbackBySession(session)"
                                                :disabled="session.savingFeedback || !session.id"
                                                class="rounded-xl border border-amber-300/30 bg-amber-400/10 px-3 py-2 text-xs font-bold text-amber-200 transition hover:bg-amber-400/20 disabled:cursor-not-allowed disabled:opacity-50">
                                            <span x-text="session.savingFeedback ? 'Guardando...' : '💬 Agregar Nota'"></span>
                                        </button>
                                        <button @click="suggestChangeWithAI(session)"
                                                class="rounded-xl border border-cyan-300/30 bg-cyan-400/10 px-3 py-2 text-xs font-bold text-cyan-200 transition hover:bg-cyan-400/20">
                                            ⚡ Sugerir cambio con IA
                                        </button>
                                    </div>

                                    <div class="mt-3 space-y-1 border-t border-white/10 pt-2 text-[11px] text-slate-500">
                                        <p class="flex items-center gap-1.5">
                                            <i class="fa-regular fa-clock"></i>
                                            <span x-text="session.has_director_note ? 'Observación guardada por Dirección' : 'Sin observaciones de Dirección'"></span>
                                        </p>
                                        <p class="flex items-center gap-1.5">
                                            <i class="fa-regular fa-clock"></i>
                                            <span x-text="session.version_label || (session.is_director_edited ? '✓ Versión 2 (Editada por Dirección)' : 'Versión original del Docente')"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                {{-- Actions --}}
                <div class="mt-6 flex gap-3 border-t border-white/10 pt-5">
                    <button x-show="slidePlan.status !== 'aprobado'"
                            @click="approvePlan(slidePlan.id)"
                            class="flex-1 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-2.5 text-sm font-bold text-emerald-200 transition hover:bg-emerald-400/20">
                        <i class="fa-solid fa-check mr-1.5"></i>Aprobar
                    </button>
                    <button x-show="slidePlan.status !== 'rechazado' && slidePlan.status !== 'aprobado'"
                            @click="openReject(slidePlan.id)"
                            class="flex-1 rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-2.5 text-sm font-bold text-rose-200 transition hover:bg-rose-400/20">
                        <i class="fa-solid fa-xmark mr-1.5"></i>Rechazar
                    </button>
                    <button @click="closeSlide()" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-slate-300 hover:bg-white/10 transition">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- Reject modal --}}
    <template x-if="rejectingId">
        <div class="fixed inset-0 z-70 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="rejectingId = null">
            <div class="glass-card mx-4 w-full max-w-md rounded-[2rem] p-6">
                <h3 class="text-lg font-bold text-white">Rechazar planificación</h3>
                <p class="mt-2 text-sm text-slate-400">Indica el motivo o feedback para el docente:</p>
                <textarea x-model="rejectFeedback"
                          class="mt-4 w-full rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                          rows="3" placeholder="Ej: Falta alineación con el currículo, ajustar objetivos..."></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="rejectingId = null" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">Cancelar</button>
                    <button @click="submitReject()"
                            class="rounded-xl bg-rose-500 px-4 py-2 text-sm font-bold text-white hover:bg-rose-600">
                        Enviar rechazo con feedback
                    </button>
                </div>
            </div>
        </div>
    </template>

    <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 shadow-2xl shadow-black/20 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('director.dashboard') }}" class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg shadow-cyan-500/20">
                    <i class="fa-solid fa-arrow-left text-xl text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Auditoría Académica</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white md:text-3xl">Planificaciones Docentes</h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @include('components.user-control-panel')
            </div>
        </header>

        <section class="glass-card rounded-[2rem] p-6">
            @php
                $statusQuery = request('status', '');
                $commonFilters = [
                    'grade' => $selectedGrade ?? request('grade', ''),
                    'subject' => $selectedSubject ?? request('subject', ''),
                ];
                $activeGrade = $selectedGrade ?? request('grade', '');
                $activeSubject = $selectedSubject ?? request('subject', '');
                $clearGradeQuery = \Illuminate\Support\Arr::except(request()->query(), ['grade']);
                $clearSubjectQuery = \Illuminate\Support\Arr::except(request()->query(), ['subject']);
                $statusCounts = $statusCounts ?? collect();
            @endphp
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex gap-2">
                    <a href="{{ route('director.planificaciones', array_merge(['status' => ''], $commonFilters)) }}"
                       class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/10 {{ !request('status') ? 'bg-white/10 text-cyan-200' : 'text-slate-300' }}">
                        Todas
                    </a>
                    <a href="{{ route('director.planificaciones', array_merge(['status' => 'pendiente'], $commonFilters)) }}"
                       class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/10 {{ request('status') === 'pendiente' ? 'bg-amber-400/20 text-amber-200' : 'text-slate-300' }}">
                        Pendientes
                        @if(($statusCounts['pendiente'] ?? 0) > 0)
                            <span class="ml-1 rounded-full bg-amber-400 px-1.5 text-[10px] font-black text-amber-950">{{ $statusCounts['pendiente'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('director.planificaciones', array_merge(['status' => 'pendiente_revision'], $commonFilters)) }}"
                       class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/10 {{ request('status') === 'pendiente_revision' ? 'bg-cyan-400/20 text-cyan-200' : 'text-slate-300' }}">
                        Correcciones
                        @if(($statusCounts['pendiente_revision'] ?? 0) > 0)
                            <span class="ml-1 rounded-full bg-cyan-300 px-1.5 text-[10px] font-black text-cyan-950">{{ $statusCounts['pendiente_revision'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('director.planificaciones', array_merge(['status' => 'aprobado'], $commonFilters)) }}"
                       class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/10 {{ request('status') === 'aprobado' ? 'bg-emerald-400/20 text-emerald-200' : 'text-slate-300' }}">
                        Aprobadas
                    </a>
                    <a href="{{ route('director.planificaciones', array_merge(['status' => 'rechazado'], $commonFilters)) }}"
                       class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/10 {{ request('status') === 'rechazado' ? 'bg-rose-400/20 text-rose-200' : 'text-slate-300' }}">
                        Rechazadas
                    </a>
                </div>

                <form method="GET" class="flex flex-wrap items-center space-x-2 space-y-2 sm:space-y-0">
                    <input type="hidden" name="status" value="{{ $statusQuery }}">

                    <select name="grade"
                            onchange="this.form.submit()"
                            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        <option value="">Todos los Grados</option>
                        @foreach(($gradeOptions ?? collect()) as $grade)
                            <option value="{{ $grade }}" @selected(($selectedGrade ?? request('grade')) === $grade)>
                                {{ $grade }}
                            </option>
                        @endforeach
                    </select>

                    <select name="subject"
                            onchange="this.form.submit()"
                            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        <option value="">Todas las Asignaturas</option>
                        @foreach(($subjectOptions ?? collect()) as $subject)
                            <option value="{{ $subject }}" @selected(($selectedSubject ?? request('subject')) === $subject)>
                                {{ $subject }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit"
                            class="rounded-xl border border-cyan-300/30 bg-cyan-400/10 px-3 py-2 text-xs font-bold uppercase tracking-wide text-cyan-200 transition hover:bg-cyan-400/20">
                        Filtrar
                    </button>
                </form>
            </div>

            @if($activeGrade || $activeSubject)
                <div class="mb-6 flex flex-wrap items-center gap-2">
                    @if($activeGrade)
                        <span class="inline-flex items-center space-x-1 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-300">
                            <span>Grado: {{ $activeGrade }}</span>
                            <a href="{{ route('director.planificaciones', $clearGradeQuery) }}"
                               class="text-slate-400 transition hover:text-slate-200"
                               aria-label="Quitar filtro de grado">
                                <i class="fa-solid fa-xmark text-[10px]"></i>
                            </a>
                        </span>
                    @endif

                    @if($activeSubject)
                        <span class="inline-flex items-center space-x-1 rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-300">
                            <span>Asignatura: {{ $activeSubject }}</span>
                            <a href="{{ route('director.planificaciones', $clearSubjectQuery) }}"
                               class="text-slate-400 transition hover:text-slate-200"
                               aria-label="Quitar filtro de asignatura">
                                <i class="fa-solid fa-xmark text-[10px]"></i>
                            </a>
                        </span>
                    @endif

                    <a href="{{ route('director.planificaciones', ['status' => $statusQuery]) }}"
                       class="ml-1 inline-flex items-center rounded-lg border border-white/10 px-3 py-1 text-xs font-semibold text-slate-300 transition hover:bg-white/10">
                        Limpiar todos los filtros
                    </a>
                </div>
            @endif

            <div class="space-y-3">
                @forelse($planificaciones as $plan)
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-5 transition hover:bg-white/[.07] cursor-pointer"
                         @click="openSlide({{ $plan->id }})">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-bold text-white">{{ $plan->tema }}</h3>
                                    <span class="rounded-full px-3 py-0.5 text-xs font-bold uppercase tracking-wider
                                        {{ $plan->status === 'aprobado' ? 'bg-emerald-400/20 text-emerald-200 border border-emerald-300/30' : '' }}
                                        {{ $plan->status === 'rechazado' ? 'bg-rose-400/20 text-rose-200 border border-rose-300/30' : '' }}
                                        {{ $plan->status === 'pendiente_revision' ? 'bg-cyan-400/20 text-cyan-200 border border-cyan-300/30' : '' }}
                                        {{ $plan->status === 'pendiente' || !$plan->status ? 'bg-amber-400/20 text-amber-200 border border-amber-300/30' : '' }}">
                                        {{ $plan->status === 'pendiente_revision' ? 'pendiente de revisión' : ($plan->status ?? 'pendiente') }}
                                    </span>
                                    @php
                                        $isStuck = $plan->status === 'pendiente' && $plan->created_at->lt(now()->subHours(48));
                                    @endphp
                                    @if($isStuck)
                                        <span class="rounded-full bg-rose-400/20 text-rose-200 border border-rose-300/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                            <i class="fa-solid fa-clock mr-1"></i>+48h
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm text-slate-400">
                                    <i class="fa-solid fa-user mr-1.5 text-cyan-300"></i>{{ $plan->user?->name ?? 'Docente eliminado' }}
                                    <span class="mx-2">&middot;</span>
                                    <i class="fa-regular fa-calendar mr-1.5"></i>{{ $plan->created_at->format('d/m/Y H:i') }}
                                </p>
                                @php
                                    $sessionCount = $plan->activities_count ?? 0;
                                    $courseName = $plan->payload['course_name'] ?? '';
                                    $feedback = $plan->payload['rechazo_feedback'] ?? null;
                                @endphp
                                @if($sessionCount > 0)
                                    <p class="mt-1 text-xs text-slate-500">
                                        <i class="fa-regular fa-file-lines mr-1"></i>{{ $sessionCount }} sesión(es)
                                        @if($courseName) &middot; {{ $courseName }} @endif
                                    </p>
                                @endif
                                @if($feedback)
                                    <p class="mt-2 text-xs text-rose-200 italic">
                                        <i class="fa-solid fa-comment mr-1"></i>{{ \Illuminate\Support\Str::limit($feedback, 80) }}
                                    </p>
                                @endif
                                @if($plan->status === 'pendiente_revision')
                                    <p class="mt-2 inline-flex items-center rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-bold text-cyan-200">
                                        <i class="fa-solid fa-rotate-right mr-1.5"></i>Corregida por el docente, requiere nueva decisión
                                    </p>
                                @endif
                            </div>
                            <div class="flex gap-2" @click.stop>
                                @if($plan->status !== 'aprobado')
                                    <button @click="approvePlan({{ $plan->id }})"
                                            class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-2 text-sm font-bold text-emerald-200 transition hover:bg-emerald-400/20">
                                        <i class="fa-solid fa-check mr-1.5"></i>Aprobar
                                    </button>
                                @endif
                                @if($plan->status !== 'rechazado' && $plan->status !== 'aprobado')
                                    <button @click="openReject({{ $plan->id }})"
                                            class="rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-2 text-sm font-bold text-rose-200 transition hover:bg-rose-400/20">
                                        <i class="fa-solid fa-xmark mr-1.5"></i>Rechazar
                                    </button>
                                @endif
                                <button @click="openSlide({{ $plan->id }})"
                                        class="rounded-xl border border-white/10 px-3 py-2 text-sm text-slate-300 hover:bg-white/10 transition">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-white/10 bg-white/[.045] p-8 text-center">
                        <i class="fa-regular fa-calendar-circle-plus mb-3 text-4xl text-slate-500"></i>
                        <p class="text-slate-400">No hay planificaciones {{ request('status') ? 'con estado «' . request('status') . '»' : '' }}.</p>
                    </div>
                @endforelse
            </div>

            @if($planificaciones->hasPages())
                <div class="mt-6">
                    {{ $planificaciones->links() }}
                </div>
            @endif
        </section>
    </main>

    @include('components.ai-assistant-bubble')

    <script>
        function planificacionesApp() {
            return {
                slideOpen: false,
                slidePlan: null,
                slideLoading: false,
                slideError: null,
                rejectingId: null,
                rejectFeedback: '',
                expandedSessions: [],

                openSlide(id) {
                    if (!id) {
                        return Promise.resolve(null);
                    }
                    this.slideOpen = true;
                    this.slidePlan = null;
                    this.slideLoading = true;
                    this.slideError = null;

                    const fetchJson = (url) => fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    }).then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            throw new Error(data.error || `Error ${res.status} al cargar ${url}`);
                        }
                        return data;
                    });

                    const request = Promise.allSettled([
                        fetchJson(`/director/planificaciones/${id}/sessions`),
                        fetchJson(`/director/planificaciones/${id}/activities`),
                    ])
                        .then(([sessionResult, activitiesResult]) => {
                            if (sessionResult.status !== 'fulfilled' || !sessionResult.value?.success) {
                                const reason = sessionResult.status === 'rejected'
                                    ? sessionResult.reason?.message
                                    : sessionResult.value?.error;
                                this.slideError = reason || 'No se pudo cargar la planificación.';
                                this.slidePlan = null;
                                return null;
                            }

                            const sessionData = sessionResult.value;
                            const activitiesData = activitiesResult.status === 'fulfilled'
                                ? activitiesResult.value
                                : { activities: [] };

                            const sessions = this.normalizeActivitySessions(
                                activitiesData?.activities || [],
                                sessionData.sessions || []
                            );

                            this.slidePlan = {
                                ...sessionData,
                                sessions,
                            };
                            this.expandedSessions = sessions.length ? [sessions[0].uid] : [];

                            return this.slidePlan;
                        })
                        .catch((e) => {
                            console.warn('Slide fetch failed', e);
                            this.slideError = e?.message || 'Error inesperado al cargar la planificación.';
                            this.slidePlan = null;
                            return null;
                        })
                        .finally(() => {
                            this.slideLoading = false;
                        });

                    return request;
                },

                normalizeActivitySessions(activities, fallbackSessions = []) {
                    if (!Array.isArray(activities) || activities.length === 0) {
                        return (fallbackSessions || []).map((session, idx) => ({
                            uid: `fallback-${idx}-${Date.now()}`,
                            id: session.activity_id || null,
                            index: idx + 1,
                            title: session.title || `Clase ${idx + 1}`,
                            date: session.date || '',
                            inicio: session.inicio || '',
                            desarrollo: session.desarrollo || '',
                            cierre: session.cierre || '',
                            director_notes: session.director_notes || '',
                            has_director_note: !!session.has_director_note || !!session.director_notes,
                            is_director_edited: !!session.is_director_edited || !!session.director_notes,
                            version_label: session.version_label || (session.director_notes ? '✓ Versión 2 (Editada por Dirección)' : 'Versión original del Docente'),
                            feedbackDraft: session.director_notes || '',
                            savingFeedback: false,
                        }));
                    }

                    return activities.map((activity, idx) => ({
                        uid: `activity-${activity.id || idx}`,
                        id: activity.id || null,
                        index: activity.index || (idx + 1),
                        title: activity.title || `Clase ${idx + 1}`,
                        date: activity.date || activity.due_date || '',
                        inicio: activity.inicio || '',
                        desarrollo: activity.desarrollo || '',
                        cierre: activity.cierre || '',
                        director_notes: activity.director_notes || '',
                        has_director_note: !!activity.has_director_note || !!activity.director_notes,
                        is_director_edited: !!activity.is_director_edited || !!activity.director_notes,
                        version_label: activity.version_label || (activity.director_notes ? '✓ Versión 2 (Editada por Dirección)' : 'Versión original del Docente'),
                        feedbackDraft: activity.director_notes || '',
                        savingFeedback: false,
                    }));
                },

                closeSlide() {
                    this.slideOpen = false;
                    this.slidePlan = null;
                    this.slideError = null;
                    this.expandedSessions = [];
                },

                isSessionOpen(uid) {
                    return this.expandedSessions.includes(uid);
                },

                toggleSessionAccordion(uid) {
                    if (!uid) return;
                    if (this.isSessionOpen(uid)) {
                        this.expandedSessions = this.expandedSessions.filter((s) => s !== uid);
                        return;
                    }
                    this.expandedSessions = [...this.expandedSessions, uid];
                },

                async saveFeedbackBySession(session) {
                    if (!session || !session.id) return;
                    session.savingFeedback = true;
                    try {
                        const res = await fetch(`/director/activities/${session.id}/feedback`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ director_notes: session.feedbackDraft || '' })
                        });
                        const data = await res.json();
                        if (data.success) {
                            session.director_notes = data.director_notes || session.feedbackDraft;
                            session.has_director_note = !!session.director_notes;
                            session.is_director_edited = true;
                            session.version_label = '✓ Versión 2 (Editada por Dirección)';
                        }
                    } catch (e) {
                        console.warn('Save feedback failed', e);
                    } finally {
                        session.savingFeedback = false;
                    }
                },

                suggestChangeWithAI(session) {
                    if (!session) return;

                    const title = session.title || `Clase ${session.index || ''}`.trim();
                    const context = {
                        module: 'director-planificaciones',
                        source: 'director-slide-over',
                        type: 'clase',
                        title,
                        inicio: session.inicio || '',
                        desarrollo: session.desarrollo || '',
                        cierre: session.cierre || '',
                        activity_id: session.id || null,
                        planificacion_id: this.slidePlan?.id || null,
                    };

                    window.dispatchEvent(new CustomEvent('nova-assistant-prefill', {
                        detail: {
                            context,
                            prefill: `Quiero que optimices la parte que dice: ${title}. Sugiero que cambies...`,
                            seedMessage: `Analicemos **${title}**. Ya cargué su Inicio, Desarrollo y Cierre para sugerir mejoras pedagógicas.`,
                        },
                    }));
                },

                async approvePlan(id) {
                    try {
                        const res = await fetch(`/director/planificaciones/${id}/approve`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.closeSlide();
                            window.location.reload();
                        }
                    } catch (e) {
                        console.warn('Approve failed', e);
                    }
                },

                openReject(id) {
                    this.rejectingId = id;
                    this.rejectFeedback = '';
                },

                async submitReject() {
                    if (!this.rejectingId) return;
                    try {
                        const res = await fetch(`/director/planificaciones/${this.rejectingId}/reject`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ feedback: this.rejectFeedback })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.rejectingId = null;
                            this.closeSlide();
                            window.location.reload();
                        }
                    } catch (e) {
                        console.warn('Reject failed', e);
                    }
                }
            }
        }
    </script>
</body>
</html>
