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
        body { font-family: Inter, Nunito, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); margin: 0; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 28px 20px 80px; }
        .top { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-bottom: 22px; flex-wrap: wrap; }
        .top a { color: var(--nova-violet); text-decoration: none; font-weight: 700; }
        h1 { margin: 0 0 6px; font-size: 30px; }
        .muted { color: var(--text-secondary); }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .tab { border: 1px solid var(--nova-glass-border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 8px 14px; cursor: pointer; font-weight: 700; }
        .tab.active { background: var(--nova-gradient); color: #fff; border-color: transparent; }
        .grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 22px; }
        .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); box-shadow: var(--nova-shadow); border-radius: 20px; padding: 18px; }
        .stat b { display: block; font-size: 28px; }
        .btn { border: 0; border-radius: 999px; padding: 10px 16px; font-weight: 800; cursor: pointer; }
        .btn-ai { background: var(--nova-violet); color: #fff; }
        .btn-print { background: var(--nova-fuchsia); color: #fff; }
        .btn-ghost { background: transparent; color: var(--nova-violet); }
        label { display: block; font-size: 12px; font-weight: 700; margin: 10px 0 6px; color: var(--text-secondary); }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--nova-glass-border); background: var(--bg-secondary); color: var(--text-primary); border-radius: 12px; padding: 10px 12px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .q { border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px; margin-bottom: 10px; }
        .list-item { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--nova-glass-border); }
        .pill { font-size: 11px; font-weight: 800; border-radius: 999px; padding: 4px 8px; background: color-mix(in srgb, var(--nova-violet) 12%, transparent); color: var(--nova-violet); }
        .ok { color: #159A79; font-weight: 700; }
        .err { color: #C2410C; }
        .spin { display: inline-block; animation: spin 1s linear infinite; }
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
            <h1>📝 Evaluaciones</h1>
            <p class="muted">Crea evaluaciones con IA en modo digital o físico imprimible.</p>
        </div>
        <button class="btn btn-ai" @click="tab = 'create'"><i class="fa-solid fa-wand-magic-sparkles"></i> Crear con IA</button>
    </div>

    <div class="tabs">
        <button class="tab" :class="{ active: tab === 'dash' }" @click="tab = 'dash'">📊 Dashboard</button>
        <button class="tab" :class="{ active: tab === 'create' }" @click="tab = 'create'">✨ Crear</button>
        <button class="tab" :class="{ active: tab === 'list' }" @click="tab = 'list'">📋 Mis evaluaciones</button>
        <button class="tab" :class="{ active: tab === 'pending' }" @click="tab = 'pending'">✅ Pendientes</button>
        <button class="tab" :class="{ active: tab === 'bank' }" @click="tab = 'bank'">🧠 Banco</button>
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
        <h3>➕ Crear evaluación con IA</h3>
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
        <label><input type="checkbox" x-model="form.large_print" style="width:auto"> Letra grande (NEE)</label>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-ai" :disabled="loading" @click="generate()">
                <i class="fa-solid fa-wand-magic-sparkles" :class="{ spin: loading }"></i>
                <span x-text="loading ? 'Generando…' : 'Generar con IA'"></span>
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
                        <button class="btn btn-ghost" type="button" @click="regeneratePreview(i)">Regenerar esta pregunta</button>
                    </div>
                </template>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-ai" @click="save('draft')">Guardar borrador</button>
                    <button class="btn btn-ai" @click="save('published')">Publicar</button>
                    <button class="btn btn-print" x-show="form.mode === 'physical'" @click="save('draft', true)">Guardar e imprimir</button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="tab === 'list'" class="card">
        <h3>📋 Mis evaluaciones</h3>
        <input placeholder="Buscar…" x-model="query" style="margin-bottom:12px">
        <template x-for="ev in filtered()" :key="ev.id">
            <div class="list-item">
                <div>
                    <strong x-text="ev.title"></strong>
                    <div class="muted">
                        <span x-text="ev.mode === 'physical' ? '🖨️ Física' : '💻 Digital'"></span>
                        · <span x-text="ev.course?.subject_name || 'Sin curso'"></span>
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span class="pill" x-text="ev.status"></span>
                    <a class="btn btn-ghost" :href="`/teacher/evaluations/${ev.id}/print`" target="_blank">Imprimir</a>
                    <button class="btn btn-ghost" @click="duplicate(ev.id)">Duplicar</button>
                    <button class="btn btn-ghost" @click="remove(ev.id)">Eliminar</button>
                </div>
            </div>
        </template>
    </div>

    <div x-show="tab === 'pending'" class="card">
        <h3>✅ Pendientes de calificar</h3>
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
        <h3>🧠 Banco de preguntas</h3>
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
        message: '',
        error: '',
        query: '',
        bankQuery: '',
        aiGrade: null,
        stats: @json($stats),
        evaluations: @json($evaluations),
        pending: @json($pendingAttempts),
        bank: @json($bank),
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
        },
        csrf() { return document.querySelector('meta[name="csrf-token"]').content; },
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
            const payload = {
                ...this.form,
                title: this.preview.title || this.form.topic || 'Evaluación',
                instructions: this.preview.instructions,
                questions: this.preview.questions,
                rubric: this.preview.rubric || {},
                generated_by_ai: true,
                status,
                physical_format: { paper_size: 'A4', orientation: 'portrait', font_size: this.form.large_print ? 16 : 12, include_qr: true },
            };
            const res = await fetch('{{ route('teacher.evaluations.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.success) { this.error = 'No se pudo guardar.'; return; }
            this.evaluations.unshift(data.evaluation);
            this.message = '✅ Evaluación guardada.';
            this.tab = 'list';
            if (goPrint) window.open(`/teacher/evaluations/${data.evaluation.id}/print`, '_blank');
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
        async gradeAi(id) {
            const res = await fetch(`/teacher/evaluations/attempts/${id}/grade-ai`, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' } });
            const data = await res.json();
            this.aiGrade = data.feedback || data.error;
        },
        regeneratePreview(i) {
            const instruction = prompt('¿Cómo quieres cambiar esta pregunta?');
            if (!instruction || !this.preview?.questions[i]) return;
            this.preview.questions[i].text = this.preview.questions[i].text + ' (ajustada: ' + instruction + ')';
            this.message = 'Pregunta marcada para ajuste. Vuelve a generar con IA o edítala a mano.';
        },
    };
}
</script>
</body>
</html>
