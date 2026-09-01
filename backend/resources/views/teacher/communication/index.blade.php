<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comunicación · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.nova-theme')
    @include('partials.teacher-mobile')
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
        .msg { margin: 8px 0; padding: 8px 10px; border-radius: 11px; max-width: 84%; white-space: pre-wrap; word-break: break-word; }
        .msg.teacher { margin-left: auto; background: color-mix(in srgb, var(--nova-violet) 16%, transparent); }
        .msg.student { margin-right: auto; background: color-mix(in srgb, var(--nova-fuchsia) 14%, transparent); }
        .msg.family { margin-right: auto; background: color-mix(in srgb, var(--nova-cyan, #22d3ee) 18%, transparent); }
        .msg .meta { display: block; font-size: 10px; font-weight: 700; opacity: .65; margin-top: 6px; }
        .thread-row { display: block; width: 100%; text-align: left; }
        .thread-row .unread { display: inline-flex; min-width: 18px; justify-content: center; border-radius: 999px; padding: 1px 6px; font-size: 10px; font-weight: 800; background: var(--nova-fuchsia); color: #fff; margin-left: 6px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th, .table td { text-align: left; border-bottom: 1px solid var(--nova-glass-border); padding: 8px 6px; }
        @media (max-width: 1080px) { .layout { grid-template-columns: 1fr; } .threads { grid-template-columns: 1fr; min-height: 0; } .row2, .grid3 { grid-template-columns: 1fr; } }
        @media (max-width: 767px) {
            .wrap { padding: 16px 16px calc(28px + env(safe-area-inset-bottom)); }
            .title { font-size: 24px; }
            .thread-list, .thread-main, .thread-side { min-height: 0; }
            .thread-main { min-height: 52vh; }
            .msg { max-width: 92%; }
            .table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
@include('partials.theme-switcher')
<div class="wrap" x-data="communicationApp()" x-init="init()" x-cloak>
    <div class="top">
        <div>
            <a href="{{ route('teacher.hub') }}" style="text-decoration:none;color:var(--nova-violet);font-weight:700;"><i class="fa-solid fa-arrow-left"></i> Volver al hub</a>
            <h1 class="title">Comunicación</h1>
            <p class="muted">Canales inteligentes para informar y conversar con familias y estudiantes.</p>
        </div>
    </div>

    <div class="tabs">
        <button class="tab" :class="{ active: tab === 'announcements' }" @click="tab = 'announcements'"><i class="fa-solid fa-bullhorn"></i> Anuncios / Circulares</button>
        <button class="tab" :class="{ active: tab === 'messages' }" @click="tab = 'messages'"><i class="fa-solid fa-comments"></i> Mensajes</button>
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
                        <strong x-text="t.contact_name"></strong>
                        <span class="pill" x-show="t.is_family || t.contact_role === 'representante'">Familia</span>
                        <span class="unread" x-show="t.unread > 0" x-text="t.unread"></span>
                        <br>
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
                                <div class="msg" :class="messageClass(m)">
                                    <div x-text="m.body"></div>
                                    <small class="meta" x-text="messageMeta(m)"></small>
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

    </div>
</div>

<script>
function communicationApp() {
    return {
        tab: 'announcements',
        notice: '',
        error: '',
        courses: @json($courses),
        students: @json($students),
        announcements: @json($announcements),
        threads: @json($threads),
        selectedThreadId: @json($threads->first()['id'] ?? null),
        chatDraft: '',
        quickReplies: [],
        poller: null,
        announcement: {
            idea: '',
            course_id: '',
            smart_segment: 'none',
            schedule_at: '',
            drive_link: '',
            files: [],
            draft: null,
        },
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        selectedThread() {
            return this.threads.find(t => t.id === this.selectedThreadId) || null;
        },
        messageClass(m) {
            if (m.sender_role === 'teacher') return 'teacher';
            if (m.sender_role === 'representante' || m.sender_role === 'parent') return 'family';
            return 'student';
        },
        messageMeta(m) {
            const who = m.sender_role === 'teacher'
                ? 'Tú'
                : (m.sender_role === 'representante' || m.sender_role === 'parent' ? 'Familia' : 'Estudiante');
            const raw = m.created_at || m.at;
            if (!raw) return who;
            const d = new Date(raw);
            if (Number.isNaN(d.getTime())) return who;
            return who + ' · ' + d.toLocaleString('es-VE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
        async selectThread(id) {
            this.selectedThreadId = id;
            this.quickReplies = [];
            this.chatDraft = '';
            try {
                await fetch(`/teacher/communication/threads/${id}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                });
                const thread = this.threads.find(t => t.id === id);
                if (thread) thread.unread = 0;
            } catch (_) {}
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
        async pollThreads() {
            try {
                const res = await fetch('{{ route('teacher.communication.threads') }}', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.success) this.threads = data.threads || [];
            } catch (_) {}
        },
        init() {
            this.poller = setInterval(() => {
                if (this.tab === 'messages') this.pollThreads();
            }, 12000);
        },
    };
}
</script>
</body>
</html>

