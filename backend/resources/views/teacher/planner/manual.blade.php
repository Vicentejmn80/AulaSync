<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificador Visual · Nova Academy</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <script src="https://cdn.jsdelivr.net/npm/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.nova-theme')
    <style>
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: transparent;
            bottom: 0;
            color: transparent;
            cursor: pointer;
            height: auto;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            width: auto;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .gradient-border {
            position: relative;
            border-radius: 2rem;
            background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab, #5073b8, #1098ad, #07b39b, #6fba82);
            background-size: 300% 300%;
            animation: animated-gradient 12s ease alternate infinite;
        }
        
        @keyframes animated-gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 2rem;
            padding: 3px;
            background: inherit;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -15px rgba(124, 58, 237, 0.4);
        }
        /* Light mode: convert dark containers to premium white cards */
        :root:not(.dark) .bg-slate-900\/55,
        :root:not(.dark) .bg-slate-900\/70,
        :root:not(.dark) .bg-slate-900\/40,
        :root:not(.dark) .bg-slate-900\/50,
        :root:not(.dark) .bg-slate-900\/25,
        :root:not(.dark) .bg-slate-900\/35 { background: var(--bg-secondary); border-color: #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.03); }
        :root:not(.dark) .bg-slate-950\/55,
        :root:not(.dark) .bg-slate-950\/60 { background: var(--bg-tertiary); border-color: #E2E8F0; }
        :root:not(.dark) .border-slate-700\/40,
        :root:not(.dark) .border-slate-800\/50 { border-color: #E2E8F0; }
        :root:not(.dark) .text-slate-200 { color: var(--text-primary); }
        :root:not(.dark) .text-slate-300 { color: var(--text-secondary); }
        :root:not(.dark) .text-slate-400 { color: var(--text-tertiary); }
        :root:not(.dark) .text-slate-500 { color: var(--text-tertiary); }
        :root:not(.dark) .hover\:bg-slate-800\/60:hover,
        :root:not(.dark) .hover\:bg-slate-900\/35:hover { background: var(--bg-tertiary); }
        :root:not(.dark) .hover\:border-slate-600\/60:hover { border-color: #CBD5E1; }
        :root:not(.dark) .bg-violet-500\/20 { background: rgba(124,58,237,0.08); }
        :root:not(.dark) .text-violet-200 { color: var(--nova-violet); }
        :root:not(.dark) .text-cyan-300 { color: var(--nova-cyan); }
        :root:not(.dark) input,
        :root:not(.dark) textarea { background: var(--bg-tertiary); color: var(--text-primary); border-color: #E2E8F0; }
        :root:not(.dark) input:focus,
        :root:not(.dark) textarea:focus { background: var(--bg-secondary); border-color: var(--nova-violet); box-shadow: 0 0 0 3px rgba(124,58,237,0.1); }
        :root:not(.dark) input::placeholder,
        :root:not(.dark) textarea::placeholder { color: var(--text-tertiary); }
    </style>
</head>
<body class="min-h-screen font-sans relative overflow-x-hidden" style="background:var(--bg-primary);color:var(--text-primary);">

    <!-- Fondo animado estilo screenshot -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-72 h-72 bg-violet-600/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-fuchsia-600/20 rounded-full blur-3xl animate-pulse delay-1000"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-blue-600/10 rounded-full blur-3xl"></div>
        
        <!-- Patrón de grid sutil -->
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.1) 1px, transparent 0); background-size: 40px 40px;"></div>
    </div>

<div class="max-w-7xl mx-auto px-4 py-8 relative z-10" x-data="manualPlanner()">
    <header class="mb-8 rounded-3xl border border-slate-700/40 bg-slate-900/55 backdrop-blur-md p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5">
            <div>
                <p class="text-[11px] uppercase tracking-[0.2em] text-slate-400 font-semibold mb-2">Planificador Visual</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-slate-900 dark:bg-gradient-to-r dark:from-white dark:via-violet-200 dark:to-cyan-200 dark:bg-clip-text dark:text-transparent">
                    Planificación Manual
                </h1>
                <p class="text-slate-300 mt-2">Diseña sesiones con estructura clara y vista previa en tiempo real.</p>
                <div class="mt-4 max-w-md">
                    <label class="block text-[11px] uppercase tracking-[0.14em] text-slate-500 font-semibold mb-1.5">Curso objetivo</label>
                    <select x-model="selectedCourseId"
                        class="w-full rounded-xl border border-slate-700/40 bg-slate-950/55 px-4 py-2.5 text-slate-200 transition-all duration-200 hover:border-slate-600/60 focus:outline-none focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/30 focus:bg-slate-900/70">
                        <template x-for="course in courses" :key="course.id">
                            <option :value="course.id" x-text="course.name"></option>
                        </template>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('teacher.hub') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-700/40 bg-slate-900/40 text-slate-200 hover:bg-slate-800/60 transition-all duration-200">
                    <i class="ph-bold ph-arrow-left"></i> Volver al Hub
                </a>
                <button @click="addSession()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white font-semibold shadow-lg shadow-violet-600/25 hover:opacity-95 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200">
                    <i class="ph-bold ph-plus-circle"></i> Nueva Sesión
                </button>
            </div>
        </div>
    </header>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <section class="xl:col-span-8 space-y-3">
            <template x-for="(session, index) in sessions" :key="session.id || index">
                <article class="rounded-2xl bg-slate-900/25 backdrop-blur-sm p-4 md:p-5 transition-all duration-200 hover:bg-slate-900/35"
                    :class="activeSessionIndex === index ? 'bg-slate-900/40' : ''"
                    @click="setActiveSession(index)">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/20 text-violet-200 text-sm font-semibold" x-text="index + 1"></span>
                            <div>
                                <p class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold">Sesión</p>
                                <p class="text-sm text-slate-200" x-text="session.day || 'Sin día definido'"></p>
                            </div>
                        </div>
                        <button type="button" @click.stop.prevent="removeSession(index)" x-show="sessions.length > 1"
                            class="h-8 w-8 rounded-lg border border-slate-300/80 bg-white/80 text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300 dark:hover:text-red-200 dark:hover:bg-red-500/20 hover:scale-[1.04] active:scale-[0.96] transition-all duration-200">
                            <i class="ph-bold ph-trash pointer-events-none"></i>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] uppercase tracking-[0.14em] text-slate-500 font-semibold mb-1.5">Fecha</label>
                            <input type="date" x-model="session.date" @change="updateDayName(index)"
                                class="w-full rounded-xl border border-slate-700/40 bg-slate-950/55 px-4 py-3 text-slate-200 transition-all duration-200 hover:border-slate-600/60 focus:outline-none focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/30 focus:bg-slate-900/70">
                        </div>

                        <div>
                            <label class="block text-[11px] uppercase tracking-[0.14em] text-slate-500 font-semibold mb-1.5">Inicio de sesión</label>
                            <div class="relative">
                                <textarea x-model="session.inicio" rows="3" @focus="setActiveSession(index)"
                                    class="w-full rounded-xl border border-slate-700/40 bg-slate-950/55 px-4 py-3 pr-16 text-slate-200 placeholder-slate-500 transition-all duration-200 hover:border-slate-600/60 focus:outline-none focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/30 focus:bg-slate-900/70 resize-none"
                                    placeholder="Objetivo de apertura, activación y encuadre de la clase..."></textarea>
                                <span class="absolute bottom-2 right-3 text-[11px] text-slate-500">
                                    <span x-text="session.inicio?.length || 0"></span>/500
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] uppercase tracking-[0.14em] text-slate-500 font-semibold mb-1.5">Desarrollo pedagógico</label>
                            <div class="relative">
                                <textarea x-model="session.desarrollo" rows="6" @focus="setActiveSession(index)"
                                    class="w-full rounded-xl border border-slate-700/40 bg-slate-950/55 px-4 py-3 pr-16 text-slate-200 placeholder-slate-500 transition-all duration-200 hover:border-slate-600/60 focus:outline-none focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/30 focus:bg-slate-900/70 resize-none"
                                    placeholder="Secuencia de actividades, metodología activa y evaluación formativa..."></textarea>
                                <span class="absolute bottom-2 right-3 text-[11px] text-slate-500">
                                    <span x-text="session.desarrollo?.length || 0"></span>/1000
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 border-b border-slate-800/50"></div>
                </article>
            </template>
        </section>

        <aside class="xl:col-span-4">
            <div class="xl:sticky xl:top-6 rounded-2xl border border-slate-700/40 bg-slate-900/70 backdrop-blur-md p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i class="ph-fill ph-eye text-cyan-300"></i>
                    <p class="text-[11px] uppercase tracking-[0.2em] text-slate-400 font-semibold">Vista Previa Rápida</p>
                </div>

                <div class="rounded-xl border border-slate-700/40 bg-slate-950/60 p-4 mb-4">
                    <p class="text-xs text-slate-500 mb-1">Sesión activa</p>
                    <p class="text-sm font-medium text-slate-200" x-text="`Sesión ${activeSessionIndex + 1}`"></p>
                    <p class="text-xs text-slate-400 mt-1" x-text="currentSession()?.date ? formatDisplayDate(currentSession().date) : 'Sin fecha'"></p>
                </div>

                <template x-if="currentSession()">
                    <div class="space-y-4 text-sm"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        :key="activeSessionIndex">
                        <div>
                            <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase mb-1">Inicio</p>
                            <p class="text-slate-300 leading-relaxed" x-text="currentSession()?.inicio || 'Aún no has escrito el inicio de esta sesión.'"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold tracking-widest text-slate-500 uppercase mb-1">Desarrollo</p>
                            <p class="text-slate-300 leading-relaxed" x-text="currentSession()?.desarrollo || 'Aún no has escrito el desarrollo pedagógico.'"></p>
                        </div>
                    </div>
                </template>

                <div class="mt-5 pt-4 border-t border-slate-700/40 grid grid-cols-2 gap-3 text-center">
                    <div class="rounded-lg border border-slate-700/40 bg-slate-900/50 py-2">
                        <p class="text-[11px] text-slate-500">Sesiones</p>
                        <p class="text-sm font-semibold text-slate-200" x-text="sessions.length"></p>
                    </div>
                    <div class="rounded-lg border border-slate-700/40 bg-slate-900/50 py-2">
                        <p class="text-[11px] text-slate-500">Campos completos</p>
                        <p class="text-sm font-semibold text-slate-200" x-text="completionCount()"></p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div class="fixed bottom-4 left-4 right-4 md:bottom-8 md:right-8 md:left-auto z-50">
        <button @click="save()" :disabled="isLoading"
            class="w-full md:w-auto inline-flex justify-center items-center gap-2 px-6 py-3 rounded-2xl bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white font-semibold shadow-xl shadow-violet-600/30 hover:opacity-95 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 disabled:opacity-60">
            <i :class="isLoading ? 'ph-bold ph-circle-notch animate-spin' : 'ph-bold ph-floppy-disk'"></i>
            <span x-text="isLoading ? 'Guardando...' : 'Guardar Planificación'"></span>
        </button>
    </div>

    <div x-show="sessions.length === 0" x-cloak class="text-center py-20">
        <div class="inline-flex flex-col items-center gap-4 rounded-2xl border border-slate-700/40 bg-slate-900/50 p-8">
            <i class="ph-fill ph-notebook text-5xl text-slate-500"></i>
            <h3 class="text-xl font-semibold text-slate-200">No hay sesiones planificadas</h3>
            <button @click="addSession()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white font-semibold">
                Crear primera sesión
            </button>
        </div>
    </div>
</div>

<script>
function manualPlanner() {
    return {
        sessions: @json($planning->sessions ?? []),
        courses: @json(($courses ?? collect())->map(fn($c) => [
            'id' => $c->id,
            'name' => trim($c->subject_name . ' ' . $c->grade . ($c->section ? ' / ' . $c->section : '')),
        ])->values()),
        selectedCourseId: @json($selectedCourseId ?? null),
        planificacionId: @json($planning->planificacion_id ?? null),
        isLoading: false,
        activeSessionIndex: 0,
        
        init() {
            if (this.sessions.length === 0) {
                this.addSession();
            } else {
                this.sessions.forEach((s, i) => this.updateDayName(i));
            }
            this.activeSessionIndex = 0;
            if (!this.selectedCourseId && this.courses.length > 0) {
                this.selectedCourseId = this.courses[0].id;
            }
        },

        addSession() {
            const lastDate = this.sessions.length > 0 
                ? new Date(this.sessions[this.sessions.length - 1].date + 'T00:00:00')
                : new Date();
            if(this.sessions.length > 0) lastDate.setDate(lastDate.getDate() + 1);

            this.sessions.push({
                id: Date.now(),
                date: lastDate.toISOString().split('T')[0],
                day: this.formatDayName(lastDate),
                inicio: '', desarrollo: '', cierre: ''
            });
            this.activeSessionIndex = this.sessions.length - 1;
        },

        removeSession(index) { 
            this.sessions.splice(index, 1); 
            if (this.activeSessionIndex >= this.sessions.length) {
                this.activeSessionIndex = Math.max(0, this.sessions.length - 1);
            }
            Swal.fire({
                title: 'Sesión eliminada',
                text: 'La sesión ha sido removida',
                icon: 'info',
                timer: 1500,
                showConfirmButton: false,
                background: '#1A1A2E',
                color: '#fff'
            });
        },

        setActiveSession(index) {
            this.activeSessionIndex = index;
        },

        currentSession() {
            if (!this.sessions.length) return null;
            return this.sessions[this.activeSessionIndex] ?? this.sessions[0];
        },

        completionCount() {
            return this.sessions.filter((s) => (s.inicio || '').trim() && (s.desarrollo || '').trim()).length;
        },
        
        formatDayName(date) { return date.toLocaleDateString('es-ES', { weekday: 'long' }); },
        
        formatDisplayDate(dateStr) {
            if(!dateStr) return 'Seleccionar fecha';
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },
        
        updateDayName(index) {
            if(this.sessions[index].date) {
                const d = new Date(this.sessions[index].date + 'T00:00:00');
                this.sessions[index].day = this.formatDayName(d);
            }
        },

        async save() {
            if(this.isLoading) return;
            if (!this.selectedCourseId) {
                Swal.fire({
                    title: 'Curso requerido',
                    text: 'Selecciona un curso antes de guardar la planificación.',
                    icon: 'warning',
                    background: '#1A1A2E',
                    color: '#fff',
                    confirmButtonColor: '#7c3aed'
                });
                return;
            }
            this.isLoading = true;
            try {
                const response = await fetch("{{ route('teacher.planner.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        course_id: Number(this.selectedCourseId),
                        planificacion_id: this.planificacionId,
                        sessions: this.sessions
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    Swal.fire({ 
                        title: '¡Planificación Guardada!', 
                        text: "Tus sesiones han sido sincronizadas correctamente.", 
                        icon: 'success', 
                        background: '#1A1A2E',
                        color: '#fff',
                        confirmButtonColor: '#7c3aed',
                        timer: 2000,
                        timerProgressBar: true
                    });
                    setTimeout(() => window.location.href = data.redirect, 1500);
                } else throw new Error(data.error);
            } catch (error) {
                Swal.fire({ 
                    title: 'Error', 
                    text: error.message, 
                    icon: 'error',
                    background: '#1A1A2E',
                    color: '#fff',
                    confirmButtonColor: '#7c3aed'
                });
            } finally { this.isLoading = false; }
        }
    };
}
</script>

<style>
    [x-cloak] { display: none !important; }
    
    /* Scrollbar personalizada */
    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    
    ::-webkit-scrollbar-track {
        background: #0F0B1F;
    }
    
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #7c3aed, #c026d3);
        border-radius: 5px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, #8b5cf6, #d946ef);
    }
    
    /* Animaciones */
    @keyframes pulse-glow {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
    }
    
    .delay-1000 {
        animation-delay: 1s;
    }
    
    /* Línea de tiempo decorativa */
    .timeline-dot {
        width: 4px;
        height: 4px;
        background: linear-gradient(180deg, #7c3aed, #c026d3);
        border-radius: 50%;
    }
</style>
</body>
</html>