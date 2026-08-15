<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comunicación · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
        .wrap { max-width: 1260px; margin: 0 auto; padding: 24px 20px 70px; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .title { margin: 0; font-size: 32px; font-weight: 900; }
        .muted { color: var(--text-secondary); margin: 3px 0 0; }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
        .tab { border: 1px solid var(--nova-glass-border); border-radius: 999px; padding: 9px 14px; background: var(--bg-card); font-weight: 700; color: var(--text-secondary); cursor: pointer; }
        .tab.active { background: var(--nova-gradient); color: #fff; border-color: transparent; }
        .layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; }
        .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); border-radius: 18px; padding: 16px; box-shadow: var(--nova-shadow); }
        .card h3 { margin: 0 0 10px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        label { display: block; margin: 8px 0 5px; font-size: 12px; font-weight: 700; color: var(--text-secondary); }
        input, textarea, select { width: 100%; box-sizing: border-box; border: 1px solid var(--nova-glass-border); border-radius: 10px; background: var(--bg-secondary); color: var(--text-primary); padding: 9px 10px; }
        textarea { min-height: 90px; }
        .btn { border: 0; border-radius: 999px; padding: 9px 14px; font-weight: 800; cursor: pointer; }
        .btn-main { background: var(--nova-violet); color: #fff; }
        .btn-soft { background: color-mix(in srgb, var(--nova-violet) 13%, var(--bg-secondary)); color: var(--nova-violet); }
        .btn-alert { background: var(--nova-fuchsia); color: #fff; }
        .stack { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .list-item { border-bottom: 1px solid var(--nova-glass-border); padding: 10px 0; }
        .pill { border-radius: 999px; padding: 4px 8px; font-size: 11px; font-weight: 800; background: color-mix(in srgb, var(--nova-violet) 14%, transparent); color: var(--nova-violet); }
        .ok { color: #0F766E; font-weight: 700; }
        .warn { color: #B45309; font-weight: 700; }
        .threads { display: grid; grid-template-columns: 290px 1fr 280px; gap: 10px; min-height: 520px; }
        .thread-list, .thread-main, .thread-side { border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 10px; background: var(--bg-secondary); }
        .thread-row { border: 1px solid transparent; border-radius: 10px; padding: 8px; cursor: pointer; margin-bottom: 6px; }
        .thread-row.active { border-color: var(--nova-violet); background: color-mix(in srgb, var(--nova-violet) 9%, transparent); }
        .msg { margin: 8px 0; padding: 8px 10px; border-radius: 11px; max-width: 84%; }
        .msg.teacher { margin-left: auto; background: color-mix(in srgb, var(--nova-violet) 16%, transparent); }
        .msg.student { margin-right: auto; background: color-mix(in srgb, var(--nova-fuchsia) 14%, transparent); }
        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th, .table td { text-align: left; border-bottom: 1px solid var(--nova-glass-border); padding: 8px 6px; }
        @media (max-width: 1080px) { .layout { grid-template-columns: 1fr; } .threads { grid-template-columns: 1fr; } .row2, .grid3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
@include('partials.theme-switcher')
<div class="wrap" x-data="communicationApp()" x-cloak>
    <div class="top">
        <div>
            <a href="{{ route('teacher.hub') }}" style="text-decoration:none;color:var(--nova-violet);font-weight:700;"><i class="fa-solid fa-arrow-left"></i> Volver al hub</a>
            <h1 class="title">Comunicación</h1>
            <p class="muted">Canales inteligentes para informar, conversar y planificar evaluaciones sin fricción.</p>
        </div>
    </div>

    <div class="tabs">
        <button class="tab" :class="{ active: tab === 'announcements' }" @click="tab = 'announcements'"><i class="fa-solid fa-bullhorn"></i> Anuncios / Circulares</button>
        <button class="tab" :class="{ active: tab === 'messages' }" @click="tab = 'messages'"><i class="fa-solid fa-comments"></i> Mensajes</button>
        <button class="tab" :class="{ active: tab === 'plans' }" @click="tab = 'plans'"><i class="fa-solid fa-sitemap"></i> Plan de Evaluación</button>
    </div>

    <div x-show="tab === 'announcements'" class="layout">
        <div class="card">
            <h3><i class="fa-solid fa-feather-pointed"></i> Crear anuncio con IA</h3>
            <label>Idea informal</label>
            <textarea x-model="announcement.idea" placeholder="Ej: Informar que el examen se pospuso por jornada pedagógica."></textarea>
            <div class="row2">
                <div>
                    <label>Curso</label>
                    <select x-model="announcement.course_id">
                        <option value="">Todos mis cursos</option>
                        <template x-for="c in courses" :key="c.id">
                            <option :value="c.id" x-text="`${c.subject_name} · ${c.grade}${c.section ? ' / ' + c.section : ''}`"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label>Segmentación inteligente</label>
                    <select x-model="announcement.smart_segment">
                        <option value="none">Sin filtro extra</option>
                        <option value="pending_tasks">Solo estudiantes con pendientes</option>
                        <option value="low_score">Solo estudiantes con bajo rendimiento</option>
                    </select>
                </div>
            </div>
            <div class="row2">
                <div>
                    <label>Programar envío</label>
                    <input type="datetime-local" x-model="announcement.schedule_at">
                </div>
                <div>
                    <label>Adjunto Google Drive</label>
                    <input x-model="announcement.drive_link" placeholder="https://drive.google.com/...">
                </div>
            </div>
            <label>Adjuntos desde dispositivo</label>
            <input type="file" multiple @change="announcement.files = Array.from($event.target.files || [])">
            <div class="stack">
                <button class="btn btn-main" @click="generateAnnouncement()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generar borrador IA</button>
                <button class="btn btn-alert" @click="publishAnnouncement()"><i class="fa-solid fa-paper-plane"></i> Programar anuncio</button>
            </div>
            <p class="ok" x-show="notice" x-text="notice"></p>
            <p class="warn" x-show="error" x-text="error"></p>
            <template x-if="announcement.draft">
                <div style="margin-top:12px;">
                    <label>Título</label>
                    <input x-model="announcement.draft.title">
                    <label>Mensaje final</label>
                    <textarea x-model="announcement.draft.body" style="min-height:140px;"></textarea>
                </div>
            </template>
        </div>
        <div class="card">
            <h3><i class="fa-solid fa-chart-simple"></i> Métricas de alcance</h3>
            <template x-if="announcements.length === 0"><p class="muted">Aún no hay circulares enviadas.</p></template>
            <template x-for="item in announcements" :key="item.id">
                <div class="list-item">
                    <div style="display:flex;justify-content:space-between;gap:8px;">
                        <strong x-text="item.title"></strong>
                        <span class="pill" x-text="item.status"></span>
                    </div>
                    <small class="muted">Leído: <span x-text="item.read_count"></span> / <span x-text="item.recipients_count"></span></small>
                    <div class="stack">
                        <button class="btn btn-soft" @click="demoRead(item.id)">Registrar lectura demo</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="tab === 'messages'" class="card">
        <h3><i class="fa-solid fa-inbox"></i> Mensajes privados por estudiante</h3>
        <div class="threads">
            <div class="thread-list">
                <template x-for="t in threads" :key="t.id">
                    <div class="thread-row" :class="{ active: selectedThreadId === t.id }" @click="selectThread(t.id)">
                        <strong x-text="t.contact_name"></strong><br>
                        <small class="muted" x-text="t.last_message_preview || 'Sin mensajes'"></small>
                    </div>
                </template>
            </div>
            <div class="thread-main">
                <template x-if="!selectedThread()"><p class="muted">Selecciona un chat para comenzar.</p></template>
                <template x-if="selectedThread()">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                            <strong x-text="selectedThread().contact_name"></strong>
                            <button class="btn btn-soft" @click="simulateIncoming()">Simular pregunta</button>
                        </div>
                        <div style="max-height:340px; overflow:auto; margin-top:10px;">
                            <template x-for="m in selectedThread().messages" :key="m.id">
                                <div class="msg" :class="m.sender_role === 'teacher' ? 'teacher' : 'student'">
                                    <div x-text="m.body"></div>
                                </div>
                            </template>
                        </div>
                        <label>Responder</label>
                        <textarea x-model="chatDraft" placeholder="Escribe una respuesta clara y profesional."></textarea>
                        <div class="stack">
                            <button class="btn btn-main" @click="sendMessage(false)">Enviar mensaje</button>
                            <button class="btn btn-soft" @click="suggestReplies()"><i class="fa-solid fa-bolt"></i> Sugerencias IA</button>
                        </div>
                        <div class="stack" x-show="quickReplies.length > 0">
                            <template x-for="(s, idx) in quickReplies" :key="idx">
                                <button class="btn btn-soft" @click="applySuggestion(s)" x-text="s"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
            <div class="thread-side">
                <template x-if="selectedThread() && selectedThread().student">
                    <div>
                        <h4 style="margin:0 0 8px;">Contexto académico</h4>
                        <div class="list-item"><strong>Estudiante:</strong> <span x-text="selectedThread().student.name"></span></div>
                        <div class="list-item"><strong>Promedio:</strong> <span x-text="selectedThread().student_avg ?? 'Sin datos'"></span></div>
                        <div class="list-item"><strong>Curso:</strong> <span x-text="`${selectedThread().student.grade} ${selectedThread().student.section || ''}`"></span></div>
                    </div>
                </template>
                <template x-if="!selectedThread()"><p class="muted">Aquí verás rendimiento y resumen del estudiante.</p></template>
            </div>
        </div>
    </div>

    <div x-show="tab === 'plans'" class="layout">
        <div class="card">
            <h3><i class="fa-solid fa-sliders"></i> Generar plan de evaluación con IA</h3>
            <div class="row2">
                <div>
                    <label>Curso</label>
                    <select x-model="planForm.course_id">
                        <option value="">Selecciona un curso</option>
                        <template x-for="c in courses" :key="c.id">
                            <option :value="c.id" x-text="`${c.subject_name} · ${c.grade}${c.section ? ' / ' + c.section : ''}`"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label>Semanas del período</label>
                    <input type="number" min="4" max="40" x-model.number="planForm.weeks">
                </div>
            </div>
            <label>Programa de la materia</label>
            <textarea x-model="planForm.program_text" placeholder="Describe unidades, objetivos y ritmo del curso para que IA distribuya evaluaciones y porcentajes."></textarea>
            <div class="stack">
                <button class="btn btn-main" @click="generatePlan()">Generar plan de evaluación para todo el curso</button>
                <button class="btn btn-soft" x-show="planDraft" @click="analyzeOverload()">Alerta de sobrecarga</button>
            </div>
            <p class="ok" x-show="planMessage" x-text="planMessage"></p>
            <p class="warn" x-show="planWarning" x-text="planWarning"></p>
            <template x-if="planDraft">
                <div style="margin-top:14px;">
                    <label>Título del plan</label>
                    <input x-model="planDraft.title">
                    <label>Resumen</label>
                    <textarea x-model="planDraft.summary"></textarea>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Unidad</th>
                                <th>Evaluación</th>
                                <th>%</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, idx) in planDraft.items" :key="idx">
                                <tr>
                                    <td><input x-model="item.unit_name"></td>
                                    <td><input x-model="item.assessment_type"></td>
                                    <td><input type="number" min="0" max="100" x-model.number="item.weight_percentage"></td>
                                    <td><input type="date" x-model="item.due_date"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="stack">
                        <button class="btn btn-main" @click="savePlan()">Guardar plan</button>
                        <button class="btn btn-alert" :disabled="!savedPlanId" @click="publishToCalendar()">Publicar en calendario</button>
                    </div>
                </div>
            </template>
        </div>
        <div class="card">
            <h3><i class="fa-solid fa-list-check"></i> Planes guardados</h3>
            <template x-if="plans.length === 0"><p class="muted">No hay planes guardados todavía.</p></template>
            <template x-for="p in plans" :key="p.id">
                <div class="list-item">
                    <strong x-text="p.title"></strong>
                    <div class="muted" x-text="p.course ? `${p.course.subject_name} · ${p.course.grade}` : 'Sin curso'"></div>
                    <small x-text="`${(p.items || []).length} ítems`"></small>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function communicationApp() {
    return {
        tab: 'announcements',
        notice: '',
        error: '',
        planMessage: '',
        planWarning: '',
        courses: @json($courses),
        students: @json($students),
        announcements: @json($announcements),
        threads: @json($threads),
        plans: @json($plans),
        selectedThreadId: @json($threads->first()['id'] ?? null),
        chatDraft: '',
        quickReplies: [],
        savedPlanId: null,
        announcement: {
            idea: '',
            course_id: '',
            smart_segment: 'none',
            schedule_at: '',
            drive_link: '',
            files: [],
            draft: null,
        },
        planForm: { course_id: '', program_text: '', weeks: 12 },
        planDraft: null,
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        selectedThread() {
            return this.threads.find(t => t.id === this.selectedThreadId) || null;
        },
        selectThread(id) {
            this.selectedThreadId = id;
            this.quickReplies = [];
            this.chatDraft = '';
        },
        async generateAnnouncement() {
            this.error = ''; this.notice = '';
            const res = await fetch('{{ route('teacher.communication.announcements.generate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ idea: this.announcement.idea, audience: 'Comunidad académica' }),
            });
            const data = await res.json();
            if (!data.success) { this.error = data.error || 'No se pudo generar.'; return; }
            this.announcement.draft = data.result;
            this.notice = 'Borrador generado por IA.';
        },
        async publishAnnouncement() {
            this.error = ''; this.notice = '';
            if (!this.announcement.draft?.title || !this.announcement.draft?.body) {
                this.error = 'Primero genera o escribe un borrador.';
                return;
            }
            const form = new FormData();
            form.append('title', this.announcement.draft.title);
            form.append('body', this.announcement.draft.body);
            form.append('course_id', this.announcement.course_id || '');
            form.append('smart_segment', this.announcement.smart_segment);
            form.append('schedule_at', this.announcement.schedule_at || '');
            form.append('drive_link', this.announcement.drive_link || '');
            for (const file of this.announcement.files) form.append('files[]', file);
            const res = await fetch('{{ route('teacher.communication.announcements.store') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: form,
            });
            const data = await res.json();
            if (!data.success) { this.error = data.error || 'No se pudo guardar.'; return; }
            this.announcements.unshift(data.announcement);
            this.notice = 'Anuncio guardado y segmentado.';
        },
        async demoRead(id) {
            const res = await fetch(`/teacher/communication/announcements/${id}/demo-read`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!data.success) return;
            const idx = this.announcements.findIndex(a => a.id === id);
            if (idx >= 0) this.announcements[idx] = data.announcement;
        },
        async sendMessage(aiSuggested) {
            const thread = this.selectedThread();
            if (!thread || !this.chatDraft.trim()) return;
            const res = await fetch(`/teacher/communication/threads/${thread.id}/messages`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ body: this.chatDraft, ai_suggested: !!aiSuggested }),
            });
            const data = await res.json();
            if (!data.success) return;
            thread.messages.push(data.message);
            thread.last_message_preview = data.message.body.slice(0, 130);
            this.chatDraft = '';
        },
        async simulateIncoming() {
            const thread = this.selectedThread();
            if (!thread) return;
            const res = await fetch(`/teacher/communication/threads/${thread.id}/simulate-incoming`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                thread.messages.push(data.message);
                thread.last_message_preview = data.message.body.slice(0, 130);
            }
        },
        async suggestReplies() {
            const thread = this.selectedThread();
            if (!thread) return;
            const last = [...thread.messages].reverse().find(m => m.sender_role !== 'teacher');
            const res = await fetch(`/teacher/communication/threads/${thread.id}/quick-replies`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ incoming: last ? last.body : '' }),
            });
            const data = await res.json();
            if (data.success) this.quickReplies = data.suggestions || [];
        },
        applySuggestion(text) {
            this.chatDraft = text;
        },
        async generatePlan() {
            this.planMessage = ''; this.planWarning = '';
            const res = await fetch('{{ route('teacher.communication.plans.generate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(this.planForm),
            });
            const data = await res.json();
            if (!data.success) { this.planWarning = data.error || 'No se pudo generar el plan.'; return; }
            this.planDraft = data.plan;
            this.savedPlanId = null;
            this.planMessage = 'Plan generado. Puedes editarlo antes de guardar.';
        },
        async analyzeOverload() {
            if (!this.planDraft || !this.planForm.course_id) return;
            const res = await fetch('{{ route('teacher.communication.plans.overload') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ course_id: this.planForm.course_id, items: this.planDraft.items || [] }),
            });
            const data = await res.json();
            this.planWarning = data.message || '';
            if (Array.isArray(data.warnings) && data.warnings.length) {
                this.planWarning += ' ' + data.warnings.join(' | ');
            }
        },
        async savePlan() {
            if (!this.planDraft) return;
            const payload = {
                course_id: this.planForm.course_id,
                title: this.planDraft.title || 'Plan de evaluación',
                summary: this.planDraft.summary || '',
                items: this.planDraft.items || [],
            };
            const res = await fetch('{{ route('teacher.communication.plans.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.success) { this.planWarning = data.error || 'No se pudo guardar.'; return; }
            this.plans.unshift(data.plan);
            this.savedPlanId = data.plan.id;
            this.planMessage = 'Plan guardado correctamente.';
        },
        async publishToCalendar() {
            if (!this.savedPlanId) return;
            const res = await fetch(`/teacher/communication/plans/${this.savedPlanId}/publish-calendar`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
            });
            const data = await res.json();
            this.planMessage = data.message || 'Plan publicado en calendario.';
        },
    };
}
</script>
</body>
</html>

