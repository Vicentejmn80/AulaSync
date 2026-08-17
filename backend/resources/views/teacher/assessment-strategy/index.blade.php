<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Estrategia de Evaluación · AulaSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }

        body {
            margin: 0;
            font-family: "Segoe UI", Nunito, system-ui, sans-serif;
            color: var(--text-primary);
            background:
                radial-gradient(ellipse 70% 45% at 8% -10%, color-mix(in srgb, var(--nova-violet) 22%, transparent), transparent 55%),
                radial-gradient(ellipse 55% 40% at 92% 0%, color-mix(in srgb, var(--nova-fuchsia) 16%, transparent), transparent 50%),
                radial-gradient(ellipse 40% 30% at 50% 100%, color-mix(in srgb, var(--nova-cyan) 10%, transparent), transparent 45%),
                var(--bg-primary);
            min-height: 100vh;
        }

        .wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 28px 20px 90px;
            position: relative;
            z-index: 1;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--nova-violet);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .back-link:hover { color: var(--nova-fuchsia); }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--nova-fuchsia);
            margin-bottom: 8px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.15;
        }

        h1 .grad {
            background: var(--nova-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .subtitle {
            margin: 0;
            max-width: 560px;
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.55;
        }

        .top-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding: 6px;
            background: color-mix(in srgb, var(--bg-card) 80%, transparent);
            border: 1px solid var(--nova-glass-border);
            border-radius: 999px;
            width: fit-content;
            max-width: 100%;
            backdrop-filter: blur(10px);
        }

        .tab {
            border: 0;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 999px;
            padding: 10px 18px;
            cursor: pointer;
            font-weight: 800;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.18s ease;
        }

        .tab:hover { color: var(--text-primary); background: color-mix(in srgb, var(--nova-violet) 8%, transparent); }
        .tab.active {
            background: var(--nova-gradient);
            color: #fff;
            box-shadow: 0 8px 22px color-mix(in srgb, var(--nova-violet) 35%, transparent);
        }

        .layout {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 16px;
            align-items: start;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--nova-shadow);
            backdrop-filter: blur(12px);
        }

        .card + .card { margin-top: 0; }

        .section-title {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            width: 34px;
            height: 34px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--nova-violet) 14%, transparent);
            color: var(--nova-violet);
            font-size: 14px;
        }

        .section-hint {
            margin: 0 0 14px;
            color: var(--text-tertiary);
            font-size: 13px;
            line-height: 1.45;
        }

        .muted { color: var(--text-secondary); }
        .tiny { font-size: 12px; color: var(--text-tertiary); }

        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }

        label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin: 12px 0 6px;
            color: var(--text-secondary);
        }

        input, select, textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input:focus, select:focus, textarea:focus {
            border-color: color-mix(in srgb, var(--nova-violet) 55%, transparent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--nova-violet) 18%, transparent);
        }

        textarea { resize: vertical; min-height: 96px; line-height: 1.45; }

        .stack {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 14px;
            align-items: center;
        }

        .btn {
            border: 0;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.12s ease, opacity 0.12s ease, box-shadow 0.12s ease;
        }

        .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        .btn-ai {
            background: var(--nova-gradient);
            color: #fff;
            box-shadow: 0 10px 24px color-mix(in srgb, var(--nova-violet) 32%, transparent);
        }

        .btn-main { background: var(--nova-violet); color: #fff; }
        .btn-fuchsia { background: var(--nova-fuchsia); color: #fff; }
        .btn-soft {
            background: color-mix(in srgb, var(--nova-violet) 12%, var(--bg-secondary));
            color: var(--nova-violet);
        }
        .btn-ghost {
            background: transparent;
            color: var(--nova-violet);
            border: 1px solid var(--nova-glass-border);
        }
        .btn-danger {
            background: color-mix(in srgb, #EF4444 14%, var(--bg-secondary));
            color: #DC2626;
        }
        .btn-sm { padding: 7px 12px; font-size: 12px; }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 800;
            border-radius: 999px;
            padding: 5px 10px;
            background: color-mix(in srgb, var(--nova-violet) 12%, transparent);
            color: var(--nova-violet);
        }

        .pill.fuchsia {
            background: color-mix(in srgb, var(--nova-fuchsia) 14%, transparent);
            color: var(--nova-fuchsia);
        }

        .pill.ok {
            background: color-mix(in srgb, var(--nova-success) 16%, transparent);
            color: #15803D;
        }

        .pill.warn {
            background: color-mix(in srgb, var(--nova-warning) 18%, transparent);
            color: #B45309;
        }

        .balance-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 12px 0 4px;
        }

        .ok { color: #0F766E; font-weight: 700; font-size: 13px; margin: 10px 0 0; }
        .warn { color: #B45309; font-weight: 700; font-size: 13px; margin: 10px 0 0; }
        .err { color: #C2410C; font-weight: 700; font-size: 13px; margin: 10px 0 0; }

        .spin { display: inline-block; animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .table-wrap {
            overflow-x: auto;
            margin-top: 14px;
            border: 1px solid var(--nova-glass-border);
            border-radius: 14px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 720px;
        }

        .table th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-tertiary);
            padding: 10px 8px;
            background: color-mix(in srgb, var(--nova-violet) 6%, var(--bg-secondary));
            border-bottom: 1px solid var(--nova-glass-border);
            white-space: nowrap;
        }

        .table td {
            padding: 8px;
            border-bottom: 1px solid var(--nova-glass-border);
            vertical-align: top;
        }

        .table input, .table select, .table textarea {
            padding: 8px 9px;
            border-radius: 10px;
            font-size: 12px;
            min-height: auto;
        }

        .table textarea { min-height: 54px; }

        .list-item {
            padding: 14px 0;
            border-bottom: 1px solid var(--nova-glass-border);
        }

        .list-item:last-child { border-bottom: 0; }

        .list-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .empty {
            padding: 22px 12px;
            text-align: center;
            color: var(--text-tertiary);
            font-size: 13px;
        }

        .empty i {
            display: block;
            font-size: 22px;
            margin-bottom: 8px;
            color: color-mix(in srgb, var(--nova-violet) 55%, var(--text-tertiary));
        }

        .rubric-card {
            border: 1px solid var(--nova-glass-border);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 10px;
            background: color-mix(in srgb, var(--bg-secondary) 88%, transparent);
            transition: border-color 0.15s ease, transform 0.15s ease;
        }

        .rubric-card:hover {
            border-color: color-mix(in srgb, var(--nova-violet) 40%, transparent);
            transform: translateY(-1px);
        }

        .criterion {
            border: 1px solid var(--nova-glass-border);
            border-radius: 14px;
            padding: 14px;
            margin-top: 12px;
            background: color-mix(in srgb, var(--nova-violet) 4%, var(--bg-secondary));
        }

        .criterion-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }

        .matrix-wrap {
            overflow-x: auto;
            margin-top: 14px;
            border: 1px solid var(--nova-glass-border);
            border-radius: 14px;
        }

        .matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 640px;
        }

        .matrix th, .matrix td {
            border-bottom: 1px solid var(--nova-glass-border);
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .matrix th {
            background: color-mix(in srgb, var(--nova-fuchsia) 8%, var(--bg-secondary));
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
        }

        .matrix td:first-child {
            font-weight: 800;
            white-space: nowrap;
            background: color-mix(in srgb, var(--nova-violet) 5%, transparent);
        }

        .divider {
            height: 1px;
            background: var(--nova-glass-border);
            margin: 18px 0;
        }

        .sync-box {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px dashed var(--nova-glass-border);
        }

        @media (max-width: 1080px) {
            .layout { grid-template-columns: 1fr; }
            .row2, .row3 { grid-template-columns: 1fr; }
            .tabs { width: 100%; border-radius: 18px; }
        }
    </style>
</head>
<body>
@include('partials.theme-switcher')

<div class="wrap" x-data="assessmentStrategyApp()" x-cloak>
    <div class="top">
        <div>
            <a class="back-link" href="{{ route('teacher.hub') }}">
                <i class="fa-solid fa-arrow-left"></i> Volver al hub
            </a>
            <div class="eyebrow">
                <i class="fa-solid fa-diagram-project"></i>
                Diseño de evaluación · clase mundial
            </div>
            <h1>Estrategia de <span class="grad">Evaluación</span></h1>
            <p class="subtitle">
                Diseña planes balanceados, analiza sobrecarga y construye rúbricas profesionales alineadas a outcomes de aprendizaje.
            </p>
        </div>
        <div class="top-actions">
            <a class="btn btn-ghost" href="{{ route('teacher.evaluations.index') }}">
                <i class="fa-solid fa-file-signature"></i> Mis evaluaciones
            </a>
        </div>
    </div>

    <div class="tabs">
        <button type="button" class="tab" :class="{ active: tab === 'plans' }" @click="tab = 'plans'">
            <i class="fa-solid fa-sitemap"></i> Plan de Evaluación
        </button>
        <button type="button" class="tab" :class="{ active: tab === 'rubrics' }" @click="tab = 'rubrics'">
            <i class="fa-solid fa-table-cells-large"></i> Rúbricas
        </button>
    </div>

    {{-- ===================== PLAN TAB ===================== --}}
    <div x-show="tab === 'plans'" class="layout">
        <div class="card">
            <h3 class="section-title">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                Generador inteligente de plan
            </h3>
            <p class="section-hint">
                Indica el programa y el equilibrio deseado. La IA distribuirá evidencias formativas y sumativas a lo largo del período.
            </p>

            <div class="row3">
                <div>
                    <label>Curso</label>
                    <select x-model="planForm.course_id">
                        <option value="">Selecciona un curso</option>
                        <template x-for="c in courses" :key="c.id">
                            <option :value="c.id" x-text="courseLabel(c)"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label>Semanas</label>
                    <input type="number" min="4" max="40" x-model.number="planForm.weeks">
                </div>
                <div>
                    <label>Balance</label>
                    <select x-model="planForm.balance">
                        <option value="balanced">Equilibrado (recomendado)</option>
                        <option value="process">Enfatizar proceso (formativa)</option>
                        <option value="product">Enfatizar producto (sumativa)</option>
                    </select>
                </div>
            </div>

            <label>Programa / unidades del curso</label>
            <textarea
                rows="4"
                x-model="planForm.program_text"
                placeholder="Describe unidades, objetivos, ritmos y evidencias esperadas. Ejemplo: 4 unidades de álgebra con quizzes semanales, un proyecto aplicado y un examen parcial..."
            ></textarea>

            <div class="stack">
                <button type="button" class="btn btn-ai" :disabled="loadingPlan" @click="generatePlan()">
                    <i class="fa-solid fa-wand-magic-sparkles" :class="{ spin: loadingPlan }"></i>
                    <span x-text="loadingPlan ? 'Generando…' : 'Generar plan con IA'"></span>
                </button>
                <button type="button" class="btn btn-soft" :disabled="!planDraft || analyzing" @click="analyzeOverload()">
                    <i class="fa-solid fa-scale-balanced" :class="{ spin: analyzing }"></i>
                    <span x-text="analyzing ? 'Analizando…' : 'Analizar balance y sobrecarga'"></span>
                </button>
                <button type="button" class="btn btn-main" :disabled="!planDraft || savingPlan" @click="savePlan()">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span x-text="savingPlan ? 'Guardando…' : 'Guardar plan'"></span>
                </button>
                <button type="button" class="btn btn-fuchsia" :disabled="!savedPlanId || publishing" @click="publishToCalendar()">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span x-text="publishing ? 'Publicando…' : 'Publicar en calendario'"></span>
                </button>
            </div>

            <p class="ok" x-show="planMessage" x-text="planMessage"></p>
            <p class="warn" x-show="planWarning" x-text="planWarning"></p>
            <p class="err" x-show="planError" x-text="planError"></p>

            <template x-if="balance">
                <div class="balance-row">
                    <span class="pill ok"><i class="fa-solid fa-seedling"></i> Formativa <span x-text="balance.formative + '%'"></span></span>
                    <span class="pill fuchsia"><i class="fa-solid fa-flag-checkered"></i> Sumativa <span x-text="balance.summative + '%'"></span></span>
                    <span class="pill" :class="Math.abs(balance.total - 100) <= 1.5 ? 'ok' : 'warn'">
                        <i class="fa-solid fa-percent"></i> Total <span x-text="balance.total + '%'"></span>
                    </span>
                </div>
            </template>

            <template x-if="planDraft">
                <div>
                    <div class="divider"></div>
                    <label>Título del plan</label>
                    <input x-model="planDraft.title" placeholder="Plan de evaluación · ...">
                    <label>Resumen</label>
                    <textarea rows="2" x-model="planDraft.summary" placeholder="Breve narrativa pedagógica del plan"></textarea>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Unidad</th>
                                    <th>Tipo</th>
                                    <th>Categoría</th>
                                    <th>%</th>
                                    <th>Fecha</th>
                                    <th>Outcome</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in planDraft.items" :key="idx">
                                    <tr>
                                        <td><input x-model="item.unit_name" placeholder="Unidad"></td>
                                        <td><input x-model="item.assessment_type" placeholder="Quiz, proyecto..."></td>
                                        <td>
                                            <select x-model="item.category">
                                                <option value="formative">Formativa</option>
                                                <option value="summative">Sumativa</option>
                                            </select>
                                        </td>
                                        <td style="width:78px"><input type="number" min="0" max="100" step="0.5" x-model.number="item.weight_percentage"></td>
                                        <td style="width:140px"><input type="date" x-model="item.due_date"></td>
                                        <td><input x-model="item.learning_outcome" placeholder="Aprendizaje esperado"></td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm" @click="removePlanItem(idx)" title="Quitar ítem">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="stack">
                        <button type="button" class="btn btn-ghost btn-sm" @click="addPlanItem()">
                            <i class="fa-solid fa-plus"></i> Agregar ítem
                        </button>
                        <span class="tiny" x-show="savedPlanId">
                            <i class="fa-solid fa-circle-check"></i> Plan guardado #<span x-text="savedPlanId"></span>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <div class="card">
            <h3 class="section-title">
                <i class="fa-solid fa-folder-open"></i>
                Planes guardados
            </h3>
            <p class="section-hint">Tus estrategias publicadas y borradores. Sincroniza evaluaciones existentes al plan.</p>

            <template x-if="plans.length === 0">
                <div class="empty">
                    <i class="fa-solid fa-clipboard-list"></i>
                    Aún no hay planes guardados. Genera uno con IA para comenzar.
                </div>
            </template>

            <template x-for="p in plans" :key="p.id">
                <div class="list-item">
                    <div class="list-head">
                        <div>
                            <strong x-text="p.title"></strong>
                            <div class="tiny" x-text="p.course ? courseLabel(p.course) : 'Sin curso'"></div>
                            <div class="stack" style="margin-top:8px">
                                <span class="pill" x-text="(p.items || []).length + ' ítems'"></span>
                                <span class="pill" :class="p.status === 'published' ? 'ok' : ''" x-text="p.status || 'draft'"></span>
                                <span class="pill" :class="planIsBalanced(p) ? 'ok' : 'warn'" x-text="'Total: ' + planTotalWeight(p) + '%'"></span>
                            </div>
                        </div>
                        <div class="stack" style="margin-top:0">
                            <button type="button" class="btn btn-soft btn-sm" @click="loadPlan(p)" title="Cargar en editor">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" :disabled="deletingPlanId === p.id" @click="deletePlan(p.id)">
                                <i class="fa-solid fa-trash" :class="{ spin: deletingPlanId === p.id }"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div class="sync-box">
                <h3 class="section-title" style="font-size:16px">
                    <i class="fa-solid fa-link"></i>
                    Sincronizar evaluación
                </h3>
                <p class="section-hint">Adjunta una evaluación existente a un plan con su peso relativo.</p>

                <label>Evaluación</label>
                <select x-model="attachForm.evaluation_id">
                    <option value="">Selecciona una evaluación</option>
                    <template x-for="ev in evaluations" :key="ev.id">
                        <option :value="ev.id" x-text="ev.title + (ev.course ? ' · ' + ev.course.subject_name : '')"></option>
                    </template>
                </select>

                <label>Plan destino</label>
                <select x-model="attachForm.plan_id">
                    <option value="">Auto (crear/usar plan del curso)</option>
                    <template x-for="p in plans" :key="'attach-' + p.id">
                        <option :value="p.id" x-text="p.title"></option>
                    </template>
                </select>

                <div class="row2">
                    <div>
                        <label>Peso %</label>
                        <input type="number" min="1" max="100" x-model.number="attachForm.weight_percentage">
                    </div>
                    <div>
                        <label>Categoría</label>
                        <select x-model="attachForm.category">
                            <option value="summative">Sumativa</option>
                            <option value="formative">Formativa</option>
                        </select>
                    </div>
                </div>

                <label>Unidad (opcional)</label>
                <input x-model="attachForm.unit_name" placeholder="Nombre de unidad">

                <div class="stack">
                    <button type="button" class="btn btn-main" :disabled="attaching" @click="attachEvaluation()">
                        <i class="fa-solid fa-plus" :class="{ spin: attaching }"></i>
                        <span x-text="attaching ? 'Agregando…' : 'Agregar al plan'"></span>
                    </button>
                </div>
                <p class="ok" x-show="attachMessage" x-text="attachMessage"></p>
                <p class="err" x-show="attachError" x-text="attachError"></p>
            </div>
        </div>
    </div>

    {{-- ===================== RUBRICS TAB ===================== --}}
    <div x-show="tab === 'rubrics'" class="layout">
        <div class="card">
            <h3 class="section-title">
                <i class="fa-solid fa-sparkles"></i>
                Generador de rúbricas
            </h3>
            <p class="section-hint">
                Crea rúbricas analíticas, holísticas o de punto único con descriptores observables y medibles.
            </p>

            <div class="row3">
                <div>
                    <label>Tipo</label>
                    <select x-model="rubricForm.type">
                        <option value="analytic">Analítica</option>
                        <option value="holistic">Holística</option>
                        <option value="single_point">Punto único</option>
                    </select>
                </div>
                <div>
                    <label>Curso</label>
                    <select x-model="rubricForm.course_id">
                        <option value="">Sin curso</option>
                        <template x-for="c in courses" :key="'rubric-c-' + c.id">
                            <option :value="c.id" x-text="courseLabel(c)"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label>Tipo de tarea</label>
                    <input x-model="rubricForm.task_type" placeholder="Ensayo, proyecto, exposición...">
                </div>
            </div>

            <label>Describe la rúbrica</label>
            <textarea
                rows="4"
                x-model="rubricForm.prompt"
                placeholder="Ej. Rúbrica para un ensayo argumentativo de 8° grado sobre cambio climático. Enfocada en tesis, evidencia, coherencia y lenguaje académico."
            ></textarea>

            <div class="stack">
                <button type="button" class="btn btn-ai" :disabled="loadingRubric" @click="generateRubric()">
                    <i class="fa-solid fa-wand-magic-sparkles" :class="{ spin: loadingRubric }"></i>
                    <span x-text="loadingRubric ? 'Generando…' : 'Generar rúbrica con IA'"></span>
                </button>
                <button type="button" class="btn btn-main" :disabled="!rubricDraft || savingRubric" @click="saveRubric()">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span x-text="savingRubric ? 'Guardando…' : 'Guardar rúbrica'"></span>
                </button>
            </div>

            <p class="ok" x-show="rubricMessage" x-text="rubricMessage"></p>
            <p class="err" x-show="rubricError" x-text="rubricError"></p>

            <template x-if="rubricDraft">
                <div>
                    <div class="divider"></div>
                    <div class="row2">
                        <div>
                            <label>Título</label>
                            <input x-model="rubricDraft.title">
                        </div>
                        <div>
                            <label>Puntos totales</label>
                            <input type="number" min="1" max="1000" x-model.number="rubricDraft.total_points">
                        </div>
                    </div>
                    <label>Descripción</label>
                    <textarea rows="2" x-model="rubricDraft.description"></textarea>

                    <template x-for="(crit, ci) in rubricDraft.criteria" :key="ci">
                        <div class="criterion">
                            <div class="criterion-head">
                                <strong>Criterio <span x-text="ci + 1"></span></strong>
                                <button type="button" class="btn btn-danger btn-sm" @click="removeCriterion(ci)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            <div class="row2">
                                <div>
                                    <label>Nombre</label>
                                    <input x-model="crit.name">
                                </div>
                                <div>
                                    <label>Peso %</label>
                                    <input type="number" min="0" max="100" step="0.5" x-model.number="crit.weight_percentage">
                                </div>
                            </div>
                            <div class="row2">
                                <div>
                                    <label>Excelente</label>
                                    <textarea rows="2" x-model="crit.descriptors.excellent"></textarea>
                                </div>
                                <div>
                                    <label>Competente</label>
                                    <textarea rows="2" x-model="crit.descriptors.proficient"></textarea>
                                </div>
                                <div>
                                    <label>En desarrollo</label>
                                    <textarea rows="2" x-model="crit.descriptors.developing"></textarea>
                                </div>
                                <div>
                                    <label>Inicial</label>
                                    <textarea rows="2" x-model="crit.descriptors.beginning"></textarea>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="stack">
                        <button type="button" class="btn btn-ghost btn-sm" @click="addCriterion()">
                            <i class="fa-solid fa-plus"></i> Agregar criterio
                        </button>
                    </div>

                    <div class="divider"></div>
                    <h3 class="section-title" style="font-size:16px">
                        <i class="fa-solid fa-table-cells"></i>
                        Vista matriz
                    </h3>
                    <p class="section-hint">Vista rápida criterios × niveles de desempeño.</p>
                    <div class="matrix-wrap">
                        <table class="matrix">
                            <thead>
                                <tr>
                                    <th>Criterio</th>
                                    <th>Excelente</th>
                                    <th>Competente</th>
                                    <th>En desarrollo</th>
                                    <th>Inicial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(crit, ci) in rubricDraft.criteria" :key="'mx-' + ci">
                                    <tr>
                                        <td>
                                            <div x-text="crit.name || ('Criterio ' + (ci + 1))"></div>
                                            <div class="tiny" x-text="(crit.weight_percentage || 0) + '%'"></div>
                                        </td>
                                        <td x-text="crit.descriptors?.excellent || '—'"></td>
                                        <td x-text="crit.descriptors?.proficient || '—'"></td>
                                        <td x-text="crit.descriptors?.developing || '—'"></td>
                                        <td x-text="crit.descriptors?.beginning || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>

        <div class="card">
            <h3 class="section-title">
                <i class="fa-solid fa-book-open"></i>
                Biblioteca de rúbricas
            </h3>
            <p class="section-hint">Rúbricas guardadas listas para reutilizar en evaluaciones y proyectos.</p>

            <template x-if="rubrics.length === 0">
                <div class="empty">
                    <i class="fa-solid fa-table-cells"></i>
                    Tu biblioteca está vacía. Genera tu primera rúbrica con IA.
                </div>
            </template>

            <template x-for="r in rubrics" :key="r.id">
                <div class="rubric-card">
                    <div class="list-head">
                        <div>
                            <strong x-text="r.title"></strong>
                            <div class="tiny" x-text="r.course ? courseLabel(r.course) : 'Sin curso asignado'"></div>
                            <div class="stack" style="margin-top:8px">
                                <span class="pill" x-text="rubricTypeLabel(r.type)"></span>
                                <span class="pill fuchsia" x-text="((r.criteria || []).length) + ' criterios'"></span>
                                <span class="pill" x-show="r.total_points" x-text="r.total_points + ' pts'"></span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" :disabled="deletingRubricId === r.id" @click="deleteRubric(r.id)">
                            <i class="fa-solid fa-trash" :class="{ spin: deletingRubricId === r.id }"></i>
                        </button>
                    </div>
                    <p class="tiny" style="margin:10px 0 0" x-show="r.description" x-text="r.description"></p>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function assessmentStrategyApp() {
    return {
        tab: 'plans',
        courses: @json($courses),
        plans: @json($plans),
        rubrics: @json($rubrics),
        evaluations: @json($evaluations),

        planForm: {
            course_id: '',
            weeks: 12,
            balance: 'balanced',
            program_text: '',
        },
        planDraft: null,
        savedPlanId: null,
        balance: null,
        loadingPlan: false,
        analyzing: false,
        savingPlan: false,
        publishing: false,
        deletingPlanId: null,
        planMessage: '',
        planWarning: '',
        planError: '',

        attachForm: {
            evaluation_id: '',
            plan_id: '',
            unit_name: '',
            weight_percentage: 10,
            category: 'summative',
        },
        attaching: false,
        attachMessage: '',
        attachError: '',

        rubricForm: {
            type: 'analytic',
            course_id: '',
            task_type: '',
            prompt: '',
        },
        rubricDraft: null,
        loadingRubric: false,
        savingRubric: false,
        deletingRubricId: null,
        rubricMessage: '',
        rubricError: '',

        csrf() {
            return document.querySelector('meta[name="csrf-token"]').content;
        },

        headers(json = true) {
            const h = {
                'X-CSRF-TOKEN': this.csrf(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        },

        async api(url, options = {}) {
            const res = await fetch(url, options);
            let data = {};
            try { data = await res.json(); } catch (_) { data = {}; }
            if (!res.ok && !data.error) {
                data.error = data.message || ('Error HTTP ' + res.status);
                data.success = false;
            }
            return data;
        },

        courseLabel(c) {
            if (!c) return 'Sin curso';
            return `${c.subject_name || ''} · ${c.grade || ''}${c.section ? ' / ' + c.section : ''}`.trim();
        },

        rubricTypeLabel(type) {
            return ({ analytic: 'Analítica', holistic: 'Holística', single_point: 'Punto único' })[type] || type || '—';
        },

        clearPlanAlerts() {
            this.planMessage = '';
            this.planWarning = '';
            this.planError = '';
        },

        clearRubricAlerts() {
            this.rubricMessage = '';
            this.rubricError = '';
        },

        ensureDescriptors(crit) {
            if (!crit.descriptors || typeof crit.descriptors !== 'object') {
                crit.descriptors = { excellent: '', proficient: '', developing: '', beginning: '' };
            } else {
                ['excellent', 'proficient', 'developing', 'beginning'].forEach(k => {
                    if (crit.descriptors[k] == null) crit.descriptors[k] = '';
                });
            }
            return crit;
        },

        normalizePlan(plan) {
            const items = (plan.items || []).map(item => ({
                unit_name: item.unit_name || '',
                assessment_type: item.assessment_type || '',
                category: item.category || 'summative',
                weight_percentage: Number(item.weight_percentage ?? 0),
                due_date: item.due_date || '',
                learning_outcome: item.learning_outcome || '',
                notes: item.notes || '',
                evaluation_id: item.evaluation_id || null,
            }));
            return {
                title: plan.title || 'Plan de evaluación',
                summary: plan.summary || '',
                formative_weight: plan.formative_weight ?? null,
                summative_weight: plan.summative_weight ?? null,
                items,
            };
        },

        normalizeRubric(rubric) {
            return {
                title: rubric.title || 'Rúbrica',
                description: rubric.description || '',
                type: rubric.type || this.rubricForm.type,
                levels: rubric.levels || null,
                total_points: Number(rubric.total_points ?? 100),
                criteria: (rubric.criteria || []).map(c => this.ensureDescriptors({
                    name: c.name || '',
                    weight_percentage: Number(c.weight_percentage ?? 0),
                    descriptors: c.descriptors || {},
                })),
            };
        },

        addPlanItem() {
            if (!this.planDraft) return;
            this.planDraft.items.push({
                unit_name: '',
                assessment_type: '',
                category: 'summative',
                weight_percentage: 10,
                due_date: '',
                learning_outcome: '',
                notes: '',
            });
        },

        removePlanItem(idx) {
            if (!this.planDraft) return;
            this.planDraft.items.splice(idx, 1);
        },

        loadPlan(p) {
            this.planDraft = this.normalizePlan(p);
            this.savedPlanId = p.id;
            this.planForm.course_id = p.course_id || (p.course && p.course.id) || this.planForm.course_id;
            this.balance = {
                formative: Number(p.formative_weight ?? 0),
                summative: Number(p.summative_weight ?? 0),
                total: Number((p.formative_weight ?? 0) + (p.summative_weight ?? 0)),
            };
            this.clearPlanAlerts();
            this.planMessage = 'Plan cargado en el editor.';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        async generatePlan() {
            this.clearPlanAlerts();
            if (!this.planForm.course_id) {
                this.planError = 'Selecciona un curso para generar el plan.';
                return;
            }
            if (!this.planForm.program_text || this.planForm.program_text.trim().length < 12) {
                this.planError = 'Describe el programa del curso con al menos unas líneas de contexto.';
                return;
            }
            this.loadingPlan = true;
            try {
                const data = await this.api('{{ route('teacher.assessment.plans.generate') }}', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(this.planForm),
                });
                if (!data.success) {
                    this.planError = data.error || 'No se pudo generar el plan.';
                    return;
                }
                this.planDraft = this.normalizePlan(data.plan);
                this.savedPlanId = null;
                this.balance = null;
                this.planMessage = 'Plan generado. Edítalo y guárdalo cuando esté listo.';
            } catch (e) {
                this.planError = 'Error de red al generar el plan.';
            } finally {
                this.loadingPlan = false;
            }
        },

        async analyzeOverload() {
            this.clearPlanAlerts();
            if (!this.planDraft || !this.planForm.course_id) {
                this.planError = 'Necesitas un borrador y un curso para analizar.';
                return;
            }
            this.analyzing = true;
            try {
                const data = await this.api('{{ route('teacher.assessment.plans.overload') }}', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({
                        course_id: this.planForm.course_id,
                        items: this.planDraft.items || [],
                    }),
                });
                if (!data.success) {
                    this.planError = data.error || 'No se pudo analizar el plan.';
                    return;
                }
                this.balance = data.balance || null;
                if (data.status === 'ok') {
                    this.planMessage = data.message || 'El plan está balanceado.';
                } else {
                    const extra = Array.isArray(data.warnings) && data.warnings.length
                        ? ' ' + data.warnings.join(' · ')
                        : '';
                    this.planWarning = (data.message || 'Se detectaron oportunidades de mejora.') + extra;
                }
            } catch (e) {
                this.planError = 'Error de red al analizar el plan.';
            } finally {
                this.analyzing = false;
            }
        },

        async savePlan() {
            this.clearPlanAlerts();
            if (!this.planDraft) return;
            if (!this.planForm.course_id) {
                this.planError = 'Selecciona un curso antes de guardar.';
                return;
            }
            if (!this.planDraft.items || this.planDraft.items.length === 0) {
                this.planError = 'El plan necesita al menos un ítem.';
                return;
            }
            this.savingPlan = true;
            try {
                const payload = {
                    course_id: this.planForm.course_id,
                    title: this.planDraft.title || 'Plan de evaluación',
                    summary: this.planDraft.summary || '',
                    formative_weight: this.balance?.formative ?? this.planDraft.formative_weight,
                    summative_weight: this.balance?.summative ?? this.planDraft.summative_weight,
                    status: 'draft',
                    items: this.planDraft.items,
                };
                const data = await this.api('{{ route('teacher.assessment.plans.store') }}', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(payload),
                });
                if (!data.success) {
                    this.planError = data.error || 'No se pudo guardar el plan.';
                    return;
                }
                this.plans.unshift(data.plan);
                this.savedPlanId = data.plan.id;
                this.planMessage = 'Plan guardado correctamente.';
            } catch (e) {
                this.planError = 'Error de red al guardar el plan.';
            } finally {
                this.savingPlan = false;
            }
        },

        async publishToCalendar() {
            this.clearPlanAlerts();
            if (!this.savedPlanId) {
                this.planError = 'Guarda el plan antes de publicarlo en el calendario.';
                return;
            }
            this.publishing = true;
            try {
                const url = @json(route('teacher.assessment.plans.publish_calendar', ['plan' => '__ID__'])).replace('__ID__', this.savedPlanId);
                const data = await this.api(url, {
                    method: 'POST',
                    headers: this.headers(false),
                });
                if (!data.success) {
                    this.planError = data.error || 'No se pudo publicar en el calendario.';
                    return;
                }
                this.planMessage = data.message || 'Eventos publicados en el calendario.';
            } catch (e) {
                this.planError = 'Error de red al publicar en calendario.';
            } finally {
                this.publishing = false;
            }
        },

        async deletePlan(id) {
            if (!confirm('¿Eliminar este plan de evaluación?')) return;
            this.deletingPlanId = id;
            try {
                const url = @json(route('teacher.assessment.plans.destroy', ['plan' => '__ID__'])).replace('__ID__', id);
                const data = await this.api(url, {
                    method: 'DELETE',
                    headers: this.headers(false),
                });
                if (!data.success) {
                    this.planError = data.error || 'No se pudo eliminar el plan.';
                    return;
                }
                this.plans = this.plans.filter(p => p.id !== id);
                if (this.savedPlanId === id) this.savedPlanId = null;
                this.planMessage = 'Plan eliminado.';
            } catch (e) {
                this.planError = 'Error de red al eliminar el plan.';
            } finally {
                this.deletingPlanId = null;
            }
        },

        planTotalWeight(p) {
            const total = (p.items || []).reduce((sum, item) => sum + Number(item.weight_percentage || 0), 0);
            return Math.round(total * 100) / 100;
        },
        planIsBalanced(p) {
            return Math.abs(this.planTotalWeight(p) - 100) <= 0.5;
        },
        async attachEvaluation() {
            this.attachMessage = '';
            this.attachError = '';
            if (!this.attachForm.evaluation_id) {
                this.attachError = 'Selecciona una evaluación.';
                return;
            }
            this.attaching = true;
            try {
                const payload = {
                    evaluation_id: Number(this.attachForm.evaluation_id),
                    plan_id: this.attachForm.plan_id ? Number(this.attachForm.plan_id) : null,
                    unit_name: this.attachForm.unit_name || null,
                    weight_percentage: this.attachForm.weight_percentage || 10,
                    category: this.attachForm.category || 'summative',
                };
                const data = await this.api('{{ route('teacher.assessment.attach_evaluation') }}', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(payload),
                });
                if (!data.success) {
                    this.attachError = data.error || 'No se pudo adjuntar la evaluación.';
                    return;
                }
                if (data.plan) {
                    const idx = this.plans.findIndex(p => p.id === data.plan.id);
                    if (idx >= 0) this.plans[idx] = data.plan;
                    else this.plans.unshift(data.plan);
                }
                this.attachMessage = data.message || 'Evaluación agregada al plan.';
            } catch (e) {
                this.attachError = 'Error de red al sincronizar.';
            } finally {
                this.attaching = false;
            }
        },

        addCriterion() {
            if (!this.rubricDraft) return;
            this.rubricDraft.criteria.push(this.ensureDescriptors({
                name: '',
                weight_percentage: 10,
                descriptors: {},
            }));
        },

        removeCriterion(idx) {
            if (!this.rubricDraft) return;
            this.rubricDraft.criteria.splice(idx, 1);
        },

        async generateRubric() {
            this.clearRubricAlerts();
            if (!this.rubricForm.prompt || this.rubricForm.prompt.trim().length < 10) {
                this.rubricError = 'Describe la tarea o el propósito de la rúbrica con más detalle.';
                return;
            }
            this.loadingRubric = true;
            try {
                const data = await this.api('{{ route('teacher.assessment.rubrics.generate') }}', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(this.rubricForm),
                });
                if (!data.success) {
                    this.rubricError = data.error || 'No se pudo generar la rúbrica.';
                    return;
                }
                this.rubricDraft = this.normalizeRubric(data.rubric);
                this.rubricMessage = 'Rúbrica generada. Revisa los descriptores y guárdala.';
            } catch (e) {
                this.rubricError = 'Error de red al generar la rúbrica.';
            } finally {
                this.loadingRubric = false;
            }
        },

        async saveRubric() {
            this.clearRubricAlerts();
            if (!this.rubricDraft) return;
            if (!this.rubricDraft.title || !this.rubricDraft.criteria?.length) {
                this.rubricError = 'La rúbrica necesita título y al menos un criterio.';
                return;
            }
            this.savingRubric = true;
            try {
                const payload = {
                    title: this.rubricDraft.title,
                    description: this.rubricDraft.description || '',
                    course_id: this.rubricForm.course_id || null,
                    task_type: this.rubricForm.task_type || null,
                    type: this.rubricDraft.type || this.rubricForm.type,
                    levels: this.rubricDraft.levels,
                    total_points: this.rubricDraft.total_points || 100,
                    status: 'draft',
                    generated_by_ai: true,
                    criteria: this.rubricDraft.criteria,
                };
                const data = await this.api('{{ route('teacher.assessment.rubrics.store') }}', {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(payload),
                });
                if (!data.success) {
                    this.rubricError = data.error || 'No se pudo guardar la rúbrica.';
                    return;
                }
                this.rubrics.unshift(data.rubric);
                this.rubricMessage = 'Rúbrica guardada en tu biblioteca.';
            } catch (e) {
                this.rubricError = 'Error de red al guardar la rúbrica.';
            } finally {
                this.savingRubric = false;
            }
        },

        async deleteRubric(id) {
            if (!confirm('¿Eliminar esta rúbrica?')) return;
            this.deletingRubricId = id;
            try {
                const url = @json(route('teacher.assessment.rubrics.destroy', ['rubric' => '__ID__'])).replace('__ID__', id);
                const data = await this.api(url, {
                    method: 'DELETE',
                    headers: this.headers(false),
                });
                if (!data.success) {
                    this.rubricError = data.error || 'No se pudo eliminar la rúbrica.';
                    return;
                }
                this.rubrics = this.rubrics.filter(r => r.id !== id);
                this.rubricMessage = 'Rúbrica eliminada.';
            } catch (e) {
                this.rubricError = 'Error de red al eliminar la rúbrica.';
            } finally {
                this.deletingRubricId = null;
            }
        },
    };
}
</script>
</body>
</html>
