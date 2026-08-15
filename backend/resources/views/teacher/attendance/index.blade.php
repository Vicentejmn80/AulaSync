<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asistencia · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 20px 80px; }
        .top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .title { margin: 0; font-size: 32px; font-weight: 900; }
        .muted { color: var(--text-secondary); margin: 3px 0 0; }
        .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); border-radius: 18px; padding: 16px; box-shadow: var(--nova-shadow); }
        .toolbar { display: grid; grid-template-columns: 1.4fr .8fr auto; gap: 10px; align-items: end; margin-bottom: 14px; }
        label { display: block; margin: 0 0 5px; font-size: 12px; font-weight: 700; color: var(--text-secondary); }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--nova-glass-border); border-radius: 10px; background: var(--bg-secondary); color: var(--text-primary); padding: 9px 10px; }
        textarea { min-height: 70px; }
        .btn { border: 0; border-radius: 999px; padding: 9px 14px; font-weight: 800; cursor: pointer; }
        .btn-main { background: var(--nova-violet); color: #fff; }
        .btn-soft { background: color-mix(in srgb, var(--nova-violet) 13%, var(--bg-secondary)); color: var(--nova-violet); }
        .btn-ok { background: #0F766E; color: #fff; }
        .btn-warn { background: #B45309; color: #fff; }
        .btn-alert { background: var(--nova-fuchsia); color: #fff; }
        .stack { display: flex; gap: 8px; flex-wrap: wrap; }
        .layout { display: grid; grid-template-columns: 1.7fr .9fr; gap: 14px; }
        .student { border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px; margin-bottom: 8px; background: var(--bg-secondary); }
        .student-head { display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; }
        .status-btns { display: flex; gap: 6px; flex-wrap: wrap; }
        .status-btn { border: 1px solid var(--nova-glass-border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 6px 10px; font-weight: 800; font-size: 12px; cursor: pointer; }
        .status-btn.on-present { background: #0F766E; color: #fff; border-color: transparent; }
        .status-btn.on-absent { background: var(--nova-fuchsia); color: #fff; border-color: transparent; }
        .status-btn.on-tardy { background: #B45309; color: #fff; border-color: transparent; }
        .pill { border-radius: 999px; padding: 4px 8px; font-size: 11px; font-weight: 800; background: color-mix(in srgb, var(--nova-violet) 14%, transparent); color: var(--nova-violet); }
        .family { background: color-mix(in srgb, #B45309 16%, transparent); color: #B45309; }
        .banner { border-radius: 12px; padding: 10px 12px; margin-bottom: 12px; font-weight: 700; font-size: 13px; }
        .offline { background: color-mix(in srgb, #B45309 18%, transparent); color: #B45309; }
        .ok { background: color-mix(in srgb, #0F766E 16%, transparent); color: #0F766E; }
        .name { font-weight: 800; }
        .drawer { position: fixed; inset: 0 0 0 auto; width: min(420px, 100vw); background: var(--bg-card); border-left: 1px solid var(--nova-glass-border); padding: 18px; overflow: auto; z-index: 40; box-shadow: var(--nova-shadow); }
        .backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 30; }
        .hist { border-bottom: 1px solid var(--nova-glass-border); padding: 8px 0; font-size: 13px; }
        .toggle { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; }
        @media (max-width: 980px) { .layout, .toolbar { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
@include('partials.theme-switcher')
<div class="wrap" x-data="attendanceApp()" x-cloak>
    <div class="top">
        <div>
            <a href="{{ route('teacher.hub') }}" style="text-decoration:none;color:var(--nova-violet);font-weight:700;"><i class="fa-solid fa-arrow-left"></i> Volver al hub</a>
            <h1 class="title">Asistencia</h1>
            <p class="muted">Marca el curso en segundos. El representante recibe alerta automática si hay ausencia o retraso.</p>
        </div>
        <label class="toggle">
            <input type="checkbox" x-model="quickMode">
            Modo rápido (nota y motivo al marcar)
        </label>
    </div>

    <div class="banner offline" x-show="!online">
        <i class="fa-solid fa-wifi"></i> Sin conexión. La lista se guarda en este dispositivo y se sincroniza al volver internet.
        <span x-show="queue.length"> · Pendientes: <span x-text="queue.length"></span></span>
    </div>
    <div class="banner ok" x-show="toast" x-text="toast"></div>

    <div class="card toolbar">
        <div>
            <label>Clase</label>
            <select x-model="courseId" @change="loadRoster()">
                <option value="">Selecciona un curso</option>
                <template x-for="c in courses" :key="c.id">
                    <option :value="String(c.id)" x-text="`${c.subject_name} · ${c.grade}${c.section ? ' / ' + c.section : ''} (${c.students_count})`"></option>
                </template>
            </select>
        </div>
        <div>
            <label>Fecha</label>
            <input type="date" x-model="date" @change="loadRoster()">
        </div>
        <div class="stack">
            <button class="btn btn-ok" @click="markAll('present')" :disabled="!roster.length">Todos presentes</button>
            <button class="btn btn-main" @click="save()" :disabled="!roster.length || saving">
                <span x-show="!saving">Guardar</span>
                <span x-show="saving">Guardando…</span>
            </button>
        </div>
    </div>

    <div class="layout">
        <div class="card">
            <div class="stack" style="margin-bottom:12px;">
                <button class="btn btn-ok" @click="markAll('present')" :disabled="!roster.length">Presentes</button>
                <button class="btn btn-alert" @click="markAll('absent')" :disabled="!roster.length">Ausentes</button>
                <button class="btn btn-warn" @click="markAll('tardy')" :disabled="!roster.length">Tardíos</button>
            </div>
            <p class="muted" x-show="!courseId">Elige un curso para ver la lista.</p>
            <p class="muted" x-show="courseId && loading">Cargando lista…</p>
            <p class="muted" x-show="courseId && !loading && roster.length === 0">Este curso no tiene estudiantes inscritos.</p>
            <template x-for="row in roster" :key="row.student_id">
                <div class="student">
                    <div class="student-head">
                        <div>
                            <div class="name" x-text="row.name"></div>
                            <div class="stack" style="margin-top:6px;">
                                <span class="pill family" x-show="row.family_request" x-text="row.family_request ? ('Familia: ' + row.family_request.label) : ''"></span>
                                <span class="pill" x-show="row.notified_at">Representante avisado</span>
                                <button class="btn btn-soft" style="padding:4px 10px;font-size:12px;" @click="openHistory(row)">Expediente</button>
                            </div>
                        </div>
                        <div class="status-btns">
                            <button class="status-btn" :class="{ 'on-present': row.status === 'present' }" @click="setStatus(row, 'present')">Presente</button>
                            <button class="status-btn" :class="{ 'on-absent': row.status === 'absent' }" @click="setStatus(row, 'absent')">Ausente</button>
                            <button class="status-btn" :class="{ 'on-tardy': row.status === 'tardy' }" @click="setStatus(row, 'tardy')">Tardío</button>
                        </div>
                    </div>
                    <div x-show="quickMode && expandedId === row.student_id && row.status !== 'present'" style="margin-top:10px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <div>
                                <label>Motivo</label>
                                <select x-model="row.reason_id">
                                    <option value="">Sin motivo</option>
                                    <template x-for="r in reasons" :key="r.id">
                                        <option :value="String(r.id)" x-text="r.label"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label>Nota</label>
                                <input x-model="row.note" maxlength="500" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="card">
            <h3 style="margin:0 0 10px;"><i class="fa-solid fa-bell"></i> Alertas enviadas</h3>
            <p class="muted" x-show="alerts.length === 0">Cuando marques una ausencia, AulaSync avisará al representante y el registro aparecerá aquí.</p>
            <template x-for="alert in alerts" :key="alert.id || alert.message">
                <div class="hist">
                    <strong x-text="alert.title || 'Notificación de ausencia enviada'"></strong>
                    <div class="muted" x-text="alert.message"></div>
                </div>
            </template>
        </div>
    </div>

    <div class="backdrop" x-show="historyOpen" @click="historyOpen = false"></div>
    <aside class="drawer" x-show="historyOpen">
        <div class="stack" style="justify-content:space-between;align-items:center;">
            <h3 style="margin:0;" x-text="historyName"></h3>
            <button class="btn btn-soft" @click="historyOpen = false">Cerrar</button>
        </div>
        <p class="muted">Historial de asistencia, reportes de la familia y comunicaciones.</p>
        <h4>Asistencias</h4>
        <template x-if="!history.history?.length"><p class="muted">Sin marcas todavía.</p></template>
        <template x-for="item in (history.history || [])" :key="item.date + item.course">
            <div class="hist">
                <strong x-text="item.date"></strong>
                <span class="pill" x-text="labelStatus(item.status)"></span>
                <div class="muted" x-text="[item.course, item.reason, item.note].filter(Boolean).join(' · ')"></div>
            </div>
        </template>
        <h4>Reportes de la familia</h4>
        <template x-if="!history.requests?.length"><p class="muted">Sin reportes.</p></template>
        <template x-for="item in (history.requests || [])" :key="item.range + item.status">
            <div class="hist">
                <strong x-text="item.range"></strong>
                <span class="pill" x-text="item.status"></span>
                <div class="muted" x-text="[item.parent, item.reason, item.comment].filter(Boolean).join(' · ')"></div>
            </div>
        </template>
        <h4>Comunicaciones</h4>
        <template x-if="!history.communications?.length"><p class="muted">Sin avisos registrados.</p></template>
        <template x-for="item in (history.communications || [])" :key="item.id">
            <div class="hist">
                <strong x-text="item.title"></strong>
                <div class="muted" x-text="item.message"></div>
            </div>
        </template>
    </aside>
</div>
<script>
function attendanceApp() {
    return {
        courses: @json($courses),
        reasons: @json($reasons),
        alerts: @json($alerts),
        courseId: @json(optional($courses->first())->id ? (string) $courses->first()->id : ''),
        date: @json(now()->toDateString()),
        roster: [],
        loading: false,
        saving: false,
        quickMode: true,
        expandedId: null,
        online: navigator.onLine,
        queue: JSON.parse(localStorage.getItem('aula-attendance-queue') || '[]'),
        toast: '',
        historyOpen: false,
        historyName: '',
        history: {},
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        uuid() {
            if (crypto.randomUUID) return crypto.randomUUID();
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },
        labelStatus(status) {
            return { present: 'Presente', absent: 'Ausente', tardy: 'Tardío' }[status] || status;
        },
        persistQueue() {
            localStorage.setItem('aula-attendance-queue', JSON.stringify(this.queue));
        },
        async init() {
            window.addEventListener('online', () => { this.online = true; this.flushQueue(); });
            window.addEventListener('offline', () => { this.online = false; });
            if (this.courseId) await this.loadRoster();
            if (this.online) await this.flushQueue();
        },
        async loadRoster() {
            if (!this.courseId) { this.roster = []; return; }
            this.loading = true;
            try {
                const url = new URL(@json(route('teacher.attendance.roster')), window.location.origin);
                url.searchParams.set('course_id', this.courseId);
                url.searchParams.set('date', this.date);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.roster = (data.roster || []).map(row => ({
                    ...row,
                    reason_id: row.reason_id ? String(row.reason_id) : '',
                    client_uuid: row.client_uuid || this.uuid(),
                    note: row.note || '',
                }));
            } catch (e) {
                this.toast = 'No se pudo cargar la lista. Revisa la conexión.';
            } finally {
                this.loading = false;
            }
        },
        setStatus(row, status) {
            row.status = status;
            if (!row.client_uuid) row.client_uuid = this.uuid();
            if (this.quickMode && status !== 'present') this.expandedId = row.student_id;
            else if (status === 'present') this.expandedId = null;
        },
        markAll(status) {
            this.roster.forEach(row => this.setStatus(row, status));
        },
        payload() {
            return {
                course_id: Number(this.courseId),
                date: this.date,
                entries: this.roster.map(row => ({
                    student_id: row.student_id,
                    status: row.status,
                    reason_id: row.reason_id || null,
                    note: row.note || null,
                    client_uuid: row.client_uuid,
                })),
            };
        },
        async save() {
            if (!this.roster.length) return;
            const body = this.payload();
            if (!this.online) {
                this.enqueue(body);
                this.toast = 'Guardado sin conexión. Se enviará al volver internet.';
                return;
            }
            this.saving = true;
            try {
                const res = await fetch(@json(route('teacher.attendance.save')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'No se pudo guardar.');
                this.applyAlerts(data.alerts || []);
                this.toast = data.message || 'Asistencia guardada.';
                await this.loadRoster();
            } catch (e) {
                this.enqueue(body);
                this.online = navigator.onLine;
                this.toast = 'No hubo conexión. La asistencia quedó en cola local.';
            } finally {
                this.saving = false;
            }
        },
        enqueue(body) {
            this.queue.push({ ...body, queued_at: new Date().toISOString() });
            this.persistQueue();
        },
        async flushQueue() {
            if (!this.queue.length || !navigator.onLine) return;
            const pending = [...this.queue];
            this.queue = [];
            this.persistQueue();
            for (const item of pending) {
                try {
                    const res = await fetch(@json(route('teacher.attendance.save')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                        body: JSON.stringify(item),
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error('sync');
                    this.applyAlerts(data.alerts || []);
                } catch (e) {
                    this.queue.push(item);
                    this.persistQueue();
                    this.toast = 'Quedaron marcas pendientes de sincronizar.';
                    return;
                }
            }
            if (pending.length) this.toast = 'Asistencia sin conexión sincronizada.';
            if (this.courseId) await this.loadRoster();
        },
        applyAlerts(items) {
            items.forEach(item => {
                const names = (item.parents || []).join(', ');
                this.alerts.unshift({
                    id: Date.now() + Math.random(),
                    title: 'Notificación de ausencia enviada',
                    message: names
                        ? `Notificación de ausencia enviada a ${names} (${item.student}).`
                        : `Se registró la ausencia de ${item.student}.`,
                });
            });
        },
        async openHistory(row) {
            this.historyOpen = true;
            this.historyName = row.name;
            this.history = {};
            const res = await fetch(`/teacher/attendance/students/${row.student_id}`, { headers: { 'Accept': 'application/json' } });
            this.history = await res.json();
        },
    };
}
</script>
</body>
</html>
