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
        .wa {
            display: grid;
            grid-template-columns: 320px 1fr;
            min-height: min(72vh, 720px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 18px;
            overflow: hidden;
            background: var(--bg-secondary);
            position: relative;
        }
        .wa-list, .wa-chat { min-height: 0; display: flex; flex-direction: column; }
        .wa-list { border-right: 1px solid var(--nova-glass-border); background: var(--bg-card); }
        .wa-list-head, .wa-chat-head {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 12px 14px; border-bottom: 1px solid var(--nova-glass-border);
        }
        .wa-list-head h3, .wa-chat-head h3 { margin: 0; font-size: 16px; }
        .wa-plus {
            width: 38px; height: 38px; border: 0; border-radius: 50%; cursor: pointer;
            background: var(--nova-gradient); color: #fff; font-size: 18px; font-weight: 800;
        }
        .wa-search { padding: 10px 12px; }
        .wa-search input { margin: 0; }
        .wa-rows { overflow: auto; flex: 1; padding: 6px; }
        .thread-row {
            display: flex; gap: 10px; align-items: center; width: 100%; text-align: left;
            border: 0; background: transparent; color: inherit; cursor: pointer;
            border-radius: 14px; padding: 10px; margin-bottom: 4px;
        }
        .thread-row.active { background: color-mix(in srgb, var(--nova-violet) 14%, transparent); }
        .thread-row:hover { background: color-mix(in srgb, var(--nova-violet) 8%, transparent); }
        .wa-avatar {
            width: 42px; height: 42px; border-radius: 50%; flex: none;
            display: grid; place-items: center; font-weight: 800; color: #fff;
            background: var(--nova-gradient); font-size: 14px;
        }
        .wa-row-main { min-width: 0; flex: 1; }
        .wa-row-top { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; }
        .wa-row-top strong { font-size: 13px; }
        .wa-time { font-size: 10px; color: var(--text-secondary); white-space: nowrap; }
        .wa-preview { font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .unread {
            display: inline-flex; min-width: 18px; justify-content: center; border-radius: 999px;
            padding: 1px 6px; font-size: 10px; font-weight: 800; background: var(--nova-fuchsia); color: #fff;
        }
        .wa-chat { background: color-mix(in srgb, var(--bg-primary) 88%, var(--nova-violet) 12%); }
        .wa-back { display: none; }
        .wa-chat-head { background: var(--bg-card); }
        .wa-chat-copy small { display: block; color: var(--text-secondary); font-weight: 600; margin-top: 2px; }
        .wa-msgs {
            flex: 1; overflow: auto; padding: 16px 14px 8px;
            display: flex; flex-direction: column; gap: 6px;
        }
        .msg {
            margin: 0; padding: 8px 12px; border-radius: 16px; max-width: 78%;
            white-space: pre-wrap; word-break: break-word; box-shadow: 0 8px 18px -16px rgba(0,0,0,.5);
        }
        .msg.teacher { margin-left: auto; border-bottom-right-radius: 6px; background: color-mix(in srgb, var(--nova-violet) 28%, var(--bg-card)); }
        .msg.student, .msg.family { margin-right: auto; border-bottom-left-radius: 6px; background: var(--bg-card); }
        .msg.family { border-left: 3px solid var(--nova-cyan, #22d3ee); }
        .msg .meta { display: block; font-size: 10px; font-weight: 700; opacity: .65; margin-top: 6px; text-align: right; }
        .wa-compose {
            display: flex; gap: 8px; align-items: flex-end; padding: 10px 12px 12px;
            background: var(--bg-card); border-top: 1px solid var(--nova-glass-border);
        }
        .wa-compose textarea { min-height: 44px; max-height: 120px; margin: 0; border-radius: 18px; resize: none; }
        .wa-send {
            width: 44px; height: 44px; border: 0; border-radius: 50%; cursor: pointer; flex: none;
            background: var(--nova-gradient); color: #fff;
        }
        .wa-empty { padding: 40px 24px; text-align: center; color: var(--text-secondary); }
        .wa-picker {
            position: absolute; inset: 0 auto 0 0; width: 320px; z-index: 3;
            background: var(--bg-card); display: flex; flex-direction: column;
            border-right: 1px solid var(--nova-glass-border);
        }
        .contact-row { display: flex; gap: 10px; align-items: center; width: 100%; text-align: left; border: 0; background: transparent; color: inherit; cursor: pointer; padding: 10px 12px; }
        .contact-row:hover { background: color-mix(in srgb, var(--nova-violet) 8%, transparent); }
        .pill { border-radius: 999px; padding: 4px 8px; font-size: 11px; font-weight: 800; background: color-mix(in srgb, var(--nova-violet) 14%, transparent); color: var(--nova-violet); }
        .ok { color: #0F766E; font-weight: 700; }
        .warn { color: #B45309; font-weight: 700; }
        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th, .table td { text-align: left; border-bottom: 1px solid var(--nova-glass-border); padding: 8px 6px; }
        @media (max-width: 1080px) { .layout { grid-template-columns: 1fr; } .row2, .grid3 { grid-template-columns: 1fr; } }
        @media (max-width: 860px) {
            .wa { grid-template-columns: 1fr; min-height: 70vh; }
            .wa-list { display: none; }
            .wa.show-list .wa-list { display: flex; }
            .wa.show-list .wa-chat { display: none; }
            .wa-back { display: inline-flex; }
            .wa-picker { width: 100%; border-right: 0; }
            .msg { max-width: 92%; }
            .table { display: block; overflow-x: auto; }
            .wrap { padding: 16px 16px calc(28px + env(safe-area-inset-bottom)); }
            .title { font-size: 24px; }
        }
    </style>
</head>
<body>
@csrf
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

    <div x-show="tab === 'messages'" class="card" style="padding:0;overflow:hidden">
        <div class="wa" :class="{ 'show-list': mobileList }">
            <div class="wa-list">
                <div class="wa-list-head">
                    <div>
                        <h3>Chats</h3>
                        <small class="muted" x-text="threads.length ? (threads.length + ' conversaciones') : 'Bandeja vacía'"></small>
                    </div>
                    <button type="button" class="wa-plus" @click="openNewChat()" title="Nuevo chat">+</button>
                </div>
                <div class="wa-search">
                    <input x-model="inboxQuery" placeholder="Buscar chat">
                </div>
                <div class="wa-rows">
                    <p class="wa-empty" x-show="filteredThreads().length === 0">No hay conversaciones aún. Pulsa + para escribirle a una familia.</p>
                    <template x-for="t in filteredThreads()" :key="t.id">
                        <button type="button" class="thread-row" :class="{ active: selectedThreadId === t.id }" @click="selectThread(t.id)">
                            <div class="wa-avatar" x-text="initials(t.contact_name)"></div>
                            <div class="wa-row-main">
                                <div class="wa-row-top">
                                    <strong x-text="t.contact_name"></strong>
                                    <span class="wa-time" x-text="fmtTime(t.last_message_at)"></span>
                                </div>
                                <div style="display:flex;gap:8px;align-items:center">
                                    <div class="wa-preview" x-text="t.last_message_preview || 'Sin mensajes'"></div>
                                    <span class="unread" x-show="t.unread > 0" x-text="t.unread"></span>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
            <div class="wa-chat">
                <template x-if="!selectedThread() && !draftContact">
                    <div class="wa-empty">
                        <p>Elige un chat o pulsa <strong>+</strong> para escribirle a un representante.</p>
                        <button type="button" class="btn btn-soft" @click="openNewChat()">Nuevo mensaje</button>
                    </div>
                </template>
                <template x-if="selectedThread() || draftContact">
                    <div style="display:flex;flex-direction:column;height:100%;min-height:0">
                        <div class="wa-chat-head">
                            <button type="button" class="btn btn-soft wa-back" @click="mobileList = true"><i class="fa-solid fa-arrow-left"></i></button>
                            <div class="wa-avatar" x-text="initials(chatTitle())"></div>
                            <div class="wa-chat-copy" style="flex:1;min-width:0">
                                <h3 x-text="chatTitle()"></h3>
                                <small x-text="chatSubtitle()"></small>
                            </div>
                            <button type="button" class="btn btn-soft" @click="suggestReplies()" x-show="selectedThread()"><i class="fa-solid fa-bolt"></i></button>
                        </div>
                        <div class="wa-msgs" x-ref="msgs">
                            <template x-for="m in (selectedThread()?.messages || [])" :key="m.id">
                                <div class="msg" :class="messageClass(m)">
                                    <div x-text="m.body"></div>
                                    <small class="meta" x-text="messageMeta(m)"></small>
                                </div>
                            </template>
                            <p class="muted" style="text-align:center" x-show="draftContact && !(selectedThread()?.messages || []).length">Este será el primer mensaje a la familia.</p>
                        </div>
                        <div class="stack" style="padding:0 12px" x-show="quickReplies.length > 0">
                            <template x-for="(s, idx) in quickReplies" :key="idx">
                                <button class="btn btn-soft" @click="applySuggestion(s)" x-text="s"></button>
                            </template>
                        </div>
                        <form class="wa-compose" @submit.prevent="sendChat()">
                            <textarea x-model="chatDraft" rows="1" placeholder="Mensaje" @keydown.enter.prevent="sendChat()"></textarea>
                            <button type="submit" class="wa-send" title="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                    </div>
                </template>
            </div>
            <div class="wa-picker" x-show="showNewChat" x-cloak>
                <div class="wa-list-head">
                    <div>
                        <h3>Nuevo chat</h3>
                        <small class="muted">Familias vinculadas a tus alumnos</small>
                    </div>
                    <button type="button" class="btn btn-soft" @click="showNewChat = false">Cerrar</button>
                </div>
                <div class="wa-search">
                    <input x-model="contactQuery" placeholder="Buscar alumno o representante">
                </div>
                <div class="wa-rows">
                    <p class="wa-empty" x-show="filteredContacts().length === 0">No hay representantes vinculados todavía.</p>
                    <template x-for="c in filteredContacts()" :key="c.id">
                        <button type="button" class="contact-row" @click="startWithContact(c)">
                            <div class="wa-avatar" x-text="initials(c.name)"></div>
                            <div class="wa-row-main">
                                <strong x-text="c.name"></strong>
                                <div class="wa-preview" x-text="c.parent_label + (c.grade ? ' · ' + c.grade : '')"></div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        <p class="warn" style="padding:8px 14px" x-show="error" x-text="error"></p>
    </div>

    </div>
</div>

<script>
function communicationApp() {
    return {
        tab: 'messages',
        notice: '',
        error: '',
        courses: @json($courses),
        students: @json($students),
        contacts: @json($contacts ?? []),
        announcements: @json($announcements),
        threads: @json($threads),
        selectedThreadId: @json(data_get($threads->first(), 'id')),
        chatDraft: '',
        quickReplies: [],
        poller: null,
        showNewChat: false,
        inboxQuery: '',
        contactQuery: '',
        draftContact: null,
        mobileList: true,
        announcement: {
            idea: '',
            course_id: '',
            smart_segment: 'none',
            schedule_at: '',
            drive_link: '',
            files: [],
            draft: null,
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value
                || '';
        },
        applyCsrf(token) {
            if (!token) return this.csrf();
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', token);
            document.querySelectorAll('input[name="_token"]').forEach((el) => { el.value = token; });
            return token;
        },
        async refreshCsrf() {
            try {
                const res = await fetch('{{ route('ai.session') }}', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json().catch(() => ({}));
                return this.applyCsrf(json.token) || this.csrf();
            } catch (_) {
                return this.csrf();
            }
        },
        async postJson(url, payload = {}, retry = true) {
            const token = await this.refreshCsrf();
            const headers = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            };
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({ _token: token, ...payload }),
            });
            if (res.status === 419 && retry) {
                await this.refreshCsrf();
                return this.postJson(url, payload, false);
            }
            const json = await res.json().catch(() => ({}));
            return { ok: res.ok, status: res.status, json };
        },
        async postForm(url, form, retry = true) {
            const token = await this.refreshCsrf();
            form.set('_token', token);
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: form,
            });
            if (res.status === 419 && retry) {
                await this.refreshCsrf();
                return this.postForm(url, form, false);
            }
            const json = await res.json().catch(() => ({}));
            return { ok: res.ok, json };
        },
        selectedThread() {
            return this.threads.find(t => t.id === this.selectedThreadId) || null;
        },
        filteredThreads() {
            const q = (this.inboxQuery || '').trim().toLowerCase();
            const list = this.threads || [];
            if (!q) return list;
            return list.filter(t => `${t.contact_name} ${t.last_message_preview || ''}`.toLowerCase().includes(q));
        },
        filteredContacts() {
            const q = (this.contactQuery || '').trim().toLowerCase();
            const list = this.contacts || [];
            if (!q) return list;
            return list.filter(c => `${c.name} ${c.parent_label} ${c.grade || ''}`.toLowerCase().includes(q));
        },
        initials(name) {
            const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
            const letters = (parts[0]?.[0] || '?') + (parts[1]?.[0] || '');
            return letters.toUpperCase();
        },
        fmtTime(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return '';
            const sameDay = new Date().toDateString() === d.toDateString();
            return d.toLocaleString('es-VE', sameDay
                ? { hour: '2-digit', minute: '2-digit' }
                : { day: '2-digit', month: 'short' });
        },
        chatTitle() {
            return this.selectedThread()?.contact_name || (this.draftContact ? this.draftContact.parent_label : '');
        },
        chatSubtitle() {
            if (this.selectedThread()?.student) {
                const s = this.selectedThread().student;
                return [s.name, s.grade, s.section].filter(Boolean).join(' · ');
            }
            if (this.draftContact) {
                return [this.draftContact.name, this.draftContact.grade, this.draftContact.section].filter(Boolean).join(' · ');
            }
            return 'Familia';
        },
        scrollChat() {
            this.$nextTick(() => {
                const box = this.$refs.msgs;
                if (box) box.scrollTop = box.scrollHeight;
            });
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
            this.draftContact = null;
            this.showNewChat = false;
            this.mobileList = false;
            this.quickReplies = [];
            this.chatDraft = '';
            this.scrollChat();
            try {
                await this.postJson(`/teacher/communication/threads/${id}/read`);
                const thread = this.threads.find(t => t.id === id);
                if (thread) thread.unread = 0;
            } catch (_) {}
        },
        openNewChat() {
            this.showNewChat = true;
            this.contactQuery = '';
        },
        startWithContact(contact) {
            const existing = (this.threads || []).find(t => Number(t.student_id) === Number(contact.id));
            this.showNewChat = false;
            this.mobileList = false;
            if (existing) {
                this.selectThread(existing.id);
                return;
            }
            this.selectedThreadId = null;
            this.draftContact = contact;
            this.chatDraft = '';
            this.quickReplies = [];
        },
        async sendChat() {
            const body = (this.chatDraft || '').trim();
            if (!body) return;
            this.error = '';
            if (this.draftContact && !this.selectedThread()) {
                const { ok, json: data } = await this.postJson('{{ route('teacher.communication.threads.start') }}', {
                    student_id: this.draftContact.id,
                    body,
                });
                if (!ok || !data.success) {
                    this.error = data.error || data.message || 'No se pudo iniciar el chat.';
                    return;
                }
                this.upsertThread(data.thread);
                this.selectedThreadId = data.thread.id;
                this.draftContact = null;
                this.chatDraft = '';
                this.scrollChat();
                return;
            }
            await this.sendMessage(false);
        },
        async generateAnnouncement() {
            this.error = ''; this.notice = '';
            const { ok, json: data } = await this.postJson('{{ route('teacher.communication.announcements.generate') }}', {
                idea: this.announcement.idea,
                audience: 'Comunidad académica',
            });
            if (!ok || !data.success) { this.error = data.error || 'No se pudo generar.'; return; }
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
            const { json: data } = await this.postForm('{{ route('teacher.communication.announcements.store') }}', form);
            if (!data.success) { this.error = data.error || 'No se pudo guardar.'; return; }
            this.announcements.unshift(data.announcement);
            this.notice = 'Anuncio guardado y segmentado.';
        },
        async demoRead(id) {
            const { json: data } = await this.postJson(`/teacher/communication/announcements/${id}/demo-read`);
            if (!data.success) return;
            const idx = this.announcements.findIndex(a => a.id === id);
            if (idx >= 0) this.announcements[idx] = data.announcement;
        },
        async sendMessage(aiSuggested) {
            const thread = this.selectedThread();
            if (!thread || !this.chatDraft.trim()) return;
            const { ok, json: data } = await this.postJson(`/teacher/communication/threads/${thread.id}/messages`, {
                body: this.chatDraft,
                ai_suggested: !!aiSuggested,
            });
            if (!ok || !data.success) {
                this.error = data.message || data.error || 'No se pudo enviar el mensaje. Recarga e inténtalo de nuevo.';
                return;
            }
            this.upsertThread(data.thread || { ...thread, messages: [...(thread.messages || []), data.message] });
            this.chatDraft = '';
            this.scrollChat();
        },
        upsertThread(thread) {
            if (!thread?.id) return;
            const idx = this.threads.findIndex(t => t.id === thread.id);
            if (idx >= 0) this.threads.splice(idx, 1, thread);
            else this.threads.unshift(thread);
            this.threads.sort((a, b) => String(b.last_message_at || '').localeCompare(String(a.last_message_at || '')));
        },
        async simulateIncoming() {},
        async suggestReplies() {
            const thread = this.selectedThread();
            if (!thread) return;
            const last = [...thread.messages].reverse().find(m => m.sender_role !== 'teacher');
            const { json: data } = await this.postJson(`/teacher/communication/threads/${thread.id}/quick-replies`, {
                incoming: last ? last.body : '',
            });
            if (data.success) this.quickReplies = data.suggestions || [];
        },
        applySuggestion(text) {
            this.chatDraft = text;
        },
        async pollThreads() {
            try {
                const res = await fetch('{{ route('teacher.communication.threads') }}', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (!data.success) return;
                const keepId = this.selectedThreadId;
                this.threads = data.threads || [];
                if (keepId && this.threads.some(t => t.id === keepId)) this.selectedThreadId = keepId;
                this.scrollChat();
            } catch (_) {}
        },
        init() {
            this.refreshCsrf();
            this.mobileList = !this.selectedThreadId;
            this.scrollChat();
            this.poller = setInterval(() => {
                if (this.tab === 'messages') this.pollThreads();
            }, 8000);
        },
    };
}
</script>
</body>
</html>

