<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Planificador · AulaSync</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.nova-theme')
    @include('partials.teacher-mobile')
    <style>
        [x-cloak] { display: none !important; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Inter, Nunito, system-ui, sans-serif;
            background:
                radial-gradient(circle at top right, color-mix(in srgb, var(--nova-violet) 16%, transparent) 0%, transparent 42%),
                radial-gradient(circle at 8% 88%, color-mix(in srgb, #06B6D4 12%, transparent) 0%, transparent 36%),
                var(--bg-primary);
            color: var(--text-primary);
        }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 18px 110px; }
        .top { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 18px; flex-wrap: wrap; }
        .crumb { color: var(--nova-violet); text-decoration: none; font-weight: 700; font-size: 13px; }
        h1 { margin: 8px 0 4px; font-size: 30px; letter-spacing: -0.03em; }
        .muted { color: var(--text-secondary); }
        .subtle { color: var(--text-tertiary); font-size: 12px; }
        .tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; padding: 4px; background: color-mix(in srgb, var(--bg-card) 80%, transparent); border: 1px solid var(--nova-glass-border); border-radius: 14px; width: fit-content; }
        .tab { border: 0; background: transparent; color: var(--text-secondary); border-radius: 10px; padding: 9px 14px; cursor: pointer; font-weight: 700; font-size: 13px; }
        .tab.active { background: var(--nova-gradient); color: #fff; }
        .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); box-shadow: var(--nova-shadow); border-radius: 20px; padding: 16px 18px; backdrop-filter: blur(8px); }
        .btn { border: 0; border-radius: 999px; padding: 9px 14px; font-weight: 800; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; color: inherit; }
        .btn-ai { background: var(--nova-gradient); color: #fff; }
        .btn-soft { background: color-mix(in srgb, var(--nova-violet) 13%, var(--bg-secondary)); color: var(--nova-violet); }
        .btn-ghost { background: transparent; color: var(--text-secondary); }
        .btn-danger { background: rgba(244, 63, 94, 0.12); color: #e11d48; }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        label { display: block; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin: 0 0 6px; color: var(--text-tertiary); }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--nova-glass-border); background: var(--bg-secondary); color: var(--text-primary); border-radius: 12px; padding: 10px 12px; font: inherit; }
        textarea { resize: vertical; min-height: 88px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row3 { display: grid; grid-template-columns: 1.4fr .8fr .8fr; gap: 12px; }
        .style-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 12px 0 4px; }
        .style-chip { border: 1px solid var(--nova-glass-border); background: var(--bg-secondary); border-radius: 16px; padding: 12px; text-align: left; cursor: pointer; color: inherit; transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease; }
        .style-chip:hover { transform: translateY(-2px); }
        .style-chip.active { border-color: transparent; color: #fff; background: var(--nova-gradient); box-shadow: 0 12px 24px -16px rgba(124,58,237,.8); }
        .style-chip b { display: block; font-size: 13px; }
        .style-chip span { display: block; font-size: 11px; opacity: .8; margin-top: 4px; }
        .session-stack { display: flex; flex-direction: column; gap: 12px; }
        .session-card { border: 1px solid var(--nova-glass-border); border-left: 5px solid var(--phase-accent, #7C3AED); border-radius: 18px; background: color-mix(in srgb, var(--bg-card) 92%, white); padding: 14px; box-shadow: 0 12px 24px -22px rgba(15,23,42,.55); }
        .session-head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; margin-bottom: 10px; }
        .session-index { width: 34px; height: 34px; border-radius: 12px; display: grid; place-items: center; font-weight: 800; color: #fff; background: var(--nova-gradient); flex-shrink: 0; }
        .phase-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
        .phase-box { border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 10px; background: var(--bg-secondary); }
        .phase-box header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; color: var(--phase-color); font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .ai-banner { display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: 16px; margin-bottom: 12px; background: linear-gradient(135deg, color-mix(in srgb, var(--nova-violet) 16%, transparent), var(--bg-card)); }
        .toast { position: fixed; right: 18px; bottom: 88px; background: #111827; color: #fff; padding: 10px 14px; border-radius: 12px; z-index: 40; font-size: 13px; }
        .save-bar { position: fixed; right: 18px; bottom: 18px; z-index: 30; }
        .empty { text-align: center; padding: 36px 12px; color: var(--text-tertiary); }
        @media (max-width: 900px) {
            .style-grid, .row2, .row3 { grid-template-columns: 1fr; }
            .wrap { padding: 16px 16px 120px; }
        }
    </style>
</head>
<body>
@include('partials.theme-switcher')
<div class="wrap" x-data="manualPlanner()" x-cloak>
    <div class="top">
        <div>
            <a class="crumb" href="{{ route('historial') }}"><i class="fa-solid fa-arrow-left"></i> Mis planificaciones</a>
            <h1>Nueva planificación</h1>
            <p class="muted" style="margin:0; max-width:640px;">Elige el estilo de clase, pide las tarjetas a la IA o créalas a mano. Luego edita, agrega o elimina cada sesión antes de guardar.</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a class="btn btn-ghost" href="{{ route('teacher.hub') }}"><i class="fa-solid fa-house"></i> Hub</a>
            <button class="btn btn-soft" type="button" @click="addSession()"><i class="fa-solid fa-plus"></i> Nueva sesión</button>
        </div>
    </div>

    <div class="tabs">
        <button class="tab" :class="{ active: mode === 'manual' }" @click="mode = 'manual'"><i class="fa-solid fa-pen"></i> Manual</button>
        <button class="tab" :class="{ active: mode === 'ai' }" @click="mode = 'ai'"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear con IA</button>
    </div>

    <section class="card" style="margin-bottom:16px;">
        <div class="row3">
            <div>
                <label>Curso objetivo</label>
                <select x-model="selectedCourseId">
                    <template x-for="course in courses" :key="course.id">
                        <option :value="course.id" x-text="course.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label>Sesiones</label>
                <div class="btn btn-soft" style="width:100%; justify-content:center;" x-text="sessions.length + ' tarjetas'"></div>
            </div>
            <div>
                <label>Estilo activo</label>
                <div class="btn btn-soft" style="width:100%; justify-content:center;" x-text="currentTemplate()?.label || 'Clásica'"></div>
            </div>
        </div>

        <label style="margin-top:14px;">Estilo de clase</label>
        <div class="style-grid">
            <template x-for="tpl in templates" :key="tpl.id">
                <button type="button" class="style-chip" :class="{ active: lessonTemplate === tpl.id }" @click="setTemplate(tpl.id)">
                    <b x-text="tpl.label"></b>
                    <span x-text="tpl.phases.map(p => p.label).join(' · ')"></span>
                </button>
            </template>
        </div>
    </section>

    <section class="card" x-show="mode === 'ai'" style="margin-bottom:16px;">
        <div class="ai-banner">
            <i class="fa-solid fa-sparkles" style="color:var(--nova-violet); margin-top:3px;"></i>
            <div>
                <strong>Pídele a la IA el tipo de planificación que quieres</strong>
                <p class="subtle" style="margin:4px 0 0;">Genera las tarjetas con las casillas del estilo elegido. Después puedes editarlas una por una.</p>
            </div>
        </div>
        <label>Qué quieres planificar</label>
        <textarea x-model="aiPrompt" rows="4" placeholder="Ej. Planifica 4 clases de fotosíntesis para Biología 3ro en septiembre, con laboratorio y cierre de evaluación."></textarea>
        <div class="row2" style="margin-top:12px;">
            <div>
                <label>Cantidad de sesiones</label>
                <input type="number" min="1" max="12" x-model.number="sessionCount">
            </div>
            <div>
                <label>Fecha de inicio</label>
                <input type="date" x-model="startDate">
            </div>
        </div>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-ai" type="button" :disabled="aiLoading" @click="generateWithAI()">
                <i class="fa-solid" :class="aiLoading ? 'fa-circle-notch fa-spin' : 'fa-wand-magic-sparkles'"></i>
                <span x-text="aiLoading ? 'Generando…' : 'Generar tarjetas'"></span>
            </button>
            <span class="subtle" x-show="aiError" x-text="aiError" style="color:#e11d48;"></span>
        </div>
    </section>

    <section class="session-stack">
        <template x-if="sessions.length === 0">
            <div class="card empty">
                <p>Aún no hay sesiones. Crea una tarjeta o pídeselas a la IA.</p>
                <button class="btn btn-ai" type="button" @click="addSession()">Crear primera sesión</button>
            </div>
        </template>
        <template x-for="(session, index) in sessions" :key="session.id || index">
            <article class="session-card" :style="`--phase-accent: ${phaseDefs()[0]?.color || '#7C3AED'}`">
                <div class="session-head">
                    <div style="display:flex; gap:10px; min-width:0;">
                        <span class="session-index" x-text="index + 1"></span>
                        <div style="min-width:0; flex:1;">
                            <label>Título de la clase</label>
                            <input x-model="session.title" :placeholder="`Sesión ${index + 1}`">
                        </div>
                    </div>
                    <button class="btn btn-danger" type="button" x-show="sessions.length > 0" @click="removeSession(index)" title="Eliminar sesión">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <div class="row2" style="margin-bottom:10px;">
                    <div>
                        <label>Fecha</label>
                        <input type="date" x-model="session.date">
                    </div>
                    <div>
                        <label>Estilo de esta tarjeta</label>
                        <div class="subtle" style="padding-top:10px;" x-text="currentTemplate()?.label"></div>
                    </div>
                </div>
                <div class="phase-grid">
                    <template x-for="phase in phaseDefs()" :key="phase.key">
                        <div class="phase-box" :style="`--phase-color: ${phase.color}`">
                            <header>
                                <i class="fa-solid" :class="phase.icon"></i>
                                <span x-text="phase.label"></span>
                            </header>
                            <textarea x-model="session.phases[phase.key]" :placeholder="phase.placeholder"></textarea>
                        </div>
                    </template>
                </div>
            </article>
        </template>
    </section>

    <div class="save-bar">
        <button class="btn btn-ai" type="button" :disabled="isLoading" @click="save()" style="padding:12px 18px; font-size:14px;">
            <i class="fa-solid" :class="isLoading ? 'fa-circle-notch fa-spin' : 'fa-floppy-disk'"></i>
            <span x-text="isLoading ? 'Guardando…' : 'Guardar planificación'"></span>
        </button>
    </div>
    <div class="toast" x-show="toast" x-text="toast" x-cloak></div>
</div>

<script>
function manualPlanner() {
    const templates = @json($templates ?? []);
    const existing = @json($planning->sessions ?? []);
    return {
        mode: 'manual',
        templates,
        lessonTemplate: @json($lessonTemplate ?? 'clasica'),
        sessions: [],
        courses: @json(($courses ?? collect())->map(fn($c) => [
            'id' => $c->id,
            'name' => trim($c->subject_name . ' ' . $c->grade . ($c->section ? ' / ' . $c->section : '')),
        ])->values()),
        selectedCourseId: @json($selectedCourseId ?? null),
        planificacionId: @json($planning->planificacion_id ?? null),
        isLoading: false,
        aiLoading: false,
        aiPrompt: '',
        aiError: '',
        sessionCount: 4,
        startDate: new Date().toISOString().slice(0, 10),
        toast: '',
        init() {
            this.sessions = (existing || []).map((s, i) => this.hydrateSession(s, i));
            if (!this.sessions.length) this.addSession();
            if (!this.selectedCourseId && this.courses.length) this.selectedCourseId = this.courses[0].id;
        },
        currentTemplate() {
            return this.templates.find(t => t.id === this.lessonTemplate) || this.templates[0];
        },
        phaseDefs() {
            return this.currentTemplate()?.phases || [];
        },
        emptyPhases() {
            const out = {};
            this.phaseDefs().forEach(p => { out[p.key] = ''; });
            return out;
        },
        hydrateSession(raw, index = 0) {
            const phases = this.emptyPhases();
            const incoming = raw?.phases && typeof raw.phases === 'object' ? raw.phases : {};
            Object.keys(phases).forEach(key => {
                phases[key] = incoming[key] || raw?.[key] || '';
            });
            if (!Object.values(phases).some(Boolean)) {
                if (raw?.inicio) phases[this.phaseDefs()[0]?.key] = raw.inicio;
                if (raw?.desarrollo && this.phaseDefs()[1]) phases[this.phaseDefs()[1].key] = raw.desarrollo;
                if (raw?.cierre && this.phaseDefs().at(-1)) phases[this.phaseDefs().at(-1).key] = raw.cierre;
            }
            return {
                id: raw?.id || Date.now() + index,
                date: raw?.date || this.startDate,
                title: raw?.title || '',
                phases,
            };
        },
        setTemplate(id) {
            this.lessonTemplate = id;
            this.sessions = this.sessions.map((s, i) => this.hydrateSession({ ...s, phases: s.phases }, i));
        },
        addSession() {
            const last = this.sessions[this.sessions.length - 1];
            const next = last?.date ? new Date(last.date + 'T00:00:00') : new Date();
            if (last?.date) next.setDate(next.getDate() + 1);
            this.sessions.push({
                id: Date.now(),
                date: next.toISOString().slice(0, 10),
                title: '',
                phases: this.emptyPhases(),
            });
        },
        removeSession(index) {
            this.sessions.splice(index, 1);
            this.flash('Sesión eliminada');
        },
        flash(text) {
            this.toast = text;
            setTimeout(() => { if (this.toast === text) this.toast = ''; }, 1800);
        },
        async generateWithAI() {
            if (this.aiLoading) return;
            if ((this.aiPrompt || '').trim().length < 8) {
                this.aiError = 'Describe qué planificación quieres (mínimo 8 caracteres).';
                return;
            }
            this.aiLoading = true;
            this.aiError = '';
            try {
                const res = await fetch(@json(route('teacher.planner.generate')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        prompt: this.aiPrompt,
                        course_id: this.selectedCourseId,
                        lesson_template: this.lessonTemplate,
                        session_count: this.sessionCount,
                        start_date: this.startDate,
                    }),
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'No se pudo generar.');
                this.lessonTemplate = data.lesson_template || this.lessonTemplate;
                this.sessions = (data.sessions || []).map((s, i) => this.hydrateSession(s, i));
                this.mode = 'manual';
                this.flash('Tarjetas listas para editar');
            } catch (e) {
                this.aiError = e.message;
            } finally {
                this.aiLoading = false;
            }
        },
        async save() {
            if (this.isLoading) return;
            if (!this.selectedCourseId) {
                this.flash('Selecciona un curso');
                return;
            }
            this.isLoading = true;
            try {
                const res = await fetch(@json(route('teacher.planner.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        course_id: Number(this.selectedCourseId),
                        planificacion_id: this.planificacionId,
                        lesson_template: this.lessonTemplate,
                        sessions: this.sessions,
                    }),
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'No se pudo guardar.');
                this.flash('Planificación guardada');
                setTimeout(() => { window.location.href = data.redirect || @json(route('historial')); }, 700);
            } catch (e) {
                this.flash(e.message);
            } finally {
                this.isLoading = false;
            }
        },
    };
}
</script>
</body>
</html>
