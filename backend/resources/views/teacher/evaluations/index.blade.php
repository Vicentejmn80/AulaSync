<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Evaluaciones · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, Nunito, system-ui, sans-serif; background: radial-gradient(circle at top right, color-mix(in srgb, var(--nova-violet) 16%, transparent) 0%, transparent 36%), var(--bg-primary); color: var(--text-primary); margin: 0; }
        .wrap { max-width: 1220px; margin: 0 auto; padding: 28px 20px 80px; }
        .top { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 22px; flex-wrap: wrap; }
        .top a { color: var(--nova-violet); text-decoration: none; font-weight: 700; }
        h1 { margin: 0 0 6px; font-size: 30px; }
        .muted { color: var(--text-secondary); }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .tab { border: 1px solid var(--nova-glass-border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 8px 14px; cursor: pointer; font-weight: 700; }
        .tab.active { background: var(--nova-gradient); color: #fff; border-color: transparent; }
        .grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); box-shadow: var(--nova-shadow); border-radius: 20px; padding: 18px; backdrop-filter: blur(8px); }
        .stat b { display: block; font-size: 28px; }
        .btn { border: 0; border-radius: 999px; padding: 10px 16px; font-weight: 800; cursor: pointer; }
        .btn-ai { background: var(--nova-violet); color: #fff; }
        .btn-print { background: var(--nova-fuchsia); color: #fff; }
        .btn-ghost { background: transparent; color: var(--nova-violet); }
        .btn-soft { background: color-mix(in srgb, var(--nova-violet) 13%, var(--bg-secondary)); color: var(--nova-violet); }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        label { display: block; font-size: 12px; font-weight: 700; margin: 10px 0 6px; color: var(--text-secondary); }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--nova-glass-border); background: var(--bg-secondary); color: var(--text-primary); border-radius: 12px; padding: 10px 12px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .q { border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px; margin-bottom: 10px; }
        .list-item { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--nova-glass-border); }
        .pill { font-size: 11px; font-weight: 800; border-radius: 999px; padding: 4px 8px; background: color-mix(in srgb, var(--nova-violet) 12%, transparent); color: var(--nova-violet); }
        .ok { color: #159A79; font-weight: 700; }
        .err { color: #C2410C; }
        .spin { display: inline-block; animation: spin 1s linear infinite; }
        .section-title { margin: 0 0 8px; font-size: 20px; }
        .preview-sheet { background: #fff; border: 1px solid #D5D9E2; border-radius: 16px; padding: 22px; color: #122033; box-shadow: 0 14px 30px rgba(17, 24, 39, 0.08); }
        .preview-header { display: grid; grid-template-columns: 58px 1fr; gap: 12px; align-items: center; margin-bottom: 10px; }
        .preview-logo { width: 58px; height: 58px; border-radius: 14px; background: linear-gradient(135deg, #6C63FF, #FF6B9D); display: flex; align-items: center; justify-content: center; }
        .preview-logo img { width: 38px; height: 38px; object-fit: contain; }
        .preview-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 18px; font-size: 12px; margin: 10px 0 16px; }
        .preview-line { border-bottom: 1px solid #98A2B3; min-height: 20px; }
        .preview-question { margin: 14px 0; font-size: 13px; }
        .preview-write { border-bottom: 1px dashed #98A2B3; min-height: 22px; margin-top: 6px; }
        .info-note { font-size: 12px; color: var(--text-tertiary); margin-top: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 900px) { .grid4, .row2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
@include('partials.theme-switcher')
<div class="wrap" x-data="evaluationsApp()" x-cloak>
    <div class="top">
        <div>
            <a href="{{ route('teacher.hub') }}"><i class="fa-solid fa-arrow-left"></i> Volver al hub</a>
            <h1><i class="fa-solid fa-file-signature"></i> Evaluaciones</h1>
            <p class="muted">Crea evaluaciones con IA en modo digital o físico imprimible.</p>
        </div>
        <button class="btn btn-ai" @click="tab = 'create'"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear con IA</button>
    </div>

    <div class="tabs">
        <button class="tab" :class="{ active: tab === 'dash' }" @click="tab = 'dash'"><i class="fa-solid fa-chart-line"></i> Dashboard</button>
        <button class="tab" :class="{ active: tab === 'create' }" @click="tab = 'create'"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear</button>
        <button class="tab" :class="{ active: tab === 'list' }" @click="tab = 'list'"><i class="fa-solid fa-rectangle-list"></i> Mis evaluaciones</button>
        <button class="tab" :class="{ active: tab === 'pending' }" @click="tab = 'pending'"><i class="fa-solid fa-hourglass-half"></i> Pendientes</button>
        <button class="tab" :class="{ active: tab === 'bank' }" @click="tab = 'bank'"><i class="fa-solid fa-brain"></i> Banco</button>
    </div>

    <div x-show="tab === 'dash'" class="grid4">
        <div class="card stat"><span class="muted">Activas</span><b x-text="stats.active"></b></div>
        <div class="card stat"><span class="muted">Pendientes de calificar</span><b x-text="stats.pending"></b></div>
        <div class="card stat"><span class="muted">Próximas</span><b x-text="stats.upcoming.length"></b></div>
        <div class="card stat"><span class="muted">Promedio general</span><b x-text="stats.average ?? '—'"></b></div>
    </div>
    <div x-show="tab === 'dash'" class="card">
        <h3>Próximas evaluaciones</h3>
        <template x-if="stats.upcoming.length === 0"><p class="muted">No hay evaluaciones programadas.</p></template>
        <template x-for="item in stats.upcoming" :key="item.id">
            <div class="list-item">
                <div>
                    <strong x-text="item.title"></strong>
                    <div class="muted" x-text="item.scheduled_at || 'Sin fecha'"></div>
                </div>
                <span class="pill" x-text="item.status"></span>
            </div>
        </template>
    </div>

    <div x-show="tab === 'create'" class="card">
        <h3 class="section-title"><i class="fa-solid fa-sparkles"></i> Crear evaluación con IA</h3>
        <div class="row2">
            <div>
                <label>Modalidad</label>
                <select x-model="form.mode">
                    <option value="digital">Digital — estudiantes responden en la plataforma</option>
                    <option value="physical">Física — hoja imprimible</option>
                </select>
            </div>
            <div>
                <label>Curso / Grupo</label>
                <select x-model="form.course_id">
                    <option value="">Sin curso</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->subject_name }} · {{ $course->grade }}{{ $course->section ? ' / '.$course->section : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label>Describe la evaluación</label>
        <textarea rows="4" x-model="form.prompt" placeholder="Ej. Evaluación de Present Simple para inglés A1. 15 preguntas..."></textarea>
        <div class="row2">
            <div>
                <label>Tema / Unidad</label>
                <input x-model="form.topic" placeholder="Family Vocabulary">
            </div>
            <div>
                <label>Dificultad</label>
                <select x-model="form.difficulty">
                    <option value="basico">Básico</option>
                    <option value="intermedio">Intermedio</option>
                    <option value="avanzado">Avanzado</option>
                </select>
            </div>
            <div>
                <label>Tipo de preguntas</label>
                <select x-model="form.question_mix">
                    <option value="mixto">Mixto</option>
                    <option value="multiple_choice">Selección múltiple</option>
                    <option value="true_false">Verdadero / Falso</option>
                    <option value="completion">Completar</option>
                    <option value="open">Abiertas</option>
                </select>
            </div>
            <div>
                <label>Número de preguntas</label>
                <input type="number" min="3" max="40" x-model.number="form.question_count">
            </div>
        </div>
        <div class="row2" x-show="form.mode === 'physical'">
            <div>
                <label>Tamaño de hoja</label>
                <select x-model="form.paper_size">
                    <option value="A4">A4</option>
                    <option value="Letter">Carta</option>
                </select>
            </div>
            <div>
                <label>Orientación</label>
                <select x-model="form.orientation">
                    <option value="portrait">Vertical</option>
                    <option value="landscape">Horizontal</option>
                </select>
            </div>
        </div>
        <label><input type="checkbox" x-model="form.large_print" style="width:auto"> Letra grande (NEE)</label>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-ai" :disabled="loading" @click="generate()">
                <i class="fa-solid fa-wand-magic-sparkles" :class="{ spin: loading }"></i>
                <span x-text="loading ? 'Generando…' : 'Generar con IA'"></span>
            </button>
            <button class="btn btn-soft" type="button" x-show="form.mode === 'physical' && preview" @click="printDraftFromPreview()">
                <i class="fa-solid fa-print"></i> Borrador imprimible
            </button>
        </div>
        <p class="ok" x-show="message" x-text="message"></p>
        <p class="err" x-show="error" x-text="error"></p>

        <template x-if="preview">
            <div style="margin-top:18px;">
                <label>Título</label>
                <input x-model="preview.title">
                <label>Instrucciones</label>
                <textarea rows="3" x-model="preview.instructions"></textarea>
                <template x-for="(q, i) in preview.questions" :key="i">
                    <div class="q">
                        <div class="muted">Pregunta <span x-text="i+1"></span> · <span x-text="q.type"></span></div>
                        <textarea rows="2" x-model="q.text"></textarea>
                        <template x-if="q.options && q.options.length">
                            <div>
                                <template x-for="(opt, oi) in q.options" :key="oi">
                                    <input style="margin-top:6px" x-model="q.options[oi]">
                                </template>
                            </div>
                        </template>
                        <label>Respuesta correcta (clave del profesor)</label>
                        <input x-model="q.correct_answer">
                        <button class="btn btn-ghost" type="button" :disabled="questionLoadingIndex === i" @click="regeneratePreview(i)">
                            <i class="fa-solid fa-rotate" :class="{ spin: questionLoadingIndex === i }"></i>
                            <span x-text="questionLoadingIndex === i ? 'Mejorando...' : 'Mejorar pregunta con IA'"></span>
                        </button>
                    </div>
                </template>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-ai" :disabled="saving" @click="save('draft')"><span x-text="saving ? 'Guardando...' : 'Guardar borrador'"></span></button>
                    <button class="btn btn-ai" :disabled="saving" @click="save('published')">Publicar</button>
                    <button class="btn btn-print" :disabled="saving" x-show="form.mode === 'physical'" @click="save('draft', true)">Guardar e imprimir</button>
                </div>
                <p class="info-note" x-show="form.mode === 'digital'">
                    Al publicar, podrás compartir el enlace digital para que tus estudiantes respondan en línea.
                </p>
            </div>
        </template>

        <template x-if="preview && form.mode === 'physical'">
            <div style="margin-top:18px;">
                <h4 class="section-title"><i class="fa-solid fa-file-lines"></i> Vista previa de hoja física</h4>
                <div class="preview-sheet">
                    <div class="preview-header">
                        <div class="preview-logo">
                            <img src="/images/aulasync-mark.png" alt="AulaSync">
                        </div>
                        <div>
                            <strong x-text="schoolName"></strong><br>
                            <span style="font-size:12px;color:#526072;">Evaluación académica · Borrador imprimible</span>
                        </div>
                    </div>
                    <h3 style="margin:0 0 8px;" x-text="preview.title || 'Evaluación sin título'"></h3>
                    <div class="preview-meta">
                        <div>Curso: <span x-text="selectedCourseLabel()"></span></div>
                        <div>Fecha: <span class="preview-line"></span></div>
                        <div>Estudiante: <span class="preview-line"></span></div>
                        <div>Docente: <span x-text="teacherName"></span></div>
                    </div>
                    <p style="font-size:13px;" x-text="preview.instructions || 'Lee cuidadosamente cada pregunta y responde con orden y claridad.'"></p>
                    <template x-for="(q, i) in preview.questions.slice(0, 5)" :key="'sheet-'+i">
                        <div class="preview-question">
                            <strong x-text="`${i + 1}.`"></strong>
                            <span x-text="q.text"></span>
                            <template x-if="q.options && q.options.length">
                                <div style="margin-top:6px; padding-left:14px;">
                                    <template x-for="(opt, oi) in q.options" :key="'sheet-opt-'+oi">
                                        <div style="margin:3px 0;">&#9633; <span x-text="opt"></span></div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!q.options || q.options.length === 0">
                                <div>
                                    <div class="preview-write"></div>
                                    <div class="preview-write"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p class="info-note">Se muestran las primeras 5 preguntas de la vista previa. El PDF final incluirá toda la evaluación.</p>
                </div>
                <div style="margin-top:10px;">
                    <button class="btn btn-print" @click="printDraftFromPreview()"><i class="fa-solid fa-print"></i> Imprimir borrador ahora</button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="tab === 'list'" class="card">
        <h3 class="section-title"><i class="fa-solid fa-clipboard-check"></i> Mis evaluaciones</h3>
        <input placeholder="Buscar…" x-model="query" style="margin-bottom:12px">
        <template x-for="ev in filtered()" :key="ev.id">
            <div class="list-item">
                <div>
                    <strong x-text="ev.title"></strong>
                    <div class="muted">
                        <span x-text="ev.mode === 'physical' ? 'Física imprimible' : 'Digital online'"></span>
                        · <span x-text="ev.course?.subject_name || 'Sin curso'"></span>
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="pill" x-text="ev.status"></span>
                    <a class="btn btn-ghost" :href="`/teacher/evaluations/${ev.id}/print`" target="_blank">Imprimir</a>
                    <a class="btn btn-soft" x-show="ev.mode === 'digital' && ev.public_token" :href="`/e/${ev.public_token}`" target="_blank">Abrir examen</a>
                    <button class="btn btn-soft" @click="addToPlan(ev.id)"><i class="fa-solid fa-diagram-project"></i> Agregar al plan</button>
                    <button class="btn btn-ghost" @click="duplicate(ev.id)">Duplicar</button>
                    <button class="btn btn-ghost" @click="remove(ev.id)">Eliminar</button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="tab === 'pending'" class="card">
        <h3 class="section-title"><i class="fa-solid fa-list-check"></i> Pendientes de calificar</h3>
        <template x-if="pending.length === 0"><p class="muted">No hay respuestas pendientes.</p></template>
        <template x-for="p in pending" :key="p.id">
            <div class="list-item">
                <div>
                    <strong x-text="p.student_name || 'Estudiante'"></strong>
                    <div class="muted" x-text="p.evaluation?.title"></div>
                </div>
                <button class="btn btn-ai" @click="gradeAi(p.id)">Calificar con IA</button>
            </div>
        </template>
        <pre class="muted" x-show="aiGrade" x-text="JSON.stringify(aiGrade, null, 2)"></pre>
    </div>

    <div x-show="tab === 'bank'" class="card">
        <h3 class="section-title"><i class="fa-solid fa-layer-group"></i> Banco de preguntas</h3>
        <input placeholder="Buscar por tema, tipo o texto" x-model="bankQuery" style="margin-bottom:12px">
        <template x-for="q in filteredBank()" :key="q.id">
            <div class="q">
                <span class="pill" x-text="q.type"></span>
                <p x-text="q.text"></p>
                <div class="muted" x-text="q.topic || q.evaluation?.title"></div>
            </div>
        </template>
    </div>
</div>
<script>
function evaluationsApp() {
    return {
        tab: 'dash',
        loading: false,
        saving: false,
        questionLoadingIndex: null,
        message: '',
        error: '',
        query: '',
        bankQuery: '',
        aiGrade: null,
        schoolName: @json($teacher?->settings?->nombre_institucion ?? 'Institución educativa'),
        teacherName: @json($teacher?->name ?? 'Docente'),
        stats: @json($stats),
        evaluations: @json($evaluations),
        pending: @json($pendingAttempts),
        bank: @json($bank),
        courses: @json($courses),
        preview: null,
        form: {
            prompt: '',
            mode: 'digital',
            course_id: '',
            topic: '',
            difficulty: 'intermedio',
            question_mix: 'mixto',
            question_count: 10,
            large_print: false,
            paper_size: 'A4',
            orientation: 'portrait',
        },
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
        selectedCourseLabel() {
            const id = Number(this.form.course_id || 0);
            const found = this.courses.find(c => Number(c.id) === id);
            if (!found) return 'Sin curso asignado';
            return `${found.subject_name} · ${found.grade}${found.section ? ' / ' + found.section : ''}`;
        },
        filtered() {
            const q = this.query.toLowerCase();
            return this.evaluations.filter(e => !q || (e.title || '').toLowerCase().includes(q));
        },
        filteredBank() {
            const q = this.bankQuery.toLowerCase();
            return this.bank.filter(item => !q || `${item.text} ${item.type} ${item.topic || ''}`.toLowerCase().includes(q));
        },
        async generate() {
            this.loading = true; this.error = ''; this.message = '';
            try {
                const res = await fetch('{{ route('teacher.evaluations.generate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!data.success) { this.error = data.error || 'No se pudo generar.'; return; }
                this.preview = data.evaluation;
                this.message = data.message;
            } catch (e) { this.error = 'Error de red.'; }
            finally { this.loading = false; }
        },
        async save(status, goPrint = false) {
            if (!this.preview) return;
            this.error = '';
            this.saving = true;
            const payload = {
                ...this.form,
                title: this.preview.title || this.form.topic || 'Evaluación',
                instructions: this.preview.instructions,
                questions: this.preview.questions,
                rubric: this.preview.rubric || {},
                generated_by_ai: true,
                status,
                physical_format: { paper_size: this.form.paper_size, orientation: this.form.orientation, font_size: this.form.large_print ? 16 : 12, include_qr: true },
            };
            try {
                const res = await fetch('{{ route('teacher.evaluations.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!data.success) { this.error = data.error || 'No se pudo guardar.'; return; }
                this.evaluations.unshift(data.evaluation);
                this.message = 'Evaluación guardada con éxito.';
                this.tab = 'list';
                if (goPrint) window.open(`/teacher/evaluations/${data.evaluation.id}/print`, '_blank');
            } catch (e) {
                this.error = 'Error de red al guardar.';
            } finally {
                this.saving = false;
            }
        },
        async duplicate(id) {
            const res = await fetch(`/teacher/evaluations/${id}/duplicate`, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.success) this.evaluations.unshift(data.evaluation);
        },
        async remove(id) {
            if (!confirm('¿Eliminar esta evaluación?')) return;
            await fetch(`/teacher/evaluations/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' } });
            this.evaluations = this.evaluations.filter(e => e.id !== id);
        },
        async addToPlan(id) {
            this.error = '';
            this.message = '';
            try {
                const res = await fetch('{{ route('teacher.assessment.attach_evaluation') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({ evaluation_id: id, weight_percentage: 10, category: 'summative' }),
                });
                const data = await res.json();
                if (!data.success) { this.error = data.error || 'No se pudo agregar al plan.'; return; }
                this.message = data.message || 'Evaluación agregada al plan.';
            } catch (e) {
                this.error = 'Error de red al sincronizar con el plan.';
            }
        },
        async gradeAi(id) {
            const res = await fetch(`/teacher/evaluations/attempts/${id}/grade-ai`, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' } });
            const data = await res.json();
            this.aiGrade = data.feedback || data.error;
        },
        async regeneratePreview(i) {
            const instruction = prompt('¿Qué ajuste quieres aplicar a esta pregunta?');
            if (!instruction || !this.preview?.questions[i]) return;
            this.questionLoadingIndex = i;
            this.error = '';
            try {
                const res = await fetch('{{ route('teacher.evaluations.regenerate_draft') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify({
                        mode: this.form.mode,
                        topic: this.form.topic,
                        difficulty: this.form.difficulty,
                        question: this.preview.questions[i],
                        instruction,
                    }),
                });
                const data = await res.json();
                if (!data.success || !data.question) {
                    this.error = data.error || 'No se pudo mejorar esta pregunta.';
                    return;
                }
                const original = this.preview.questions[i];
                this.preview.questions[i] = {
                    ...original,
                    ...data.question,
                    points: Number(data.question.points || original.points || 1),
                };
                this.message = 'Pregunta mejorada con IA.';
            } catch (e) {
                this.error = 'Error de red al mejorar pregunta.';
            } finally {
                this.questionLoadingIndex = null;
            }
        },
        printDraftFromPreview() {
            if (!this.preview || this.form.mode !== 'physical') return;
            const safe = (v) => String(v || '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
            const questions = (this.preview.questions || []).map((q, idx) => {
                const options = Array.isArray(q.options) && q.options.length
                    ? `<div style="padding-left:14px; margin-top:6px;">${q.options.map(opt => `<div style="margin:3px 0;">&#9633; ${safe(opt)}</div>`).join('')}</div>`
                    : `<div><div style="border-bottom:1px dashed #98A2B3; min-height:20px; margin-top:6px;"></div><div style="border-bottom:1px dashed #98A2B3; min-height:20px; margin-top:6px;"></div></div>`;
                return `<div style="margin:14px 0; page-break-inside:avoid;"><strong>${idx + 1}.</strong> ${safe(q.text)} ${options}</div>`;
            }).join('');
            const html = `<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>${safe(this.preview.title || 'Evaluación')}</title><style>
                body{font-family:Inter,Arial,sans-serif;color:#122033;margin:0;background:#fff;}
                .sheet{max-width:850px;margin:14px auto;padding:26px 30px;}
                .head{display:grid;grid-template-columns:58px 1fr;gap:12px;align-items:center;}
                .logo{width:58px;height:58px;border-radius:14px;background:linear-gradient(135deg,#6C63FF,#FF6B9D);display:flex;align-items:center;justify-content:center;}
                .logo img{width:38px;height:38px;object-fit:contain;}
                .meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin:12px 0 16px;font-size:12px;}
                .line{border-bottom:1px solid #98A2B3;min-height:20px;}
                @media print{.actions{display:none;} @page{size:${this.form.paper_size} ${this.form.orientation};margin:12mm;} .sheet{max-width:none;margin:0;padding:0;}}
            </style></head><body><div class="sheet"><div class="actions" style="text-align:right;margin-bottom:10px;"><button onclick="window.print()">Imprimir</button></div>
                <div class="head"><div class="logo"><img src="/images/aulasync-mark.png" alt="logo"></div><div><strong>${safe(this.schoolName)}</strong><br><span style="font-size:12px;color:#526072;">Evaluación académica · Borrador</span></div></div>
                <h2 style="margin:12px 0 6px;">${safe(this.preview.title || 'Evaluación')}</h2>
                <div class="meta"><div>Curso: ${safe(this.selectedCourseLabel())}</div><div>Docente: ${safe(this.teacherName)}</div><div>Estudiante: <span class="line"></span></div><div>Fecha: <span class="line"></span></div></div>
                <p style="font-size:13px;">${safe(this.preview.instructions || 'Lee cuidadosamente cada pregunta y responde con orden y claridad.')}</p>
                ${questions}
            </div></body></html>`;
            const win = window.open('', '_blank');
            if (!win) {
                this.error = 'El navegador bloqueó la ventana emergente de impresión.';
                return;
            }
            win.document.open();
            win.document.write(html);
            win.document.close();
        },
    };
}
</script>
</body>
</html>
