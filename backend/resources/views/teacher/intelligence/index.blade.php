<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inteligencia AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@11/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
    @include('partials.nova-theme')
    @include('partials.teacher-mobile')
    <style>
        [x-cloak] { display: none !important; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 24px 20px 80px; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .title { margin: 0; font-size: 32px; font-weight: 900; }
        .muted { color: var(--text-secondary); margin: 3px 0 0; font-size: 14px; }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .tab { border: 1px solid var(--nova-glass-border); border-radius: 999px; padding: 9px 14px; background: var(--bg-card); font-weight: 700; color: var(--text-secondary); cursor: pointer; }
        .tab.active { background: var(--nova-gradient); color: #fff; border-color: transparent; }
        .layout { display: grid; grid-template-columns: 1fr 1.25fr; gap: 14px; align-items: start; }
        .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); border-radius: 18px; padding: 16px; box-shadow: var(--nova-shadow); margin-bottom: 14px; }
        .card h3 { margin: 0 0 10px; font-size: 16px; }
        .pill { border-radius: 999px; padding: 3px 9px; font-size: 11px; font-weight: 800; background: color-mix(in srgb, var(--nova-violet) 14%, transparent); color: var(--nova-violet); white-space: nowrap; }
        .pill.ok { background: color-mix(in srgb, #0F766E 15%, transparent); color: #0F766E; }
        .pill.warn { background: color-mix(in srgb, #B45309 16%, transparent); color: #B45309; }
        .pill.err { background: color-mix(in srgb, #B91C1C 14%, transparent); color: #B91C1C; }
        .pill.dim { background: color-mix(in srgb, var(--text-secondary) 14%, transparent); color: var(--text-secondary); }
        .pill.soon { background: color-mix(in srgb, var(--nova-fuchsia) 14%, transparent); color: var(--nova-fuchsia); }
        .btn { border: 0; border-radius: 999px; padding: 9px 14px; font-weight: 800; cursor: pointer; font-size: 13px; }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .btn-main { background: var(--nova-violet); color: #fff; }
        .btn-soft { background: color-mix(in srgb, var(--nova-violet) 13%, var(--bg-secondary)); color: var(--nova-violet); }
        .btn-alert { background: var(--nova-fuchsia); color: #fff; }
        .btn-ghost { background: transparent; border: 1px solid var(--nova-glass-border); color: var(--text-secondary); }
        .btn-sm { padding: 6px 10px; font-size: 12px; }
        .stack { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        input, select, textarea { box-sizing: border-box; border: 1px solid var(--nova-glass-border); border-radius: 10px; background: var(--bg-secondary); color: var(--text-primary); padding: 8px 10px; font-family: inherit; font-size: 13px; }
        input[type="checkbox"] { width: auto; accent-color: var(--nova-violet); }
        label { display: block; margin: 8px 0 5px; font-size: 12px; font-weight: 700; color: var(--text-secondary); }
        .ok-text { color: #0F766E; font-weight: 700; }
        .warn-text { color: #B45309; font-weight: 700; }
        .err-text { color: #B91C1C; font-weight: 700; }
        .dropzone { border: 2px dashed var(--nova-glass-border); border-radius: 16px; padding: 28px 16px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; }
        .dropzone:hover, .dropzone.drag { border-color: var(--nova-violet); background: color-mix(in srgb, var(--nova-violet) 7%, transparent); }
        .dropzone i { font-size: 30px; color: var(--nova-violet); margin-bottom: 8px; }
        .doc-row { display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--nova-glass-border); padding: 10px 0; }
        .doc-row:last-child { border-bottom: 0; }
        .doc-name { font-weight: 700; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th, .table td { text-align: left; border-bottom: 1px solid var(--nova-glass-border); padding: 7px 6px; vertical-align: middle; }
        .table th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-secondary); }
        .review-section { margin-top: 14px; }
        .review-section > h4 { margin: 0 0 8px; font-size: 13px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 14px; }
        .stat { background: var(--bg-card); border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px; }
        .stat .v { font-size: 24px; font-weight: 900; }
        .stat .l { font-size: 11px; color: var(--text-secondary); font-weight: 700; }
        .dist-bar { display: flex; height: 10px; border-radius: 999px; overflow: hidden; background: var(--bg-secondary); margin: 6px 0; }
        .dist-bar > div { height: 100%; }
        .actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; }
        .action-btn { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px; background: var(--bg-card); cursor: pointer; font-weight: 700; font-size: 12.5px; color: var(--text-primary); text-align: left; }
        .action-btn:hover { border-color: var(--nova-violet); background: color-mix(in srgb, var(--nova-violet) 7%, transparent); }
        .action-btn i { color: var(--nova-violet); font-size: 16px; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0; }
        .chip { border: 1px solid var(--nova-glass-border); border-radius: 999px; padding: 6px 11px; font-size: 12px; cursor: pointer; background: var(--bg-secondary); color: var(--text-secondary); font-weight: 600; }
        .chip:hover { border-color: var(--nova-violet); color: var(--nova-violet); }
        .md-body { line-height: 1.55; font-size: 13.5px; overflow-wrap: anywhere; }
        .md-body table { border-collapse: collapse; margin: 8px 0; width: 100%; }
        .md-body th, .md-body td { border: 1px solid var(--nova-glass-border); padding: 5px 8px; font-size: 12.5px; text-align: left; }
        .md-body h1, .md-body h2, .md-body h3 { margin: 10px 0 6px; font-size: 15px; }
        .md-body code { background: var(--bg-secondary); border-radius: 5px; padding: 1px 5px; font-size: 12px; }
        .query-log { display: flex; flex-direction: column; gap: 10px; }
        .bubble { padding: 10px 12px; border-radius: 14px; max-width: 88%; }
        .bubble.user { margin-left: auto; background: color-mix(in srgb, var(--nova-violet) 16%, transparent); }
        .bubble.assistant { margin-right: auto; background: var(--bg-secondary); border: 1px solid var(--nova-glass-border); }
        .empty { text-align: center; color: var(--text-secondary); padding: 26px 10px; }
        .list-item { border-bottom: 1px solid var(--nova-glass-border); padding: 9px 0; font-size: 13px; }
        .list-item:last-child { border-bottom: 0; }
        .connectors { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
        .connector { display: flex; align-items: center; gap: 7px; border: 1px solid var(--nova-glass-border); border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; background: var(--bg-card); }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        .spinner.dark { border-color: color-mix(in srgb, var(--nova-violet) 25%, transparent); border-top-color: var(--nova-violet); }
        @keyframes spin { to { transform: rotate(360deg); } }
        .skel { background: linear-gradient(90deg, var(--bg-secondary) 25%, color-mix(in srgb, var(--nova-violet) 8%, var(--bg-secondary)) 50%, var(--bg-secondary) 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 10px; }
        @keyframes shimmer { to { background-position: -200% 0; } }
        @media (max-width: 1080px) { .layout { grid-template-columns: 1fr; } .stats-grid, .actions-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 767px) {
            .wrap { padding: 16px 16px calc(28px + env(safe-area-inset-bottom)); }
            .title { font-size: 24px; }
            .stats-grid, .actions-grid { grid-template-columns: 1fr 1fr; }
            .table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>
@include('partials.theme-switcher')
<div class="wrap" x-data="inteligenciaApp()" x-init="init()" x-cloak>
    <div class="top">
        <div>
            <a href="{{ route('teacher.hub') }}" style="text-decoration:none;color:var(--nova-violet);font-weight:700;"><i class="fa-solid fa-arrow-left"></i> Volver al hub</a>
            <h1 class="title"><i class="fa-solid fa-brain" style="color:var(--nova-violet);"></i> Inteligencia AulaSync</h1>
            <p class="muted">Sube tus documentos de siempre (planificaciones, listas, notas) y obtén una visión clara y accionable de tus estudiantes.</p>
        </div>
    </div>

    <div class="connectors">
        <template x-for="c in connectors" :key="c.key">
            <div class="connector">
                <i class="fa-solid" :class="c.key === 'local_upload' ? 'fa-file-arrow-up' : (c.key === 'google_classroom' ? 'fa-chalkboard' : 'fa-cloud')"></i>
                <span x-text="c.label"></span>
                <span class="pill soon" x-show="c.coming_soon">Próximamente</span>
            </div>
        </template>
    </div>

    <div class="tabs">
        <button class="tab" :class="{ active: tab === 'import' }" @click="tab = 'import'"><i class="fa-solid fa-file-import"></i> Importar documentos</button>
        <button class="tab" :class="{ active: tab === 'panel' }" @click="openPanel()"><i class="fa-solid fa-chart-pie"></i> Panel de inteligencia</button>
        <button class="tab" :class="{ active: tab === 'query' }" @click="tab = 'query'"><i class="fa-solid fa-comments"></i> Consulta</button>
    </div>

    {{-- ==================== TAB IMPORTAR ==================== --}}
    <div x-show="tab === 'import'" class="layout">
        <div>
            <div class="card">
                <h3><i class="fa-solid fa-cloud-arrow-up"></i> Subir documento</h3>
                <template x-if="!aiAvailable">
                    <p class="warn-text" style="font-size:13px;"><i class="fa-solid fa-triangle-exclamation"></i> La extracción inteligente está desactivada. Configura OPENAI_API_KEY para analizar documentos.</p>
                </template>
                <div class="dropzone" :class="{ drag: dragging }"
                     @click="$refs.fileInput.click()"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; handleFiles($event.dataTransfer.files)">
                    <i class="fa-solid fa-file-arrow-up"></i>
                    <div style="font-weight:800;" x-text="uploading ? 'Analizando documento…' : 'Arrastra tu archivo aquí o haz clic para elegirlo'"></div>
                    <div class="muted" style="margin-top:4px;font-size:12px;">PDF · Word (.docx) · Excel (.xlsx) · CSV · Fotos (.jpg/.png) — máx. 12 MB</div>
                </div>
                <input type="file" x-ref="fileInput" class="hidden" style="display:none;"
                       accept=".pdf,.docx,.xlsx,.csv,.txt,.tsv,.jpg,.jpeg,.png,.webp"
                       @change="handleFiles($event.target.files); $event.target.value = ''">
                <p class="muted" style="font-size:12px;margin-top:10px;">
                    La IA identifica automáticamente qué contiene (alumnos, notas, asistencia, planificación o evaluaciones) y te muestra qué detectó antes de aplicar nada.
                </p>
                <template x-if="uploadError"><p class="err-text" style="font-size:13px;" x-text="uploadError"></p></template>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-folder-open"></i> Documentos</h3>
                <template x-if="loadingDocs"><div class="skel" style="height:54px;margin-bottom:10px;"></div></template>
                <template x-if="!loadingDocs && documents.length === 0">
                    <div class="empty"><i class="fa-solid fa-inbox" style="font-size:22px;"></i><br>Aún no has subido documentos.</div>
                </template>
                <template x-for="doc in documents" :key="doc.id">
                    <div class="doc-row">
                        <div style="flex:1;min-width:0;">
                            <div class="doc-name" x-text="doc.original_name" :title="doc.original_name"></div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
                                <span class="pill dim" x-text="doc.kind_label"></span>
                                <span class="pill" :class="statusClass(doc.status)" x-text="statusLabel(doc.status)"></span>
                                <span class="pill dim" x-show="doc.confidence !== null" x-text="'confianza ' + Math.round((doc.confidence || 0) * 100) + '%'"></span>
                            </div>
                            <div class="muted" style="font-size:11px;margin-top:3px;" x-text="doc.created_at + (doc.applied_at ? ' · aplicado ' + doc.applied_at : '')"></div>
                            <div class="err-text" style="font-size:11.5px;margin-top:3px;" x-show="doc.error" x-text="doc.error"></div>
                        </div>
                        <div class="stack" style="margin:0;">
                            <button class="btn btn-soft btn-sm" x-show="doc.status === 'extracted' || doc.status === 'applied'" @click="openReview(doc)"><i class="fa-solid fa-eye"></i> Revisar</button>
                            <button class="btn btn-ghost btn-sm" @click="deleteDocument(doc)" :disabled="deleting === doc.id"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="card" x-show="review" x-cloak>
            <template x-if="review">
                <div>
                    <h3><i class="fa-solid fa-magnifying-glass-chart"></i> Información detectada</h3>

                    <div class="list-item" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <strong x-text="review.document_type ? typeLabels[review.document_type] || review.document_type : 'Documento'"></strong>
                        <span class="pill dim" x-show="review.confidence !== null" x-text="'confianza ' + Math.round((review.confidence || 0) * 100) + '%'"></span>
                    </div>

                    <label>Curso donde aplicar</label>
                    <select x-model="reviewForm.course_id" style="width:100%;">
                        <option value="">Selecciona un curso…</option>
                        <template x-for="c in review.course_options" :key="c.id">
                            <option :value="c.id" x-text="c.label"></option>
                        </template>
                    </select>

                    <template x-if="review.warnings && review.warnings.length">
                        <div style="margin-top:10px;">
                            <template x-for="(w, i) in review.warnings" :key="'w' + i">
                                <p class="warn-text" style="font-size:12.5px;margin:4px 0;"><i class="fa-solid fa-triangle-exclamation"></i> <span x-text="w"></span></p>
                            </template>
                        </div>
                    </template>

                    {{-- ALUMNOS --}}
                    <div class="review-section" x-show="review.students.length">
                        <h4>👩‍🎓 Alumnos detectados <span class="pill dim" x-text="review.students.length"></span></h4>
                        <p class="warn-text" style="font-size:12.5px;margin:0 0 8px;">No se incorporan a la nómina. Revísalos y envíalos al director.</p>
                        <table class="table">
                            <thead><tr><th style="width:30px;"></th><th>Nombre</th><th>Estado</th><th style="width:170px;">Coincidencia</th></tr></thead>
                            <tbody>
                                <template x-for="(s, i) in review.students" :key="'s' + i">
                                    <tr>
                                        <td><input type="checkbox" disabled></td>
                                        <td><strong x-text="s.name"></strong></td>
                                        <td>
                                            <span class="pill ok" x-show="s.status === 'existing'">Existe · no se matricula</span>
                                            <span class="pill warn" x-show="s.status === 'ambiguous'">Ambiguo</span>
                                            <span class="pill dim" x-show="s.status === 'new'">Nuevo · requiere director</span>
                                        </td>
                                        <td>
                                            <select x-show="s.status === 'ambiguous'" x-model="reviewForm.student_choices[i]" style="width:100%;padding:5px 6px;">
                                                <option value="">Elige…</option>
                                                <template x-for="c in s.candidates" :key="c.id">
                                                    <option :value="c.id" x-text="c.name + (c.code ? ' (' + c.code + ')' : '')"></option>
                                                </template>
                                            </select>
                                            <span class="muted" style="font-size:11.5px;" x-show="s.status === 'existing'" x-text="'ID ' + s.student_id"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- ACTIVIDADES --}}
                    <div class="review-section" x-show="review.activities.length">
                        <h4>📝 Actividades / planificación <span class="pill dim" x-text="review.activities.length"></span></h4>
                        <table class="table">
                            <thead><tr><th style="width:30px;"></th><th>Título</th><th>Fecha</th><th>Tipo</th><th>Estado</th></tr></thead>
                            <tbody>
                                <template x-for="(a, i) in review.activities" :key="'a' + i">
                                    <tr>
                                        <td><input type="checkbox" :value="i" x-model="reviewForm.activities" :disabled="!!a.duplicate_of"></td>
                                        <td><strong x-text="a.title"></strong></td>
                                        <td x-text="a.date || '—'"></td>
                                        <td x-text="typeLabels[a.type] || a.type"></td>
                                        <td>
                                            <span class="pill ok" x-show="!a.duplicate_of">Nueva</span>
                                            <span class="pill warn" x-show="a.duplicate_of">Ya existe</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- NOTAS --}}
                    <div class="review-section" x-show="review.grades.length">
                        <h4>📊 Calificaciones <span class="pill dim" x-text="review.grades.length"></span></h4>
                        <table class="table">
                            <thead><tr><th style="width:30px;"></th><th>Alumno</th><th>Actividad</th><th>Nota</th></tr></thead>
                            <tbody>
                                <template x-for="(g, i) in review.grades" :key="'g' + i">
                                    <tr>
                                        <td><input type="checkbox" :value="i" x-model="reviewForm.grades"></td>
                                        <td><strong x-text="g.student"></strong></td>
                                        <td x-text="g.activity_title"></td>
                                        <td x-text="g.score + (g.max_score ? ' / ' + g.max_score : '')"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- ASISTENCIA --}}
                    <div class="review-section" x-show="review.attendance.length">
                        <h4>🗓️ Asistencia <span class="pill dim" x-text="review.attendance.length"></span></h4>
                        <table class="table">
                            <thead><tr><th style="width:30px;"></th><th>Alumno</th><th>Fecha</th><th>Estado</th></tr></thead>
                            <tbody>
                                <template x-for="(r, i) in review.attendance" :key="'at' + i">
                                    <tr>
                                        <td><input type="checkbox" :value="i" x-model="reviewForm.attendance"></td>
                                        <td><strong x-text="r.student"></strong></td>
                                        <td x-text="r.date"></td>
                                        <td>
                                            <span class="pill ok" x-show="r.status === 'present'">Presente</span>
                                            <span class="pill err" x-show="r.status === 'absent'">Ausente</span>
                                            <span class="pill warn" x-show="r.status === 'tardy'">Tardanza</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- OBSERVACIONES / INCERTIDUMBRE --}}
                    <div class="review-section" x-show="review.observations && review.observations.length">
                        <h4>💬 Información relevante detectada</h4>
                        <template x-for="(o, i) in review.observations" :key="'o' + i">
                            <div class="list-item"><i class="fa-solid fa-quote-left muted"></i> <span x-text="o"></span></div>
                        </template>
                    </div>

                    <div class="review-section" x-show="review.uncertain && review.uncertain.length">
                        <h4>❓ No pude determinar con certeza</h4>
                        <template x-for="(u, i) in review.uncertain" :key="'u' + i">
                            <div class="list-item muted"><i class="fa-solid fa-circle-question"></i> <span x-text="u"></span></div>
                        </template>
                    </div>

                    <div class="stack" style="margin-top:16px;">
                        <button class="btn btn-main" @click="applyDocument()" :disabled="applying || forwarding">
                            <span x-show="!applying"><i class="fa-solid fa-check-double"></i> Aplicar a mi curso</span>
                            <span x-show="applying"><span class="spinner"></span> Aplicando…</span>
                        </button>
                        <button class="btn btn-soft" x-show="review.students.length" @click="forwardDocument()" :disabled="applying || forwarding">
                            <span x-show="!forwarding"><i class="fa-solid fa-paper-plane"></i> Enviar al director</span>
                            <span x-show="forwarding"><span class="spinner"></span> Enviando…</span>
                        </button>
                        <button class="btn btn-ghost" @click="review = null"><i class="fa-solid fa-xmark"></i> Cerrar</button>
                    </div>
                    <template x-if="applyMessage">
                        <p :class="applySuccess ? 'ok-text' : 'err-text'" style="font-size:13px;" x-text="applyMessage"></p>
                    </template>
                </div>
            </template>
        </div>

        <div class="card" x-show="!review">
            <div class="empty" style="padding:60px 16px;">
                <i class="fa-solid fa-wand-magic-sparkles" style="font-size:34px;color:var(--nova-violet);"></i>
                <h3 style="margin:10px 0 6px;">¿Qué contiene tus documentos?</h3>
                <p class="muted" style="max-width:420px;margin:0 auto;">
                    Sube tu planificación en Excel, tu lista de alumnos o tus notas y la IA extraerá automáticamente:
                    alumnos, cursos, calificaciones, evaluaciones, asistencia, observaciones y fechas.
                    Después decides qué aplicar a tu calendario.
                </p>
            </div>
        </div>
    </div>

    {{-- ==================== TAB PANEL ==================== --}}
    <div x-show="tab === 'panel'" x-cloak>
        <div class="card">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <h3 style="margin:0;"><i class="fa-solid fa-chart-pie"></i> Panel de inteligencia</h3>
                <select x-model="panelCourseId" @change="loadDashboard()" style="min-width:220px;">
                    <option value="">Todos mis cursos</option>
                    <template x-for="c in courses" :key="c.id">
                        <option :value="c.id" x-text="c.label"></option>
                    </template>
                </select>
                <button class="btn btn-soft btn-sm" @click="loadDashboard()" :disabled="loadingDashboard"><i class="fa-solid fa-rotate"></i> Actualizar</button>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-bolt"></i> Acciones</h3>
            <div class="actions-grid">
                <button class="action-btn" @click="runAction('analyze_group')"><i class="fa-solid fa-users-viewfinder"></i> Analizar grupo</button>
                <button class="action-btn" @click="startStudentAnalysis()"><i class="fa-solid fa-user-graduate"></i> Analizar estudiante</button>
                <button class="action-btn" @click="runAction('detect_attention')"><i class="fa-solid fa-triangle-exclamation"></i> Detectar quienes requieren atención</button>
                <button class="action-btn" @click="runAction('generate_planning', 4)"><i class="fa-solid fa-calendar-plus"></i> Generar planificación</button>
                <button class="action-btn" @click="runAction('generate_activities', 3)"><i class="fa-solid fa-list-check"></i> Generar actividades</button>
                <button class="action-btn" @click="runAction('generate_tasks', 2)"><i class="fa-solid fa-house-signal"></i> Generar tareas</button>
                <button class="action-btn" @click="runAction('generate_report')"><i class="fa-solid fa-file-lines"></i> Generar informe</button>
            </div>
            <template x-if="askingStudent">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                    <input x-model="studentName" placeholder="Nombre del alumno (ej: Ana Ruiz)" @keydown.enter="runAction('analyze_student')" style="flex:1;min-width:200px;">
                    <button class="btn btn-main btn-sm" @click="runAction('analyze_student')">Analizar</button>
                    <button class="btn btn-ghost btn-sm" @click="askingStudent = false">Cancelar</button>
                </div>
            </template>
            <template x-if="actionMessage">
                <p class="err-text" style="font-size:13px;" x-text="actionMessage"></p>
            </template>
        </div>

        <template x-if="loadingDashboard">
            <div class="card"><div class="skel" style="height:120px;"></div></div>
        </template>

        <template x-if="!loadingDashboard && summary">
            <div>
                <template x-if="summary.message && !summary.has_data">
                    <div class="card"><div class="empty" x-text="summary.message"></div></div>
                </template>

                <template x-if="summary.has_data">
                    <div>
                        <div class="stats-grid">
                            <div class="stat">
                                <div class="v" x-text="(summary.performance.avg_pct ?? '—') + '%'"></div>
                                <div class="l">Promedio general</div>
                            </div>
                            <div class="stat">
                                <div class="v" x-text="summary.performance.graded_students"></div>
                                <div class="l">Alumnos evaluados</div>
                            </div>
                            <div class="stat">
                                <div class="v" x-text="(summary.attendance.rate ?? '—') + '%'"></div>
                                <div class="l">Asistencia (30 días)</div>
                            </div>
                            <div class="stat">
                                <div class="v" x-text="summary.attention.length"></div>
                                <div class="l">Requieren atención</div>
                            </div>
                        </div>

                        <div class="layout">
                            <div>
                                <div class="card">
                                    <h3><i class="fa-solid fa-chart-simple"></i> Rendimiento — <span x-text="summary.label"></span></h3>
                                    <div class="dist-bar">
                                        <div style="background:#0F766E;width:0;" :style="{ width: distPct('high') + '%' }"></div>
                                        <div style="background:#B45309;width:0;" :style="{ width: distPct('mid') + '%' }"></div>
                                        <div style="background:#B91C1C;width:0;" :style="{ width: distPct('low') + '%' }"></div>
                                    </div>
                                    <div style="display:flex;gap:12px;font-size:12px;font-weight:700;flex-wrap:wrap;">
                                        <span class="ok-text"><i class="fa-solid fa-circle" style="font-size:8px;"></i> Alto (≥70%): <span x-text="summary.performance.distribution.high"></span></span>
                                        <span class="warn-text"><i class="fa-solid fa-circle" style="font-size:8px;"></i> Desarrollo (50–69%): <span x-text="summary.performance.distribution.mid"></span></span>
                                        <span class="err-text"><i class="fa-solid fa-circle" style="font-size:8px;"></i> Apoyo (<50%): <span x-text="summary.performance.distribution.low"></span></span>
                                    </div>
                                    <template x-if="summary.performance.top.length">
                                        <div style="margin-top:12px;">
                                            <h4 style="margin:0 0 6px;font-size:13px;">🏆 Destacados</h4>
                                            <template x-for="(t, i) in summary.performance.top" :key="'top' + i">
                                                <div class="list-item" style="display:flex;justify-content:space-between;">
                                                    <span x-text="t.name"></span>
                                                    <strong x-text="t.avg_pct + '%'"></strong>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="summary.trend.length">
                                        <div style="margin-top:12px;">
                                            <h4 style="margin:0 0 6px;font-size:13px;">📈 Tendencia semanal</h4>
                                            <div style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">
                                                <template x-for="(w, i) in summary.trend" :key="'tr' + i">
                                                    <div style="text-align:center;">
                                                        <div :style="{ height: Math.max(6, w.avg_pct / 2) + 'px', width: '34px', background: 'var(--nova-gradient)', borderRadius: '6px 6px 0 0' }" :title="'Semana ' + w.week"></div>
                                                        <div class="muted" style="font-size:10px;" x-text="w.avg_pct + '%'"></div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="card" x-show="summary.attention.length">
                                    <h3><i class="fa-solid fa-triangle-exclamation" style="color:#B45309;"></i> Requieren atención</h3>
                                    <template x-for="(s, i) in summary.attention" :key="'att' + i">
                                        <div class="list-item">
                                            <div style="display:flex;justify-content:space-between;gap:8px;">
                                                <strong x-text="s.name"></strong>
                                                <span class="muted" style="font-size:12px;" x-text="s.avg_pct !== null ? s.avg_pct + '%' : 'sin notas'"></span>
                                            </div>
                                            <div class="muted" style="font-size:12px;" x-text="s.reasons.join('; ')"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div>
                                <div class="card" x-show="summary.difficulty.length">
                                    <h3><i class="fa-solid fa-chart-line" style="color:#B91C1C;"></i> Áreas con dificultades</h3>
                                    <template x-for="(d, i) in summary.difficulty" :key="'dif' + i">
                                        <div class="list-item">
                                            <div style="display:flex;justify-content:space-between;gap:8px;">
                                                <strong x-text="d.title"></strong>
                                                <span class="err-text" style="font-size:12px;" x-text="d.avg_pct + '%'"></span>
                                            </div>
                                            <div class="muted" style="font-size:12px;" x-text="d.subject + ' · ' + d.graded + ' notas'"></div>
                                        </div>
                                    </template>
                                </div>

                                <div class="card" x-show="summary.detected.length">
                                    <h3><i class="fa-solid fa-lightbulb"></i> Información detectada</h3>
                                    <template x-for="(d, i) in summary.detected" :key="'det' + i">
                                        <div class="list-item"><i class="fa-solid fa-quote-left muted"></i> <span x-text="d"></span></div>
                                    </template>
                                </div>

                                <div class="card" x-show="summary.upcoming.length">
                                    <h3><i class="fa-solid fa-calendar-day"></i> Próximas actividades</h3>
                                    <template x-for="(u, i) in summary.upcoming" :key="'up' + i">
                                        <div class="list-item" style="display:flex;justify-content:space-between;gap:8px;">
                                            <span x-text="u.title"></span>
                                            <span class="muted" style="font-size:12px;" x-text="(u.date || '—') + ' · ' + (typeLabels[u.type] || u.type)"></span>
                                        </div>
                                    </template>
                                </div>

                                <div class="card" x-show="summary.recommendations.length">
                                    <h3><i class="fa-solid fa-graduation-cap" style="color:var(--nova-violet);"></i> Recomendaciones pedagógicas</h3>
                                    <template x-for="(r, i) in summary.recommendations" :key="'rec' + i">
                                        <div class="list-item"><i class="fa-solid fa-check" style="color:var(--nova-violet);"></i> <span x-text="r"></span></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- PROPUESTA PENDIENTE --}}
        <template x-if="proposal">
            <div class="card" style="border-color: var(--nova-violet);">
                <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Propuesta — <span x-text="proposal.course_label"></span></h3>
                <table class="table">
                    <thead><tr><th style="width:30px;"></th><th>Título</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <template x-for="(item, i) in proposal.items" :key="'pr' + i">
                            <tr>
                                <td><input type="checkbox" :value="i" x-model="proposalSelected"></td>
                                <td>
                                    <strong x-text="item.title"></strong>
                                    <div class="muted" style="font-size:12px;" x-text="item.description"></div>
                                </td>
                                <td><input type="date" :value="proposal.dates[i] || ''" @change="proposal.dates[i] = $event.target.value" style="width:150px;padding:5px 6px;"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div class="stack">
                    <button class="btn btn-main" @click="applyProposal()" :disabled="applyingProposal">
                        <span x-show="!applyingProposal"><i class="fa-solid fa-calendar-plus"></i> Agregar al calendario</span>
                        <span x-show="applyingProposal"><span class="spinner"></span> Aplicando…</span>
                    </button>
                    <button class="btn btn-ghost" @click="proposal = null">Descartar</button>
                </div>
                <template x-if="proposalMessage"><p class="ok-text" style="font-size:13px;" x-text="proposalMessage"></p></template>
            </div>
        </template>

        {{-- INFORME --}}
        <template x-if="report">
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h3 style="margin:0;"><i class="fa-solid fa-file-lines"></i> Informe del grupo</h3>
                    <div class="stack" style="margin:0;">
                        <button class="btn btn-soft btn-sm" @click="printReport()"><i class="fa-solid fa-print"></i> Imprimir / PDF</button>
                        <button class="btn btn-ghost btn-sm" @click="report = null">Cerrar</button>
                    </div>
                </div>
                <div class="md-body" x-html="md(report)"></div>
            </div>
        </template>
    </div>

    {{-- ==================== TAB CONSULTA ==================== --}}
    <div x-show="tab === 'query'" x-cloak class="layout" style="grid-template-columns: 1fr;">
        <div class="card">
            <h3><i class="fa-solid fa-comments"></i> Consulta tus datos</h3>
            <p class="muted" style="font-size:12.5px;">Pregunto directamente a tus datos reales de AulaSync. Si algo no está registrado, te lo diré con honestidad.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <select x-model="queryCourseId" style="max-width:220px;">
                    <option value="">Todos mis cursos</option>
                    <template x-for="c in courses" :key="'qc' + c.id">
                        <option :value="c.id" x-text="c.label"></option>
                    </template>
                </select>
            </div>
            <div class="chips">
                <button class="chip" @click="ask('¿Cómo está mi curso?')">¿Cómo está 4to A?</button>
                <button class="chip" @click="ask('¿Qué estudiantes necesitan atención?')">¿Quiénes necesitan atención?</button>
                <button class="chip" @click="ask('¿Quién tiene mejor rendimiento?')">¿Quién tiene mejor rendimiento?</button>
                <button class="chip" @click="ask('¿Qué área presenta más dificultades?')">¿Qué área presenta más dificultades?</button>
                <button class="chip" @click="ask('¿Cómo va la asistencia?')">¿Cómo va la asistencia?</button>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <input x-model="queryText" placeholder="Escribe tu pregunta sobre tus estudiantes…" style="flex:1;min-width:220px;" @keydown.enter="ask()">
                <button class="btn btn-main" @click="ask()" :disabled="asking || !queryText.trim()">
                    <span x-show="!asking"><i class="fa-solid fa-paper-plane"></i> Preguntar</span>
                    <span x-show="asking"><span class="spinner"></span></span>
                </button>
            </div>

            <div class="query-log" style="margin-top:14px;" x-show="conversation.length">
                <template x-if="conversation.length === 0"><div></div></template>
                <template x-for="(m, i) in conversation" :key="'m' + i">
                    <div class="bubble" :class="m.role">
                        <div class="md-body" x-html="md(m.text)"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function inteligenciaApp() {
    return {
        tab: 'import',
        courses: @json($courses),
        connectors: @json($connectors),
        aiAvailable: @json($aiAvailable),
        typeLabels: { clase: 'Clase', actividad: 'Actividad', tarea: 'Tarea', planificacion: 'Planificación', lista_alumnos: 'Lista de alumnos', notas: 'Notas', asistencia: 'Asistencia', evaluacion: 'Evaluación', informe: 'Informe', otro: 'Otro' },

        // Import
        dragging: false, uploading: false, uploadError: '',
        documents: [], loadingDocs: false, deleting: null,
        review: null,
        reviewForm: { course_id: '', students: [], student_choices: {}, activities: [], grades: [], attendance: [] },
        applying: false, forwarding: false, applyMessage: '', applySuccess: false,

        // Panel
        panelCourseId: '', summary: null, loadingDashboard: false,
        actionMessage: '', askingStudent: false, studentName: '',
        proposal: null, proposalSelected: [], applyingProposal: false, proposalMessage: '',
        report: null,

        // Query
        queryText: '', queryCourseId: '', asking: false, conversation: [],

        init() {
            this.loadDocuments();
            this.loadDashboard();
        },

        csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },

        async fetchJson(url, options = {}) {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf(), ...(options.headers || {}) },
                ...options,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok && data.message && !data.errors) throw new Error(data.message);
            if (!res.ok && data.errors) throw new Error(Object.values(data.errors)[0]);
            return data;
        },

        md(text) {
            try {
                const html = marked.parse(String(text || ''));
                return DOMPurify.sanitize(html);
            } catch (e) {
                return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
            }
        },

        // ─── IMPORT ───────────────────────────────────────
        async handleFiles(fileList) {
            const files = Array.from(fileList || []);
            if (files.length === 0) return;
            this.uploadError = '';
            this.uploading = true;
            for (const file of files) {
                try {
                    const form = new FormData();
                    form.append('file', file);
                    const data = await this.fetchJson('{{ route('intelligence.documents.store') }}', { method: 'POST', body: form });
                    if (data.review) {
                        this.openReviewPayload(data.document, data.review);
                    }
                } catch (e) {
                    this.uploadError = e.message || 'No se pudo subir el archivo.';
                }
            }
            this.uploading = false;
            await this.loadDocuments();
        },

        async loadDocuments() {
            this.loadingDocs = true;
            try {
                const data = await this.fetchJson('{{ route('intelligence.documents') }}');
                this.documents = data.documents || [];
            } catch (e) { /* silencioso */ }
            this.loadingDocs = false;
        },

        async openReview(doc) {
            try {
                const data = await this.fetchJson('{{ url('intelligence/documents') }}/' + doc.id);
                if (data.review) this.openReviewPayload(data.document, data.review);
            } catch (e) {
                this.uploadError = e.message;
            }
        },

        openReviewPayload(doc, review) {
            this.review = { ...review, document_id: doc.id };
            this.applyMessage = '';
            this.reviewForm = {
                course_id: review.suggested_course_id ? String(review.suggested_course_id) : '',
                students: [],
                student_choices: {},
                activities: review.activities.map((a, i) => a.duplicate_of ? -1 : i).filter(i => i >= 0),
                grades: review.grades.map((g, i) => g.student_status === 'existing' ? i : -1).filter(i => i >= 0),
                attendance: review.attendance.map((r, i) => r.student_id ? i : -1).filter(i => i >= 0),
            };
            this.tab = 'import';
        },

        async applyDocument() {
            if (!this.review) return;
            if (!this.reviewForm.course_id) {
                this.applySuccess = false;
                this.applyMessage = 'Selecciona el curso donde aplicar los datos.';
                return;
            }
            this.applying = true;
            this.applyMessage = '';
            try {
                const payload = {
                    course_id: this.reviewForm.course_id,
                    students: this.reviewForm.students.filter(i => this.reviewForm.student_choices[i] || this.review.students[i]?.student_id),
                    student_choices: Object.fromEntries(Object.entries(this.reviewForm.student_choices).filter(([, v]) => v)),
                    activities: this.reviewForm.activities,
                    grades: this.reviewForm.grades,
                    attendance: this.reviewForm.attendance,
                };
                const data = await this.fetchJson('{{ url('intelligence/documents') }}/' + this.review.document_id + '/apply', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                this.applySuccess = !!data.success;
                this.applyMessage = data.message || '';
                if (data.success) {
                    await this.loadDocuments();
                    this.loadDashboard();
                }
            } catch (e) {
                this.applySuccess = false;
                this.applyMessage = e.message || 'No se pudo aplicar el documento.';
            }
            this.applying = false;
        },

        async forwardDocument() {
            if (!this.review) return;
            this.forwarding = true;
            this.applyMessage = '';
            try {
                const data = await this.fetchJson('{{ url('intelligence/documents') }}/' + this.review.document_id + '/forward', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                });
                this.applySuccess = !!data.success;
                this.applyMessage = data.message || '';
                if (data.success) await this.loadDocuments();
            } catch (e) {
                this.applySuccess = false;
                this.applyMessage = e.message || 'No se pudo enviar la revisión al director.';
            }
            this.forwarding = false;
        },

        async deleteDocument(doc) {
            if (!confirm('¿Eliminar «' + doc.original_name + '»?')) return;
            this.deleting = doc.id;
            try {
                await this.fetchJson('{{ url('intelligence/documents') }}/' + doc.id, { method: 'DELETE' });
                await this.loadDocuments();
                if (this.review && this.review.document_id === doc.id) this.review = null;
            } catch (e) { /* silencioso */ }
            this.deleting = null;
        },

        statusLabel(status) {
            return { uploaded: 'Subido', processing: 'Analizando', extracted: 'Revisar', applied: 'Aplicado', failed: 'Error' }[status] || status;
        },

        statusClass(status) {
            return { uploaded: 'dim', processing: 'dim', extracted: '', applied: 'ok', failed: 'err' }[status] || 'dim';
        },

        // ─── PANEL ────────────────────────────────────────
        openPanel() {
            this.tab = 'panel';
            if (!this.summary) this.loadDashboard();
        },

        async loadDashboard() {
            this.loadingDashboard = true;
            try {
                const url = '{{ route('intelligence.dashboard') }}' + (this.panelCourseId ? '?course_id=' + this.panelCourseId : '');
                const data = await this.fetchJson(url);
                this.summary = data.summary;
            } catch (e) { /* silencioso */ }
            this.loadingDashboard = false;
        },

        distPct(key) {
            const d = this.summary?.performance?.distribution;
            if (!d) return 0;
            const total = d.high + d.mid + d.low;
            return total === 0 ? 0 : Math.round(d[key] * 100 / total);
        },

        async runAction(action, count) {
            this.actionMessage = '';
            if (action === 'analyze_student') {
                if (!this.askingStudent) { this.askingStudent = true; this.studentName = ''; return; }
                if (!this.studentName.trim()) return;
            }
            try {
                const payload = { action };
                if (this.panelCourseId) payload.course_id = this.panelCourseId;
                if (count) payload.count = count;
                if (action === 'analyze_student') payload.student_name = this.studentName.trim();
                const data = await this.fetchJson('{{ route('intelligence.actions.run') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                if (!data.success) {
                    this.actionMessage = data.message || 'No se pudo ejecutar la acción.';
                    return;
                }
                if (data.type === 'insight') {
                    this.summary = data.action === 'analyze_student' ? this.summary : data.payload;
                    if (data.action === 'analyze_student' && !data.payload.found) {
                        this.actionMessage = data.payload.message || 'No encontré a ese alumno.';
                    } else if (data.action === 'analyze_student') {
                        this.askingStudent = false;
                        this.openStudentInsight(data.payload);
                    }
                    if (data.action === 'detect_attention') this.summary = { ...this.summary, attention: data.payload.students };
                } else if (data.type === 'proposal') {
                    this.proposal = data.payload;
                    this.proposalSelected = data.payload.items.map((_, i) => i);
                    this.proposalMessage = '';
                } else if (data.type === 'report') {
                    this.report = data.markdown;
                }
            } catch (e) {
                this.actionMessage = e.message || 'No se pudo ejecutar la acción.';
            }
        },

        startStudentAnalysis() {
            this.askingStudent = !this.askingStudent;
            this.studentName = '';
        },

        openStudentInsight(payload) {
            const lines = ['### ' + payload.student.name];
            if (payload.avg_pct !== null) lines.push('Promedio: **' + payload.avg_pct + '%** (' + payload.grades_count + ' calificaciones)');
            else lines.push('Sin calificaciones registradas.');
            if (payload.absences_30d > 0) lines.push('Inasistencias (30 días): ' + payload.absences_30d);
            if (payload.attention_reasons.length) lines.push('⚠️ ' + payload.attention_reasons.join('; '));
            this.report = lines.join('\n\n');
        },

        async applyProposal() {
            this.applyingProposal = true;
            this.proposalMessage = '';
            try {
                const data = await this.fetchJson('{{ route('intelligence.actions.apply') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        selected: this.proposalSelected,
                        dates: this.proposal.dates,
                    }),
                });
                this.proposalMessage = data.message || '';
                if (data.success) {
                    this.proposal = null;
                    this.loadDashboard();
                    window.dispatchEvent(new CustomEvent('ai-canvas-refresh'));
                }
            } catch (e) {
                this.proposalMessage = e.message || 'No se pudo aplicar la propuesta.';
            }
            this.applyingProposal = false;
        },

        printReport() {
            const w = window.open('', '_blank');
            if (!w) return;
            w.document.write('<html><head><title>Informe del grupo</title><style>body{font-family:Inter,system-ui,sans-serif;padding:32px;color:#111;line-height:1.5} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ccc;padding:6px 8px;text-align:left} h3{margin:14px 0 6px}</style></head><body>' + this.md(this.report) + '</body></html>');
            w.document.close();
            w.print();
        },

        // ─── CONSULTA ─────────────────────────────────────
        async ask(preset) {
            const text = (preset || this.queryText || '').trim();
            if (text === '' || this.asking) return;
            this.conversation.push({ role: 'user', text });
            this.queryText = '';
            this.asking = true;
            try {
                const payload = { text };
                if (this.queryCourseId) payload.course_id = this.queryCourseId;
                const data = await this.fetchJson('{{ route('intelligence.query') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                this.conversation.push({ role: 'assistant', text: data.answer?.message || 'Sin respuesta.' });
            } catch (e) {
                this.conversation.push({ role: 'assistant', text: '⚠️ ' + (e.message || 'No se pudo procesar la consulta.') });
            }
            this.asking = false;
        },
    };
}
</script>
</body>
</html>
