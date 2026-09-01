<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gestión · AulaSync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    @include('partials.director-ui-styles')
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
        [x-cloak] { display: none !important; }
        .hub-card {
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 1.25rem;
            box-shadow: var(--nova-shadow);
            color: var(--text-primary);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
        }
        .hub-card:hover { transform: translateY(-2px); }
        .hub-card.active { border-color: var(--nova-violet); box-shadow: 0 0 0 4px color-mix(in srgb, var(--nova-violet) 18%, transparent); }
        .hub-row { transition: background .15s ease, transform .25s ease, opacity .25s ease; }
        .hub-row.just-in { animation: hubIn .45s ease; }
        @keyframes hubIn {
            from { opacity: 0; transform: translateY(10px) scale(.98); }
            to { opacity: 1; transform: none; }
        }
        .hub-chip {
            display: inline-flex; align-items: center; gap: .35rem;
            border-radius: 999px; padding: .2rem .65rem;
            font-size: .72rem; font-weight: 600; background: color-mix(in srgb, var(--nova-violet) 14%, transparent); color: var(--nova-violet);
        }
        .hub-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            border-radius: .9rem; padding: .65rem 1rem; font-weight: 700; font-size: .875rem;
            transition: background .15s ease, transform .15s ease, box-shadow .15s ease;
        }
        .hub-btn:hover { transform: translateY(-1px); }
        .hub-btn-solid { background: var(--nova-violet); color: #fff; box-shadow: 0 8px 18px -10px var(--nova-violet); }
        .hub-btn-solid:hover { filter: brightness(1.05); }
        .hub-btn-ghost { border: 1px solid var(--nova-glass-border); background: var(--bg-card); color: var(--text-primary); }
        .hub-btn-ghost:hover { background: var(--bg-tertiary); }
        .hub-btn-danger { border: 1px solid #fecaca; color: #b91c1c; background: var(--bg-card); }
        html.dark .hub-btn-danger { border-color: rgba(248,113,113,.35); color: #fecaca; }
        .hub-btn-danger:hover { background: #fef2f2; }
        html.dark .hub-btn-danger:hover { background: rgba(127,29,29,.35); }
        .hub-input {
            width: 100%; border: 1px solid var(--nova-glass-border); border-radius: .9rem; padding: .7rem .9rem;
            background: var(--bg-secondary); font-size: .9rem; color: var(--text-primary);
        }
        .hub-input:focus { outline: none; border-color: var(--nova-violet); box-shadow: 0 0 0 3px color-mix(in srgb, var(--nova-violet) 22%, transparent); }
        .hub-table-wrap { overflow: auto; border-radius: 1.1rem; border: 1px solid var(--nova-glass-border); background: var(--bg-card); }
        table.hub-table { width: 100%; border-collapse: collapse; }
        table.hub-table th { text-align: left; font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: var(--text-secondary); padding: .85rem 1rem; border-bottom: 1px solid var(--nova-glass-border); }
        table.hub-table td { padding: .85rem 1rem; border-bottom: 1px solid var(--nova-glass-border); font-size: .9rem; color: var(--text-primary); }
        .drop-over { outline: 2px dashed var(--nova-violet); background: color-mix(in srgb, var(--nova-violet) 12%, transparent); }
        .progress-bar { height: 4px; border-radius: 99px; overflow: hidden; background: color-mix(in srgb, var(--nova-violet) 18%, transparent); }
        .progress-bar > span { display: block; height: 100%; background: var(--nova-gradient); animation: load 1.2s ease infinite; }
        @keyframes load { 0% { width: 12%; } 50% { width: 78%; } 100% { width: 12%; } }
        .hub-pick { border: 1px solid var(--nova-glass-border); border-radius: .9rem; padding: .7rem .8rem; background: var(--bg-secondary); }
        .hub-pick.disabled { opacity: .55; }
        .hub-muted { color: var(--text-secondary); }
        .hub-kicker { color: var(--nova-violet); }
        .hub-nav {
            display: inline-flex; border-radius: 1rem; padding: .25rem;
            border: 1px solid var(--nova-glass-border); background: var(--bg-card); box-shadow: var(--nova-shadow);
        }
        .hub-nav a, .hub-nav span {
            border-radius: .75rem; padding: .5rem 1rem; font-size: .875rem; font-weight: 700;
            color: var(--text-primary);
        }
        .hub-nav a { opacity: .78; }
        .hub-nav a:hover { opacity: 1; background: var(--bg-tertiary); }
        .hub-nav .is-active { opacity: 1; background: var(--nova-violet); color: #fff; }
        .hub-drawer { background: var(--bg-card); color: var(--text-primary); }
        .hub-select-all {
            display: inline-flex; align-items: center; gap: .5rem;
            border-radius: .75rem; border: 1px solid var(--nova-glass-border);
            background: var(--bg-card); padding: .5rem .75rem; font-size: .875rem; font-weight: 600;
            color: var(--text-primary);
        }
        body .text-slate-400, body .text-slate-500 { color: var(--text-secondary) !important; }
        body .text-slate-800 { color: var(--text-primary) !important; }
        body .bg-white { background: var(--bg-card) !important; }
        body .bg-slate-50 { background: var(--bg-tertiary) !important; }
        body .border-slate-100, body .border-slate-200 { border-color: var(--nova-glass-border) !important; }
        html.dark .bg-indigo-50, html[data-theme="dark"] .bg-indigo-50,
        html[data-theme="eco"] .bg-indigo-50, html[data-theme="neon"] .bg-indigo-50 {
            background: color-mix(in srgb, var(--nova-violet) 18%, transparent) !important;
        }
        html.dark .hub-btn-danger, html[data-theme="dark"] .hub-btn-danger,
        html[data-theme="eco"] .hub-btn-danger, html[data-theme="neon"] .hub-btn-danger {
            border-color: rgba(248,113,113,.35); color: #fecaca;
        }
        html.dark .hub-btn-danger:hover, html[data-theme="dark"] .hub-btn-danger:hover,
        html[data-theme="eco"] .hub-btn-danger:hover, html[data-theme="neon"] .hub-btn-danger:hover {
            background: rgba(127,29,29,.35);
        }
        .grade-card {
            position: relative;
            overflow: hidden;
            border-radius: 1.35rem;
            border: 1px solid color-mix(in srgb, var(--nova-glass-border) 70%, transparent);
            box-shadow: 0 16px 36px -24px rgba(15, 23, 42, .45);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .grade-card:hover { transform: translateY(-3px); box-shadow: 0 22px 40px -22px rgba(15, 23, 42, .5); }
        .grade-card.is-open { transform: none; box-shadow: 0 24px 48px -20px rgba(15, 23, 42, .35); }
        .grade-chip {
            display: inline-flex; align-items: center; max-width: 100%;
            border-radius: 999px; padding: .18rem .55rem;
            font-size: .68rem; font-weight: 700; letter-spacing: .01em;
            background: rgba(255,255,255,.72); color: #334155;
        }
        html.dark .grade-chip, html[data-theme="dark"] .grade-chip,
        html[data-theme="eco"] .grade-chip, html[data-theme="neon"] .grade-chip {
            background: color-mix(in srgb, var(--bg-card) 80%, transparent); color: var(--text-primary);
        }
        .grade-expand {
            border-top: 1px solid color-mix(in srgb, #fff 55%, transparent);
            background: color-mix(in srgb, var(--bg-card) 88%, transparent);
            padding: 1rem;
        }
        .grade-stats {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .55rem; margin-bottom: .85rem;
        }
        .grade-stat {
            border-radius: .9rem; padding: .65rem .7rem;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
        }
        .grade-stat b { display: block; font-size: 1.15rem; line-height: 1.1; font-weight: 800; }
        .grade-stat span { font-size: .65rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-secondary); }
        .grade-inner {
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 1.05rem;
            padding: .9rem;
            min-height: 12rem;
            box-shadow: 0 10px 24px -18px rgba(15, 23, 42, .4);
        }
        .grade-inner-head {
            display: flex; align-items: center; justify-content: space-between; gap: .6rem;
            margin-bottom: .75rem;
        }
        .grade-inner-title {
            display: flex; align-items: center; gap: .55rem;
            font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
        }
        .grade-inner-ico {
            width: 1.85rem; height: 1.85rem; border-radius: .65rem;
            display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem;
        }
        .subject-row {
            display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem;
            border-radius: .85rem; padding: .7rem .75rem; margin-bottom: .5rem;
            background: var(--bg-secondary); border: 1px solid var(--nova-glass-border);
        }
        .subject-row:last-child { margin-bottom: 0; }
        .student-grid { display: grid; gap: .45rem; }
        .student-pill {
            display: flex; align-items: center; gap: .55rem;
            border-radius: .8rem; padding: .45rem .55rem;
            background: var(--bg-secondary); border: 1px solid var(--nova-glass-border);
        }
        .student-av {
            width: 1.85rem; height: 1.85rem; border-radius: .6rem; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .65rem; font-weight: 800; color: #fff;
        }
        .grade-empty {
            border: 1px dashed var(--nova-glass-border);
            border-radius: .9rem; padding: 1.1rem .8rem; text-align: center;
            color: var(--text-secondary); font-size: .8rem;
        }
        .grade-roster { max-height: 17.5rem; overflow: auto; padding-right: .15rem; }
    </style>
</head>
<body class="min-h-screen" x-data="gestionHub()" x-init="init()">
    <div class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="hub-kicker text-[11px] font-bold uppercase tracking-[.28em]">Colegio</p>
                <h1 class="text-3xl font-extrabold tracking-tight">Gestión</h1>
                <p class="hub-muted mt-1 max-w-xl text-sm">Plantel, nómina y materias. Los cursos de un profesor eliminado quedan huérfanos para reasignarlos.</p>
                <div class="hub-nav mt-4">
                    <a href="{{ route('director.dashboard') }}">Resumen</a>
                    <span class="is-active">Gestión</span>
                </div>
            </div>
            @include('components.user-control-panel')
        </header>

        <div x-show="aiBusy" x-cloak class="mb-5 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-indigo-800">
                    <i class="fa-solid fa-wand-magic-sparkles mr-2"></i>
                    <span x-text="aiStatus"></span>
                </p>
                <span class="text-xs text-indigo-500" x-text="aiDetail"></span>
            </div>
            <div class="progress-bar"><span></span></div>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <button type="button" @click="openPanel('materias')" class="hub-card p-6 text-left" :class="panel === 'materias' && 'active'">
                <div class="mb-6 flex items-center justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><i class="fa-solid fa-book"></i></span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catálogo</span>
                </div>
                <p class="text-4xl font-extrabold tabular-nums" x-text="counts.materias">0</p>
                <p class="mt-1 text-sm font-medium text-slate-500">Materias</p>
            </button>
            <button type="button" @click="openPanel('courses')" class="hub-card p-6 text-left" :class="panel === 'courses' && 'active'">
                <div class="mb-6 flex items-center justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700"><i class="fa-solid fa-book-open"></i></span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Oferta</span>
                </div>
                <p class="text-4xl font-extrabold tabular-nums" x-text="counts.courses">0</p>
                <p class="mt-1 text-sm font-medium text-slate-500">Cursos</p>
            </button>
            <button type="button" @click="openPanel('teachers')" class="hub-card p-6 text-left" :class="panel === 'teachers' && 'active'">
                <div class="mb-6 flex items-center justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-cyan-100 text-cyan-700"><i class="fa-solid fa-chalkboard-user"></i></span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Plantel</span>
                </div>
                <p class="text-4xl font-extrabold tabular-nums" x-text="counts.teachers">0</p>
                <p class="mt-1 text-sm font-medium text-slate-500">Profesores</p>
                <p class="mt-2 text-xs text-slate-400"><span x-text="counts.teachers_active"></span> activos · <span x-text="counts.teachers_pending"></span> pendientes</p>
            </button>
            <button type="button" @click="openPanel('students')" class="hub-card p-6 text-left" :class="panel === 'students' && 'active'">
                <div class="mb-6 flex items-center justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700"><i class="fa-solid fa-user-graduate"></i></span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nómina</span>
                </div>
                <p class="text-4xl font-extrabold tabular-nums" x-text="counts.students">0</p>
                <p class="mt-1 text-sm font-medium text-slate-500">Alumnos</p>
            </button>
        </div>

        <section class="hub-card p-5 md:p-6" style="transform:none">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" x-text="panelTitle"></h2>
                    <p class="text-sm text-slate-500" x-text="panelHint"></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="hub-select-all">
                        <input type="checkbox" class="h-4 w-4 accent-indigo-600" :checked="allVisibleSelected" @change="toggleSelectAll($event.target.checked)">
                        Seleccionar todo
                    </label>
                    <button type="button" class="hub-btn hub-btn-danger" x-show="selectedIds.length" x-cloak @click="bulkDelete()">
                        <i class="fa-solid fa-trash-can"></i>
                        Eliminar (<span x-text="selectedIds.length"></span>)
                    </button>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                        <input class="hub-input w-64 pl-9" x-model="query" placeholder="Buscar por nombre, grado, materia…">
                    </div>
                    <button type="button" class="hub-btn hub-btn-solid" @click="openCreate()">
                        <i class="fa-solid fa-plus"></i>
                        <span x-text="createLabel"></span>
                    </button>
                </div>
            </div>

            <div x-show="panel === 'teachers' && groupedDragCourses.length" class="mb-4 flex flex-wrap gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400 self-center">Arrastra una materia:</span>
                <template x-for="group in groupedDragCourses" :key="'drag-'+group.name">
                    <span class="hub-chip cursor-grab" draggable="true"
                          @dragstart="draggingCourseIds = group.ids"
                          @dragend="draggingCourseIds = []"
                          x-text="group.label"></span>
                </template>
            </div>

            <div class="hub-table-wrap">
                <table class="hub-table" x-show="panel === 'teachers'">
                    <thead><tr>
                        <th class="w-10"></th>
                        <th>Docente</th><th>Estado</th><th>Cursos</th><th></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="row in filteredPeople" :key="row.kind+row.id">
                            <tr class="hub-row"
                                :class="[highlights[row.kind+row.id] && 'just-in', selected?.key === row.kind+row.id && 'bg-indigo-50']"
                                @dblclick="select(row)"
                                @dragover.prevent="draggingCourseIds.length && ($event.currentTarget.classList.add('drop-over'))"
                                @dragleave="$event.currentTarget.classList.remove('drop-over')"
                                @drop.prevent="dropCourse(row, $event)">
                                <td><input type="checkbox" class="h-4 w-4 accent-indigo-600" :checked="isSelected(row.kind, row.id)" @click.stop @change="toggleSelected(row.kind, row.id)"></td>
                                <td>
                                    <p class="font-semibold" x-text="row.name"></p>
                                    <p class="text-xs text-slate-400" x-text="row.email || row.invite_code || 'Sin correo'"></p>
                                </td>
                                <td>
                                    <span class="hub-chip" :style="row.status === 'activo' ? 'background:#ecfdf5;color:#047857' : ''" x-text="row.status"></span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="chip in groupedTeacherCourses(row)" :key="chip.name">
                                            <span class="hub-chip" x-text="chip.label"></span>
                                        </template>
                                        <span class="text-xs text-slate-400" x-show="!row.courses.length">Sin cursos</span>
                                    </div>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button class="hub-btn hub-btn-ghost !py-1.5 !text-xs" x-show="row.kind === 'invite'" @click="openShare(row)"><i class="fa-solid fa-share-nodes"></i> Compartir</button>
                                    <button class="hub-btn hub-btn-ghost !py-1.5 !text-xs" @click="select(row)"><i class="fa-solid fa-link"></i> Asignar</button>
                                    <button class="hub-btn hub-btn-danger !py-1.5 !text-xs" @click="queueDelete(row)"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <table class="hub-table" x-show="panel === 'students'" x-cloak>
                    <thead><tr>
                        <th class="w-10"></th>
                        <th>Alumno</th><th>Grado</th><th>Sección</th><th>Cursos</th><th></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="row in filteredStudents" :key="'s'+row.id">
                            <tr class="hub-row" :class="highlights['student'+row.id] && 'just-in'">
                                <td><input type="checkbox" class="h-4 w-4 accent-indigo-600" :checked="isSelected('student', row.id)" @change="toggleSelected('student', row.id)"></td>
                                <td @dblclick="startEdit(row, 'name')"><span x-show="editKey !== 's'+row.id+'.name'" x-text="row.name"></span>
                                    <input x-show="editKey === 's'+row.id+'.name'" x-cloak class="hub-input" x-model="editValue" @keydown.enter="saveEdit(row, 'name')" @blur="saveEdit(row, 'name')"></td>
                                <td @dblclick="startEdit(row, 'grade')"><span x-show="editKey !== 's'+row.id+'.grade'" x-text="row.grade || '—'"></span>
                                    <input x-show="editKey === 's'+row.id+'.grade'" x-cloak class="hub-input w-24" x-model="editValue" @keydown.enter="saveEdit(row, 'grade')" @blur="saveEdit(row, 'grade')"></td>
                                <td @dblclick="startEdit(row, 'section')"><span x-show="editKey !== 's'+row.id+'.section'" x-text="row.section || '—'"></span>
                                    <input x-show="editKey === 's'+row.id+'.section'" x-cloak class="hub-input w-20" x-model="editValue" @keydown.enter="saveEdit(row, 'section')" @blur="saveEdit(row, 'section')"></td>
                                <td><span class="text-sm text-slate-500" x-text="row.courses_count + ' curso(s)'"></span></td>
                                <td class="text-right whitespace-nowrap">
                                    <button class="hub-btn hub-btn-ghost !py-1.5 !text-xs" @click="openFamilyShare(row)"><i class="fa-solid fa-share-nodes"></i> Familia</button>
                                    <button class="hub-btn hub-btn-danger !py-1.5 !text-xs" @click="queueDelete(row, 'student')"><i class="fa-solid fa-trash-can"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <table class="hub-table" x-show="panel === 'materias'" x-cloak>
                    <thead><tr>
                        <th class="w-10"></th>
                        <th>Materia</th><th>Cursos asociados</th><th></th>
                    </tr></thead>
                    <tbody>
                        <template x-for="row in filteredMaterias" :key="'m'+row.id">
                            <tr class="hub-row" :class="highlights['materia'+row.id] && 'just-in'">
                                <td><input type="checkbox" class="h-4 w-4 accent-indigo-600" :checked="isSelected('materia', row.id)" @change="toggleSelected('materia', row.id)"></td>
                                <td class="font-semibold" x-text="row.name"></td>
                                <td><span class="text-sm text-slate-500" x-text="row.courses_count + ' curso(s)'"></span></td>
                                <td class="text-right"><button class="hub-btn hub-btn-danger !py-1.5 !text-xs" @click="queueDelete(row, 'materia')"><i class="fa-solid fa-trash-can"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div x-show="panel === 'courses'" x-cloak class="p-4">
                    <div class="grade-dossier grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="card in gradeCards" :key="card.key">
                            <article class="grade-card" :class="expandedGrade === card.key && 'is-open sm:col-span-2 xl:col-span-3'" :style="'background:' + card.soft">
                                <button type="button" class="w-full p-5 text-left" @click="expandedGrade = expandedGrade === card.key ? null : card.key">
                                    <div class="mb-4 flex items-center justify-between">
                                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl text-lg font-black text-white shadow-sm" :style="'background:' + card.color" x-text="card.short"></span>
                                        <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            <span x-text="card.subjects.length + ' materias'"></span>
                                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="expandedGrade === card.key && 'rotate-180'"></i>
                                        </span>
                                    </div>
                                    <p class="text-xl font-extrabold text-slate-800" x-text="card.label"></p>
                                    <p class="mt-1 text-sm text-slate-600" x-text="gradePulse(card)"></p>
                                    <div class="mt-3 flex flex-wrap gap-1.5" x-show="card.subjects.length">
                                        <template x-for="subject in card.subjects.slice(0, 3)" :key="card.key + 'prev' + subject.name">
                                            <span class="grade-chip" x-text="subject.name"></span>
                                        </template>
                                        <span class="grade-chip" x-show="card.subjects.length > 3" x-text="'+' + (card.subjects.length - 3)"></span>
                                    </div>
                                </button>
                                <div x-show="expandedGrade === card.key" x-cloak class="grade-expand">
                                    <div class="grade-stats">
                                        <div class="grade-stat">
                                            <b x-text="card.subjects.length">0</b>
                                            <span>Materias</span>
                                        </div>
                                        <div class="grade-stat">
                                            <b x-text="card.teacherCount">0</b>
                                            <span>Docentes</span>
                                        </div>
                                        <div class="grade-stat">
                                            <b x-text="card.studentCount">0</b>
                                            <span>Alumnos</span>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <section class="grade-inner">
                                            <div class="grade-inner-head">
                                                <p class="grade-inner-title">
                                                    <span class="grade-inner-ico" :style="'background:' + card.color"><i class="fa-solid fa-book-open"></i></span>
                                                    Materias
                                                </p>
                                                <span class="hub-chip" x-text="card.subjects.length"></span>
                                            </div>
                                            <div class="grade-empty" x-show="!card.subjects.length">
                                                <i class="fa-regular fa-folder-open mb-2 block text-lg"></i>
                                                Todavía no hay materias en este grado.
                                            </div>
                                            <div class="grade-roster" x-show="card.subjects.length">
                                                <template x-for="subject in card.subjects" :key="card.key + subject.name">
                                                    <div class="mb-3 last:mb-0">
                                                        <div class="mb-1.5 flex items-center justify-between gap-2 px-0.5">
                                                            <p class="text-sm font-extrabold" x-text="subject.name"></p>
                                                            <button type="button" class="text-[11px] font-semibold text-rose-500 hover:text-rose-700" @click.stop="deleteSubject(subject.name, card.grade)">
                                                                Borrar materia
                                                            </button>
                                                        </div>
                                                        <template x-for="item in subject.items" :key="item.id">
                                                            <div class="subject-row">
                                                                <label class="flex min-w-0 flex-1 items-start gap-2">
                                                                    <input type="checkbox" class="mt-1 h-4 w-4 accent-indigo-600" :checked="isSelected('course', item.id)" @change="toggleSelected('course', item.id)">
                                                                    <span class="min-w-0">
                                                                        <span class="flex items-center gap-2">
                                                                            <span class="student-av" :style="'background:' + (item.orphan ? '#f97316' : card.color)" x-text="initials(item.teacher_name || subject.name)"></span>
                                                                            <span class="min-w-0">
                                                                                <span class="block truncate text-sm font-bold" x-text="item.teacher_name || 'Sin docente asignado'"></span>
                                                                                <span class="block text-[11px] text-slate-500">
                                                                                    <span x-text="item.section ? 'Sección ' + item.section : 'Sección única'"></span>
                                                                                    <span> · </span>
                                                                                    <span x-text="(item.students_count || 0) + ' alumnos'"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                        <span x-show="item.orphan" class="hub-chip mt-2" style="background:#fff7ed;color:#c2410c">Huérfano</span>
                                                                    </span>
                                                                </label>
                                                                <div class="flex shrink-0 items-center gap-1">
                                                                    <button type="button" class="hub-btn hub-btn-ghost !px-2 !py-1 !text-[11px]" @click.stop="selectCourse(item)" title="Ver alumnos de esta materia">
                                                                        Ver
                                                                    </button>
                                                                    <button type="button" class="shrink-0 text-rose-400 hover:text-rose-600" @click.stop="queueDelete(item, 'course')" title="Borrar este curso">
                                                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </section>

                                        <section class="grade-inner">
                                            <div class="grade-inner-head">
                                                <p class="grade-inner-title">
                                                    <span class="grade-inner-ico" :style="'background:' + card.color"><i class="fa-solid fa-user-graduate"></i></span>
                                                    Alumnos
                                                </p>
                                                <span class="hub-chip" x-text="card.studentCount"></span>
                                            </div>
                                            <div class="grade-empty" x-show="!card.roster.length">
                                                <i class="fa-regular fa-user mb-2 block text-lg"></i>
                                                Nadie en la nómina de este grado todavía.
                                            </div>
                                            <div class="grade-roster student-grid" x-show="card.roster.length">
                                                <template x-for="student in card.roster" :key="'grst'+student.id">
                                                    <div class="student-pill">
                                                        <span class="student-av" :style="'background:' + card.color" x-text="initials(student.name)"></span>
                                                        <span class="min-w-0">
                                                            <span class="block truncate text-sm font-bold" x-text="student.name"></span>
                                                            <span class="block text-[11px] text-slate-500" x-text="student.section ? 'Sección ' + student.section : 'Nómina del grado'"></span>
                                                        </span>
                                                    </div>
                                                </template>
                                            </div>
                                        </section>
                                    </div>

                                    <button type="button" class="mt-3 text-xs font-semibold text-rose-500" x-show="card.courses.length" @click="deleteGradeCourses(card)">
                                        Eliminar todo el grado
                                    </button>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>
            </div>
            <p class="px-2 py-4 text-sm text-slate-400" x-show="emptyState" x-text="emptyCopy"></p>
        </section>
    </div>

    {{-- Drawer asignación --}}
    <div x-show="selected" x-cloak class="fixed inset-0 z-40 flex justify-end bg-slate-900/20 backdrop-blur-sm" @click.self="selected = null">
        <aside class="hub-drawer h-full w-full max-w-md overflow-y-auto p-6 shadow-2xl" @click.stop>
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Perfil</p>
                    <h3 class="text-xl font-extrabold" x-text="selected?.name"></h3>
                    <p class="text-sm text-slate-500" x-text="selected?.invite_code || selected?.email || ''"></p>
                </div>
                <button class="hub-btn hub-btn-ghost !px-3" @click="selected = null"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="mb-5 rounded-2xl border p-4" style="border-color:var(--nova-glass-border)" x-show="selected?.kind === 'invite'">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Invitación</p>
                <p class="mb-1 text-sm font-semibold" x-text="'Código: ' + (selected?.invite_code || '')"></p>
                <p class="mb-3 break-all text-xs text-slate-500" x-text="registrationLink(selected) || 'Generando link de registro…'"></p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="hub-btn hub-btn-ghost !py-1.5 !text-xs" @click="copyText(selected?.invite_code, 'Código copiado.')">Copiar código</button>
                    <button type="button" class="hub-btn hub-btn-ghost !py-1.5 !text-xs" x-show="registrationLink(selected)" @click="copyRegistrationLink(selected)">Copiar link</button>
                    <button type="button" class="hub-btn hub-btn-solid !py-1.5 !text-xs" x-show="selected?.email" @click="resendInvite(selected)">Reenviar email</button>
                </div>
            </div>
            <div class="mb-5">
                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">Cursos que imparte</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="chip in (selected?.courses || [])" :key="'sel'+chip.id">
                        <span class="hub-chip" x-text="chip.label"></span>
                    </template>
                    <span class="text-sm text-slate-400" x-show="!(selected?.courses || []).length">Todavía no tiene cursos.</span>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                <p class="mb-3 text-sm font-bold">Asignar cursos</p>
                <select class="hub-input mb-3" x-model="gradeFilter">
                    <option value="">Todos los grados</option>
                    <template x-for="g in grades" :key="g"><option :value="g" x-text="g"></option></template>
                </select>
                <select class="hub-input mb-3" @change="addTag($event.target.value); $event.target.value=''">
                    <option value="">Elegir curso…</option>
                    <template x-for="course in assignableCourses" :key="'opt'+course.id">
                        <option :value="course.id" x-text="course.label"></option>
                    </template>
                </select>
                <div class="mb-4 flex flex-wrap gap-2">
                    <template x-for="id in pendingTags" :key="'tag'+id">
                        <span class="hub-chip">
                            <span x-text="courseLabel(id)"></span>
                            <button type="button" @click="pendingTags = pendingTags.filter(x => x !== id)"><i class="fa-solid fa-xmark"></i></button>
                        </span>
                    </template>
                </div>
                <button class="hub-btn hub-btn-solid w-full justify-center" :disabled="!pendingTags.length" @click="saveTags()">
                    <i class="fa-solid fa-check"></i> Guardar asignaciones
                </button>
            </div>
        </aside>
    </div>

    {{-- Course people drawer --}}
    <div x-show="selectedCourse" x-cloak class="fixed inset-0 z-40 flex justify-end bg-slate-900/20 backdrop-blur-sm" @click.self="selectedCourse = null">
        <aside class="hub-drawer h-full w-full max-w-md overflow-y-auto p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-violet-500">Curso</p>
                    <h3 class="text-xl font-extrabold" x-text="selectedCourse?.label"></h3>
                    <p class="text-sm text-slate-500" x-text="selectedCourse?.teacher_name || 'Sin docente'"></p>
                </div>
                <button class="hub-btn hub-btn-ghost !px-3" @click="selectedCourse = null"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p class="mb-2 text-xs font-bold uppercase text-slate-400">Alumnos</p>
            <ul class="space-y-2">
                <template x-for="st in (selectedCourse?.students || [])" :key="'cs'+st.id">
                    <li class="rounded-xl border border-slate-100 px-3 py-2 text-sm" x-text="st.name + ' · ' + (st.grade || '')"></li>
                </template>
                <li class="text-sm text-slate-400" x-show="!(selectedCourse?.students || []).length">Nadie inscrito todavía.</li>
            </ul>
        </aside>
    </div>

    {{-- Create modal --}}
    <div x-show="creating" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4 py-6" @keydown.escape.window="creating = false">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl p-6 shadow-2xl" style="background:var(--bg-card);color:var(--text-primary)">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" x-text="createTitle"></h3>
                <button class="hub-btn hub-btn-ghost !px-3" @click="creating = false"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="space-y-3" @submit.prevent="submitCreate()">
                <template x-if="panel === 'materias'">
                    <div class="space-y-3">
                        <label class="text-xs font-bold uppercase tracking-wide text-slate-400">Nombre de la materia</label>
                        <input class="hub-input" x-model="form.name" required placeholder="Ej. Biología">
                        <p class="text-xs text-slate-400">Solo el catálogo. Los grados y secciones se crean después como cursos.</p>
                    </div>
                </template>
                <template x-if="panel === 'teachers'">
                    <div class="space-y-3">
                        <input class="hub-input" x-model="form.name" required placeholder="Nombre del docente">
                        <input class="hub-input" x-model="form.email" type="email" placeholder="Correo (recomendado para enviar el enlace)">
                        <p class="text-xs text-slate-400">Con el correo se envía el link de activación. Sin correo, comparte el código DOC-.</p>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Asignar cursos existentes</p>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-2xl border p-2" style="border-color:var(--nova-glass-border)">
                            <p class="px-1 py-6 text-center text-sm text-slate-400" x-show="!courses.length">Primero crea la oferta de cursos.</p>
                            <template x-for="course in courses" :key="'pick-t'+course.id">
                                <label class="hub-pick flex items-center justify-between gap-2" :class="!course.orphan && 'disabled'">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <input type="checkbox" class="h-4 w-4 accent-indigo-600" :disabled="!course.orphan"
                                               :checked="form.course_ids.includes(course.id)"
                                               @change="toggleFormCourse(course.id, $event.target.checked)">
                                        <span class="truncate text-sm font-semibold" x-text="course.label"></span>
                                    </span>
                                    <span class="shrink-0 text-xs font-bold" :style="course.orphan ? 'color:#dc2626' : 'color:#059669'"
                                          x-text="course.orphan ? 'Sin profesor' : ('Asignado · ' + (course.teacher_name || 'ocupado'))"></span>
                                </label>
                            </template>
                        </div>
                        <p class="text-xs text-slate-500"><span x-text="form.course_ids.length"></span> curso(s) seleccionados. Los ocupados no se pueden tomar.</p>
                    </div>
                </template>
                <template x-if="panel === 'students'">
                    <div class="space-y-3">
                        <input class="hub-input" x-model="form.name" required placeholder="Nombre del alumno">
                        <div class="grid grid-cols-2 gap-2">
                            <select class="hub-input" x-model="form.grade">
                                <option value="">Todos los grados</option>
                                <template x-for="g in gradeMeta" :key="'fg'+g.key"><option :value="g.grade" x-text="g.label"></option></template>
                            </select>
                            <select class="hub-input" x-model="form.subject_filter">
                                <option value="">Todas las materias</option>
                                <template x-for="m in materias" :key="'fm'+m.id"><option :value="m.name" x-text="m.name"></option></template>
                            </select>
                        </div>
                        <select class="hub-input" x-model="form.sibling_student_id">
                            <option value="">Nueva familia (enlace propio)</option>
                            <template x-for="sib in students" :key="'sib'+sib.id">
                                <option :value="sib.id" x-text="'Hermano de ' + sib.name"></option>
                            </template>
                        </select>
                        <p class="text-xs text-slate-500">Si es hermano de alguien ya matriculado, comparte el mismo enlace familiar.</p>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Matricular en cursos</p>
                        <div class="max-h-56 space-y-2 overflow-y-auto rounded-2xl border p-2" style="border-color:var(--nova-glass-border)">
                            <p class="px-1 py-6 text-center text-sm text-slate-400" x-show="!studentCourseOptions.length">No hay cursos para ese filtro. Crea la oferta primero.</p>
                            <template x-for="course in studentCourseOptions" :key="'pick-s'+course.id">
                                <label class="hub-pick flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <input type="checkbox" class="h-4 w-4 accent-indigo-600"
                                               :checked="form.course_ids.includes(course.id)"
                                               @change="toggleFormCourse(course.id, $event.target.checked)">
                                        <span class="truncate text-sm font-semibold" x-text="course.label"></span>
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-500" x-text="course.teacher_name || 'Sin docente'"></span>
                                </label>
                            </template>
                        </div>
                        <p class="text-xs text-slate-500"><span x-text="form.course_ids.length"></span> curso(s) seleccionados.</p>
                    </div>
                </template>
                <template x-if="panel === 'courses'">
                    <div class="space-y-3">
                        <select class="hub-input" x-model="form.materia_id">
                            <option value="">Materia del catálogo…</option>
                            <template x-for="m in materias" :key="'cm'+m.id"><option :value="m.id" x-text="m.name"></option></template>
                        </select>
                        <input class="hub-input" x-model="form.subject_name" placeholder="O escribe una materia nueva">
                        <div class="grid grid-cols-2 gap-2">
                            <select class="hub-input" x-model="form.grade" required>
                                <option value="">Grado</option>
                                <template x-for="g in gradeMeta" :key="'cg'+g.key"><option :value="g.grade" x-text="g.label"></option></template>
                            </select>
                            <select class="hub-input" x-model="form.section">
                                <option value="">Sección</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                            </select>
                        </div>
                        <select class="hub-input" x-model="form.teacher_id">
                            <option value="">Profesor (opcional)</option>
                            <template x-for="row in people" :key="'pt'+row.kind+row.id">
                                <option :value="row.kind === 'teacher' ? row.id : ('invite:'+row.id)" x-text="row.name + (row.kind === 'invite' ? ' (pendiente)' : '')"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <div class="flex gap-2 pt-2">
                    <button type="button" class="hub-btn hub-btn-ghost flex-1 justify-center" @click="creating = false">Cancelar</button>
                    <button class="hub-btn hub-btn-solid flex-1 justify-center" :disabled="saving"><i class="fa-solid fa-check"></i> <span x-text="createAction"></span></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Share invite --}}
    <div x-show="inviteShare" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4 py-6" @keydown.escape.window="inviteShare = null">
        <div class="w-full max-w-lg rounded-3xl p-6 shadow-2xl" style="background:var(--bg-card);color:var(--text-primary)">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-500" x-text="inviteShare?.kind === 'family' ? 'Familia' : 'Profesor creado'"></p>
            <h3 class="mt-1 text-xl font-extrabold" x-text="inviteShare?.kind === 'family' ? ('Invitar a la familia de ' + (inviteShare?.name || '')) : ((inviteShare?.name || '') + ' listo')"></h3>
            <p class="mt-2 text-sm text-slate-500" x-show="inviteShare?.kind === 'family'">
                Comparte el enlace por WhatsApp. El representante se registra una vez y ve a todos los hermanos de esta familia.
            </p>
            <p class="mt-2 text-sm text-slate-500" x-show="inviteShare?.kind !== 'family' && inviteShare?.email" x-text="'Se envió un email de invitación a ' + inviteShare.email"></p>
            <p class="mt-2 text-sm text-slate-500" x-show="inviteShare?.kind !== 'family' && !inviteShare?.email">Sin correo: comparte el link de registro para que el profesor cree su cuenta.</p>
            <div class="mt-4 space-y-2" x-show="inviteShare?.kind === 'family' && (inviteShare?.students || []).length">
                <template x-for="kid in (inviteShare?.students || [])" :key="'kid'+kid.id">
                    <p class="text-sm font-semibold" x-text="kid.name + (kid.grade ? ' · ' + kid.grade : '')"></p>
                </template>
            </div>
            <div class="mt-4 space-y-3 rounded-2xl border p-4" style="border-color:var(--nova-glass-border)">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400" x-text="inviteShare?.kind === 'family' ? 'Código familiar' : 'Código de invitación'"></p>
                    <p class="mt-1 font-mono text-lg font-extrabold" x-text="inviteShare?.invite_code"></p>
                </div>
                <div x-show="shareLink(inviteShare)">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Link de registro</p>
                    <p class="mt-1 break-all text-sm text-indigo-600" x-text="shareLink(inviteShare)"></p>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" class="hub-btn hub-btn-ghost" x-show="shareLink(inviteShare)" @click="copyShareLink(inviteShare)">Copiar link</button>
                <button type="button" class="hub-btn hub-btn-ghost" @click="copyText(inviteShare?.invite_code, 'Código copiado.')">Copiar código</button>
                <a class="hub-btn hub-btn-solid" x-show="inviteShare?.kind === 'family' && whatsappLink(inviteShare)" :href="whatsappLink(inviteShare)" target="_blank" rel="noopener">WhatsApp</a>
                <button type="button" class="hub-btn hub-btn-solid" x-show="inviteShare?.kind !== 'family' && inviteShare?.email" @click="resendInvite(inviteShare)">Reenviar email</button>
                <button type="button" class="hub-btn hub-btn-ghost ml-auto" @click="inviteShare = null">Cerrar</button>
            </div>
        </div>
    </div>

    <div x-show="toast" x-cloak class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-xl">
        <span x-text="toast?.message"></span>
        <button class="ml-3 font-bold text-cyan-300" x-show="toast?.undo" @click="undoDelete()">Deshacer</button>
    </div>

    <script>
        function gestionHub() {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const routes = {
                snapshot: @json(route('director.gestion.snapshot')),
                teachers: @json(route('director.gestion.teachers.store')),
                resendInvite: (id) => @json(url('/director/gestion/teachers')).replace(/\/$/, '') + '/' + id + '/resend-invitation',
                students: @json(route('director.gestion.students.store')),
                familyInvite: (id) => @json(url('/director/gestion/students')).replace(/\/$/, '') + '/' + id + '/family-invite',
                student: (id) => @json(url('/director/gestion/students')).replace(/\/$/, '') + '/' + id,
                courses: @json(route('director.gestion.courses.store')),
                course: (id) => @json(url('/director/gestion/courses')).replace(/\/$/, '') + '/' + id,
                assign: @json(route('director.gestion.assign')),
                unassign: (id) => @json(url('/director/gestion/courses')).replace(/\/$/, '') + '/' + id + '/unassign',
                bulkDestroy: @json(route('director.gestion.bulk-destroy')),
                destroySubject: @json(route('director.gestion.courses.destroy-subject')),
                materias: @json(route('director.gestion.materias.store')),
                destroyMateria: (id) => @json(url('/director/gestion/materias')).replace(/\/$/, '') + '/' + id,
                destroyTeacher: (id) => @json(url('/director/profesores')).replace(/\/$/, '') + '/' + id,
                destroyInvite: (id) => @json(url('/director/profesores/invite')).replace(/\/$/, '') + '/' + id,
                destroyStudent: (id) => @json(url('/director/students')).replace(/\/$/, '') + '/' + id,
                destroyCourse: (id) => @json(url('/director/courses')).replace(/\/$/, '') + '/' + id,
            };
            const gradeMeta = [
                { key: '1', short: '1°', label: '1er grado', grade: '1ro', color: '#0ea5e9', soft: '#e0f2fe' },
                { key: '2', short: '2°', label: '2do grado', grade: '2do', color: '#8b5cf6', soft: '#ede9fe' },
                { key: '3', short: '3°', label: '3er grado', grade: '3ro', color: '#10b981', soft: '#d1fae5' },
                { key: '4', short: '4°', label: '4to grado', grade: '4to', color: '#f59e0b', soft: '#fef3c7' },
                { key: '5', short: '5°', label: '5to grado', grade: '5to', color: '#ec4899', soft: '#fce7f3' },
                { key: '6', short: '6°', label: '6to grado', grade: '6to', color: '#6366f1', soft: '#e0e7ff' },
            ];
            const gradeLabels = { 1: '1ro', 2: '2do', 3: '3ro', 4: '4to', 5: '5to', 6: '6to' };
            return {
                gradeMeta,
                panel: new URLSearchParams(location.search).get('panel') || 'materias',
                query: '',
                counts: { teachers: 0, teachers_active: 0, teachers_pending: 0, students: 0, courses: 0, materias: 0 },
                teachers: [], invites: [], students: [], courses: [], materias: [], grades: [],
                highlights: {},
                selected: null,
                selectedCourse: null,
                selectedIds: [],
                pendingTags: [],
                gradeFilter: '',
                expandedGrade: null,
                creating: false,
                inviteShare: null,
                schoolInviteCode: '',
                saving: false,
                form: {},
                editKey: '',
                editValue: '',
                toast: null,
                pendingDelete: null,
                draggingCourseIds: [],
                aiBusy: false,
                aiStatus: '',
                aiDetail: '',
                get people() { return [...this.invites, ...this.teachers]; },
                get panelTitle() {
                    return { teachers: 'Plantel docente', students: 'Alumnos', courses: 'Oferta por grado', materias: 'Catálogo de materias' }[this.panel] || 'Gestión';
                },
                get panelHint() {
                    if (this.panel === 'materias') return 'Las materias son el catálogo. No se crean desde el profesor.';
                    if (this.panel === 'courses') return 'Vista global del colegio por grado. Abre una tarjeta para ver materias con su docente y la nómina de alumnos.';
                    if (this.panel === 'teachers') return 'Invita al docente y asígnalo a cursos existentes. Si lo eliminas, los cursos quedan huérfanos.';
                    return 'Matricula al alumno en uno o varios cursos del mismo grado.';
                },
                get createLabel() {
                    return { teachers: 'Invitar profesor', students: 'Matricular alumno', courses: 'Crear curso', materias: 'Nueva materia' }[this.panel];
                },
                get createTitle() {
                    return { teachers: 'Nuevo profesor', students: 'Nuevo alumno', courses: 'Nuevo curso', materias: 'Nueva materia' }[this.panel];
                },
                get createAction() {
                    return { teachers: 'Crear profesor', students: 'Matricular alumno', courses: 'Crear curso', materias: 'Crear materia' }[this.panel];
                },
                get filteredPeople() {
                    const q = this.query.toLowerCase();
                    return this.people.filter(p => JSON.stringify(p).toLowerCase().includes(q));
                },
                get filteredStudents() {
                    const q = this.query.toLowerCase();
                    return this.students.filter(p => JSON.stringify(p).toLowerCase().includes(q));
                },
                get filteredCourses() {
                    const q = this.query.toLowerCase();
                    return this.courses.filter(p => JSON.stringify(p).toLowerCase().includes(q));
                },
                get filteredMaterias() {
                    const q = this.query.toLowerCase();
                    return this.materias.filter(p => JSON.stringify(p).toLowerCase().includes(q));
                },
                get studentCourseOptions() {
                    const grade = this.form.grade || '';
                    const subject = this.form.subject_filter || '';
                    return this.courses.filter((course) => {
                        if (grade && String(this.gradeNumber(course.grade)) !== String(this.gradeNumber(grade)) && course.grade !== grade) return false;
                        if (subject && course.subject_name !== subject) return false;
                        return true;
                    });
                },
                get visibleRows() {
                    if (this.panel === 'teachers') return this.filteredPeople.map(p => ({ kind: p.kind, id: p.id }));
                    if (this.panel === 'students') return this.filteredStudents.map(p => ({ kind: 'student', id: p.id }));
                    if (this.panel === 'materias') return this.filteredMaterias.map(p => ({ kind: 'materia', id: p.id }));
                    return this.filteredCourses.map(p => ({ kind: 'course', id: p.id }));
                },
                get allVisibleSelected() {
                    const rows = this.visibleRows;
                    return rows.length > 0 && rows.every(row => this.isSelected(row.kind, row.id));
                },
                get emptyState() {
                    if (this.panel === 'courses') return false;
                    if (this.panel === 'teachers') return this.filteredPeople.length === 0;
                    if (this.panel === 'materias') return this.filteredMaterias.length === 0;
                    return this.filteredStudents.length === 0;
                },
                get emptyCopy() { return this.query ? 'Nada coincide con esa búsqueda.' : 'Todavía no hay registros. Crea el primero o pídeselo a AulaSync.'; },
                get assignableCourses() {
                    const taken = new Set((this.selected?.courses || []).map(c => c.id).concat(this.pendingTags));
                    return this.courses.filter(c => c.orphan && !taken.has(c.id) && (!this.gradeFilter || String(this.gradeNumber(c.grade)) === String(this.gradeNumber(this.gradeFilter))));
                },
                get groupedDragCourses() {
                    return this.groupCourses(this.courses);
                },
                get gradeCards() {
                    const q = this.query.toLowerCase();
                    return gradeMeta.map((meta) => {
                        const courses = this.courses.filter((course) => {
                            if (String(this.gradeNumber(course.grade)) !== meta.key) return false;
                            return !q || JSON.stringify(course).toLowerCase().includes(q);
                        });
                        const subjects = {};
                        courses.forEach((course) => {
                            const name = course.subject_name || 'Materia';
                            const key = name.toLowerCase();
                            if (!subjects[key]) subjects[key] = { name, items: [] };
                            subjects[key].items.push(course);
                        });
                        const roster = this.students
                            .filter((student) => {
                                if (String(this.gradeNumber(student.grade)) !== meta.key) return false;
                                return !q || String(student.name || '').toLowerCase().includes(q);
                            })
                            .slice()
                            .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'es'));
                        const teacherNames = [...new Set(courses.map((course) => course.teacher_name).filter(Boolean))];
                        return {
                            ...meta,
                            courses,
                            subjects: Object.values(subjects),
                            roster,
                            teacherCount: teacherNames.length,
                            studentCount: roster.length,
                            orphanCount: courses.filter((course) => course.orphan).length,
                        };
                    });
                },
                async init() {
                    window.novaContext = { type: 'director_school', screen: 'gestion' };
                    window.AI_PAGE_CONTEXT = window.novaContext;
                    await this.refresh();
                    window.addEventListener('aula-sync-ai-busy', (e) => {
                        const n = e.detail?.count || null;
                        this.aiBusy = true;
                        this.aiStatus = n ? `Procesando solicitud: creando ${n} registros` : 'Procesando solicitud de AulaSync…';
                        this.aiDetail = e.detail?.prompt || '';
                    });
                    window.addEventListener('aula-sync-ai-idle', () => { this.aiBusy = false; });
                    window.addEventListener('aula-sync-roster-changed', async (e) => {
                        const before = {
                            teachers: new Set(this.people.map(p => p.kind + p.id)),
                            students: new Set(this.students.map(s => 'student' + s.id)),
                            courses: new Set(this.courses.map(c => 'course' + c.id)),
                        };
                        await this.refresh();
                        this.people.forEach(p => { if (!before.teachers.has(p.kind + p.id)) this.flash(p.kind + p.id); });
                        this.students.forEach(s => { if (!before.students.has('student' + s.id)) this.flash('student' + s.id); });
                        this.courses.forEach(c => { if (!before.courses.has('course' + c.id)) this.flash('course' + c.id); });
                        this.aiBusy = false;
                        if (e.detail?.cancelled) {
                            this.showToast(e.detail.message || 'Cancelado.');
                            return;
                        }
                        this.showToast(e.detail?.message || 'Cambios aplicados en la gestión.');
                    });
                    window.addEventListener('ai-canvas-refresh', () => this.refresh());
                },
                flash(key) {
                    this.highlights[key] = true;
                    setTimeout(() => { const copy = { ...this.highlights }; delete copy[key]; this.highlights = copy; }, 1800);
                },
                initials(name) {
                    const parts = String(name || '')
                        .trim()
                        .split(/\s+/)
                        .filter(Boolean)
                        .slice(0, 2);
                    if (!parts.length) return '—';
                    return parts.map((part) => part.charAt(0)).join('').toUpperCase();
                },
                gradePulse(card) {
                    const bits = [];
                    bits.push((card.studentCount || 0) + ' alumno' + (card.studentCount === 1 ? '' : 's'));
                    bits.push((card.teacherCount || 0) + ' docente' + (card.teacherCount === 1 ? '' : 's'));
                    if (card.orphanCount) bits.push(card.orphanCount + ' sin docente');
                    return bits.join(' · ');
                },
                gradeNumber(grade) {
                    const value = String(grade || '').toLowerCase();
                    if (!value) return 0;
                    if (/(primero|primer|1ero|1er|\b1ro\b|1°|1º)/.test(value)) return 1;
                    if (/(segundo|\b2do\b|2°|2º)/.test(value)) return 2;
                    if (/(tercero|tercer|3er|\b3ro\b|3°|3º)/.test(value)) return 3;
                    if (/(cuarto|\b4to\b|4°|4º)/.test(value)) return 4;
                    if (/(quinto|\b5to\b|5°|5º)/.test(value)) return 5;
                    if (/(sexto|\b6to\b|6°|6º)/.test(value)) return 6;
                    const digit = value.match(/[1-6]/);
                    return digit ? Number(digit[0]) : 0;
                },
                formatGradeRange(grades) {
                    const nums = [...new Set((grades || []).map((g) => this.gradeNumber(g)).filter((n) => n >= 1 && n <= 6))].sort((a, b) => a - b);
                    if (!nums.length) return (grades || []).filter(Boolean).join(', ') || '';
                    const consecutive = nums.length > 1 && nums.every((n, i) => i === 0 || n === nums[i - 1] + 1);
                    if (consecutive) return gradeLabels[nums[0]] + ' a ' + gradeLabels[nums[nums.length - 1]];
                    return nums.map((n) => gradeLabels[n]).join(', ');
                },
                groupCourses(list) {
                    const map = {};
                    (list || []).forEach((course) => {
                        const name = course.subject_name || 'Materia';
                        if (!map[name]) map[name] = { name, grades: [], ids: [] };
                        map[name].grades.push(course.grade);
                        map[name].ids.push(course.id);
                    });
                    return Object.values(map).map((group) => ({
                        name: group.name,
                        ids: group.ids,
                        label: group.name + (this.formatGradeRange(group.grades) ? ' · ' + this.formatGradeRange(group.grades) : ''),
                    }));
                },
                groupedTeacherCourses(row) {
                    return this.groupCourses(row.courses || []);
                },
                isSelected(kind, id) {
                    return this.selectedIds.some((item) => item.kind === kind && Number(item.id) === Number(id));
                },
                toggleSelected(kind, id) {
                    if (this.isSelected(kind, id)) {
                        this.selectedIds = this.selectedIds.filter((item) => !(item.kind === kind && Number(item.id) === Number(id)));
                        return;
                    }
                    this.selectedIds = [...this.selectedIds, { kind, id: Number(id) }];
                },
                toggleSelectAll(checked) {
                    const rows = this.visibleRows;
                    if (checked) {
                        const extra = rows.filter((row) => !this.isSelected(row.kind, row.id));
                        this.selectedIds = [...this.selectedIds, ...extra];
                        return;
                    }
                    const keys = new Set(rows.map((row) => row.kind + ':' + row.id));
                    this.selectedIds = this.selectedIds.filter((item) => !keys.has(item.kind + ':' + item.id));
                },
                async bulkDelete() {
                    if (!this.selectedIds.length) return;
                    if (!confirm('¿Eliminar lo seleccionado? Los cursos de un profesor quedan huérfanos para reasignarlos.')) return;
                    const payload = { teachers: [], invites: [], students: [], courses: [], materias: [] };
                    this.selectedIds.forEach((item) => {
                        if (item.kind === 'teacher') payload.teachers.push(item.id);
                        else if (item.kind === 'invite') payload.invites.push(item.id);
                        else if (item.kind === 'student') payload.students.push(item.id);
                        else if (item.kind === 'materia') payload.materias.push(item.id);
                        else payload.courses.push(item.id);
                    });
                    const json = await this.api('POST', routes.bulkDestroy, payload);
                    this.selectedIds = [];
                    await this.refresh();
                    this.showToast(json.message || 'Eliminación aplicada.');
                },
                async deleteSubject(name, grade) {
                    if (!confirm('¿Borrar ' + name + ' en ' + grade + '? Se eliminan sus secciones de este grado.')) return;
                    const json = await this.api('POST', routes.destroySubject, { subject_name: name, grade });
                    await this.refresh();
                    this.showToast(json.message || 'Materia eliminada.');
                },
                async deleteGradeCourses(card) {
                    const ids = (card.courses || []).map((course) => course.id);
                    if (!ids.length) return;
                    if (!confirm('¿Eliminar todas las materias de ' + card.label + '?')) return;
                    const json = await this.api('POST', routes.bulkDestroy, { courses: ids });
                    this.selectedIds = this.selectedIds.filter((item) => item.kind !== 'course' || !ids.includes(item.id));
                    await this.refresh();
                    this.showToast(json.message || 'Grado vaciado.');
                },
                openPanel(name) {
                    this.panel = name;
                    this.selected = null;
                    this.selectedCourse = null;
                    this.selectedIds = [];
                    this.expandedGrade = null;
                    history.replaceState(null, '', '?panel=' + name);
                },
                async refresh() {
                    const res = await fetch(routes.snapshot, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    this.counts = json.counts;
                    this.teachers = json.teachers;
                    this.invites = json.invites;
                    this.students = json.students;
                    this.courses = json.courses;
                    this.materias = json.materias || [];
                    this.grades = json.grades;
                    this.schoolInviteCode = json.school_invite_code || this.schoolInviteCode || '';
                    if (this.selected) {
                        const next = this.people.find(p => p.kind === this.selected.kind && p.id === this.selected.id);
                        if (next) this.selected = { ...next, key: next.kind + next.id };
                    }
                },
                select(row) {
                    this.selected = { ...row, key: row.kind + row.id };
                    this.pendingTags = [];
                    this.gradeFilter = '';
                },
                selectCourse(row) { this.selectedCourse = row; },
                addTag(id) {
                    const n = Number(id);
                    if (!n || this.pendingTags.includes(n)) return;
                    this.pendingTags.push(n);
                },
                courseLabel(id) { return this.courses.find(c => c.id === Number(id))?.label || id; },
                async saveTags() {
                    if (!this.selected) return;
                    await this.api('POST', routes.assign, {
                        teacher_id: this.selected.kind === 'teacher' ? this.selected.id : null,
                        invite_id: this.selected.kind === 'invite' ? this.selected.id : null,
                        course_ids: this.pendingTags,
                    });
                    this.pendingTags = [];
                    await this.refresh();
                    this.showToast('Cursos asignados.');
                },
                async dropCourse(row, event) {
                    event.currentTarget.classList.remove('drop-over');
                    const ids = (this.draggingCourseIds || []).map(Number).filter(Boolean);
                    this.draggingCourseIds = [];
                    if (!ids.length) return;
                    await this.api('POST', routes.assign, {
                        teacher_id: row.kind === 'teacher' ? row.id : null,
                        invite_id: row.kind === 'invite' ? row.id : null,
                        course_ids: ids,
                    });
                    await this.refresh();
                    this.showToast('Materia asignada.');
                },
                async unassign(courseId) {
                    await this.api('POST', routes.unassign(courseId), {});
                    await this.refresh();
                },
                openCreate() {
                    this.form = { name: '', email: '', subject_name: '', grade: '', section: 'A', materia_id: '', teacher_id: '', course_ids: [], subject_filter: '', sibling_student_id: '' };
                    this.creating = true;
                },
                toggleFormCourse(id, checked) {
                    const n = Number(id);
                    if (checked) {
                        if (!this.form.course_ids.includes(n)) this.form.course_ids.push(n);
                    } else {
                        this.form.course_ids = this.form.course_ids.filter((x) => x !== n);
                    }
                },
                async submitCreate() {
                    this.saving = true;
                    try {
                        if (this.panel === 'materias') await this.api('POST', routes.materias, { name: this.form.name });
                        if (this.panel === 'teachers') {
                            const json = await this.api('POST', routes.teachers, {
                                name: this.form.name,
                                email: this.form.email,
                                course_ids: this.form.course_ids,
                            });
                            this.inviteShare = json.invite || null;
                            this.showToast(json.message || 'Profesor creado.');
                        }
                        if (this.panel === 'students') {
                            const json = await this.api('POST', routes.students, {
                                name: this.form.name,
                                grade: this.form.grade,
                                course_ids: this.form.course_ids,
                                sibling_student_id: this.form.sibling_student_id || null,
                            });
                            this.inviteShare = json.family_invite || null;
                            this.showToast(json.message || 'Alumno matriculado.');
                        }
                        if (this.panel === 'courses') {
                            const raw = String(this.form.teacher_id || '');
                            const payload = {
                                materia_id: this.form.materia_id || null,
                                subject_name: this.form.subject_name || null,
                                grade: this.form.grade,
                                section: this.form.section,
                            };
                            if (raw.startsWith('invite:')) payload.invite_id = Number(raw.slice(7));
                            else if (raw) payload.teacher_id = Number(raw);
                            await this.api('POST', routes.courses, payload);
                        }
                        this.creating = false;
                        await this.refresh();
                        if (!this.inviteShare) this.showToast('Listo.');
                    } finally { this.saving = false; }
                },
                startEdit(row, field) { this.editKey = 's' + row.id + '.' + field; this.editValue = row[field] || ''; },
                async saveEdit(row, field) {
                    if (this.editKey !== 's' + row.id + '.' + field) return;
                    const value = this.editValue;
                    this.editKey = '';
                    if ((row[field] || '') === value) return;
                    await this.api('PATCH', routes.student(row.id), { [field]: value });
                    await this.refresh();
                },
                startCourseEdit(row, field) { this.editKey = 'c' + row.id + '.' + field; this.editValue = row[field] || ''; },
                async saveCourseEdit(row, field) {
                    if (this.editKey !== 'c' + row.id + '.' + field) return;
                    const value = this.editValue;
                    this.editKey = '';
                    if ((row[field] || '') === value) return;
                    await this.api('PATCH', routes.course(row.id), { [field]: value });
                    await this.refresh();
                },
                queueDelete(row, type) {
                    const kind = type || row.kind;
                    this.pendingDelete = { row, kind, timer: setTimeout(() => this.commitDelete(), 5000) };
                    this.toast = {
                        message: kind === 'teacher' || kind === 'invite'
                            ? 'Se eliminará en 5 segundos. Los cursos quedarán huérfanos para reasignar.'
                            : 'Se eliminará en 5 segundos.',
                        undo: true,
                    };
                },
                undoDelete() {
                    if (this.pendingDelete?.timer) clearTimeout(this.pendingDelete.timer);
                    this.pendingDelete = null;
                    this.toast = { message: 'Eliminación cancelada.', undo: false };
                    setTimeout(() => this.toast = null, 1800);
                },
                async commitDelete() {
                    const job = this.pendingDelete;
                    this.pendingDelete = null;
                    if (!job) return;
                    const { row, kind } = job;
                    let url;
                    if (kind === 'teacher') url = routes.destroyTeacher(row.id);
                    else if (kind === 'invite') url = routes.destroyInvite(row.id);
                    else if (kind === 'student') url = routes.destroyStudent(row.id);
                    else if (kind === 'materia') url = routes.destroyMateria(row.id);
                    else url = routes.destroyCourse(row.id);
                    await this.api('DELETE', url);
                    await this.refresh();
                    this.selected = null;
                    this.showToast('Eliminado.');
                },
                showToast(message) {
                    this.toast = { message, undo: false };
                    setTimeout(() => { if (this.toast && !this.toast.undo) this.toast = null; }, 2800);
                },
                openShare(row) {
                    this.inviteShare = row;
                },
                async openFamilyShare(row) {
                    const json = await this.api('GET', routes.familyInvite(row.id));
                    this.inviteShare = json.family_invite || null;
                },
                shareLink(row) {
                    const link = String(row?.invitation_link || '').trim();
                    if (/^https?:\/\//i.test(link)) return link;
                    if (row?.kind === 'family') {
                        const school = this.schoolInviteCode || row?.school_code;
                        const code = String(row?.invite_code || '').trim();
                        if (!school || !code) return '';
                        return `${window.location.origin}/familia/unirse?school=${encodeURIComponent(school)}&code=${encodeURIComponent(code)}`;
                    }
                    return this.registrationLink(row);
                },
                registrationLink(row) {
                    const link = String(row?.invitation_link || '').trim();
                    if (/^https?:\/\//i.test(link) && !String(link).includes('/familia/')) return link;
                    const school = this.schoolInviteCode;
                    const code = String(row?.invite_code || '').trim();
                    if (!school || !code || String(code).startsWith('FAM-')) return '';
                    return `${window.location.origin}/onboarding/profesor?school=${encodeURIComponent(school)}&code=${encodeURIComponent(code)}`;
                },
                copyShareLink(row) {
                    const link = this.shareLink(row);
                    if (!link) {
                        this.showToast('Aún no hay link para copiar.');
                        return;
                    }
                    this.copyText(link, 'Link copiado.');
                },
                whatsappLink(row) {
                    const link = this.shareLink(row);
                    if (!link) return '';
                    const names = (row?.students || []).map((s) => s.name).join(', ') || row?.name || 'tu hijo';
                    const text = `Hola, te invito a ver a ${names} en AulaSync. Entra aquí, crea tu cuenta y listo: ${link}`;
                    return 'https://wa.me/?text=' + encodeURIComponent(text);
                },
                copyRegistrationLink(row) {
                    const link = this.registrationLink(row);
                    if (!link) {
                        this.showToast('No hay link de registro.');
                        return;
                    }
                    this.copyText(link, 'Link de registro copiado.');
                },
                async copyText(value, label) {
                    if (!value) return;
                    try {
                        await navigator.clipboard.writeText(value);
                        this.showToast(label || 'Copiado.');
                    } catch {
                        this.showToast('No se pudo copiar.');
                    }
                },
                async resendInvite(row) {
                    if (!row?.id) return;
                    const json = await this.api('POST', routes.resendInvite(row.id), {});
                    if (json.invite) {
                        this.inviteShare = json.invite;
                        if (this.selected?.kind === 'invite' && this.selected.id === row.id) this.selected = json.invite;
                    }
                    await this.refresh();
                    this.showToast(json.message || 'Email reenviado.');
                },
                async api(method, url, body) {
                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    };
                    const res = await fetch(url, {
                        method,
                        headers,
                        body: method === 'DELETE' ? null : JSON.stringify(body || {}),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw this.fail(json);
                    return json;
                },
                fail(json) {
                    const msg = json.message || Object.values(json.errors || {}).flat()[0] || 'No se pudo completar.';
                    this.showToast(msg);
                    return new Error(msg);
                },
            };
        }
    </script>
    @include('components.ai-assistant-bubble')
</body>
</html>
