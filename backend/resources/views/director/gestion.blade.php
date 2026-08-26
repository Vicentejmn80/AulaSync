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
        body { font-family: Inter, system-ui, sans-serif; }
        [x-cloak] { display: none !important; }
        .hub-card {
            background: #fff;
            border: 1px solid #e8eef6;
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px -18px rgba(15, 23, 42, .25);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .hub-card:hover { transform: translateY(-2px); box-shadow: 0 18px 40px -20px rgba(79, 70, 229, .35); }
        .hub-card.active { border-color: #818cf8; box-shadow: 0 0 0 4px rgba(99,102,241,.12); }
        .hub-row { transition: background .15s ease, transform .25s ease, opacity .25s ease; }
        .hub-row.just-in { animation: hubIn .45s ease; }
        @keyframes hubIn {
            from { opacity: 0; transform: translateY(10px) scale(.98); }
            to { opacity: 1; transform: none; }
        }
        .hub-chip {
            display: inline-flex; align-items: center; gap: .35rem;
            border-radius: 999px; padding: .2rem .65rem;
            font-size: .72rem; font-weight: 600; background: #eef2ff; color: #3730a3;
        }
        .hub-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            border-radius: .9rem; padding: .65rem 1rem; font-weight: 700; font-size: .875rem;
            transition: background .15s ease, transform .15s ease, box-shadow .15s ease;
        }
        .hub-btn:hover { transform: translateY(-1px); }
        .hub-btn-solid { background: #4f46e5; color: #fff; box-shadow: 0 8px 18px -10px #4f46e5; }
        .hub-btn-solid:hover { background: #4338ca; }
        .hub-btn-ghost { border: 1px solid #dbe3ef; background: #fff; color: #334155; }
        .hub-btn-ghost:hover { background: #f8fafc; }
        .hub-btn-danger { border: 1px solid #fecaca; color: #b91c1c; background: #fff; }
        .hub-btn-danger:hover { background: #fef2f2; }
        .hub-input {
            width: 100%; border: 1px solid #dbe3ef; border-radius: .9rem; padding: .7rem .9rem;
            background: #fff; font-size: .9rem;
        }
        .hub-input:focus { outline: none; border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .hub-table-wrap { overflow: auto; border-radius: 1.1rem; border: 1px solid #e8eef6; background: #fff; }
        table.hub-table { width: 100%; border-collapse: collapse; }
        table.hub-table th { text-align: left; font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: #64748b; padding: .85rem 1rem; border-bottom: 1px solid #eef2f7; }
        table.hub-table td { padding: .85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: .9rem; }
        .drop-over { outline: 2px dashed #6366f1; background: #eef2ff; }
        .progress-bar { height: 4px; border-radius: 99px; overflow: hidden; background: #e0e7ff; }
        .progress-bar > span { display: block; height: 100%; background: linear-gradient(90deg,#6366f1,#22d3ee); animation: load 1.2s ease infinite; }
        @keyframes load { 0% { width: 12%; } 50% { width: 78%; } 100% { width: 12%; } }
    </style>
</head>
<body class="min-h-screen bg-[#f6f8fc] text-slate-900" x-data="gestionHub()" x-init="init()">
    <div class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
        <header class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.28em] text-indigo-500">Colegio</p>
                <h1 class="text-3xl font-extrabold tracking-tight">Gestión</h1>
                <p class="mt-1 max-w-xl text-sm text-slate-500">Plantel, nómina y materias. Los cursos de un profesor eliminado quedan huérfanos para reasignarlos.</p>
                <div class="mt-4 inline-flex rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
                    <a href="{{ route('director.dashboard') }}" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-50">Resumen</a>
                    <span class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Gestión</span>
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

        <div class="mb-6 grid gap-4 md:grid-cols-3">
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
            <button type="button" @click="openPanel('courses')" class="hub-card p-6 text-left" :class="panel === 'courses' && 'active'">
                <div class="mb-6 flex items-center justify-between">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700"><i class="fa-solid fa-book-open"></i></span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Estructura</span>
                </div>
                <p class="text-4xl font-extrabold tabular-nums" x-text="counts.courses">0</p>
                <p class="mt-1 text-sm font-medium text-slate-500">Cursos</p>
            </button>
        </div>

        <section class="hub-card p-5 md:p-6" style="transform:none">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold" x-text="panelTitle"></h2>
                    <p class="text-sm text-slate-500" x-text="panelHint"></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600">
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
                                <td class="text-right"><button class="hub-btn hub-btn-danger !py-1.5 !text-xs" @click="queueDelete(row, 'student')"><i class="fa-solid fa-trash-can"></i></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div x-show="panel === 'courses'" x-cloak class="p-4">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="card in gradeCards" :key="card.key">
                            <article class="overflow-hidden rounded-2xl border border-slate-100 shadow-sm" :style="'background:' + card.soft">
                                <button type="button" class="w-full p-5 text-left" @click="expandedGrade = expandedGrade === card.key ? null : card.key">
                                    <div class="mb-4 flex items-center justify-between">
                                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl text-lg font-black text-white" :style="'background:' + card.color" x-text="card.short"></span>
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="card.subjects.length + ' materia(s)'"></span>
                                    </div>
                                    <p class="text-xl font-extrabold text-slate-800" x-text="card.label"></p>
                                    <p class="mt-1 text-sm text-slate-500" x-text="card.orphanCount ? card.orphanCount + ' sin docente' : 'Listo para desglosar'"></p>
                                </button>
                                <div x-show="expandedGrade === card.key" x-cloak class="border-t border-white/70 bg-white/70 px-4 py-3">
                                    <p class="mb-2 text-xs text-slate-400" x-show="!card.subjects.length">Todavía no hay materias en este grado.</p>
                                    <template x-for="subject in card.subjects" :key="card.key + subject.name">
                                        <div class="mb-3 rounded-xl bg-white p-3 shadow-sm">
                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                <p class="font-bold text-slate-800" x-text="subject.name"></p>
                                                <button type="button" class="text-xs font-semibold text-rose-500 hover:text-rose-700" @click.stop="deleteSubject(subject.name, card.grade)">
                                                    Borrar materia
                                                </button>
                                            </div>
                                            <ul class="space-y-1">
                                                <template x-for="item in subject.items" :key="item.id">
                                                    <li class="flex items-center justify-between gap-2 rounded-lg px-1 py-1 text-sm">
                                                        <label class="flex min-w-0 flex-1 items-center gap-2">
                                                            <input type="checkbox" class="h-4 w-4 accent-indigo-600" :checked="isSelected('course', item.id)" @change="toggleSelected('course', item.id)">
                                                            <span class="min-w-0 truncate">
                                                                <span x-text="item.section ? 'Sección ' + item.section : 'Sección única'"></span>
                                                                <span class="text-slate-400"> · </span>
                                                                <span x-text="item.teacher_name || 'Sin docente'"></span>
                                                            </span>
                                                            <span x-show="item.orphan" class="hub-chip shrink-0" style="background:#fff7ed;color:#c2410c">Huérfano</span>
                                                        </label>
                                                        <button type="button" class="shrink-0 text-rose-400 hover:text-rose-600" @click.stop="queueDelete(item, 'course')" title="Borrar este curso">
                                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </template>
                                    <button type="button" class="mt-1 text-xs font-semibold text-rose-500" x-show="card.courses.length" @click="deleteGradeCourses(card)">
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
        <aside class="h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl" @click.stop>
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Perfil</p>
                    <h3 class="text-xl font-extrabold" x-text="selected?.name"></h3>
                    <p class="text-sm text-slate-500" x-text="selected?.invite_code || selected?.email || ''"></p>
                </div>
                <button class="hub-btn hub-btn-ghost !px-3" @click="selected = null"><i class="fa-solid fa-xmark"></i></button>
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
        <aside class="h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
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
    <div x-show="creating" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/30 px-4" @keydown.escape.window="creating = false">
        <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" x-text="'Nuevo ' + (panel === 'teachers' ? 'profesor' : panel === 'students' ? 'alumno' : 'curso')"></h3>
                <button class="hub-btn hub-btn-ghost !px-3" @click="creating = false"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form class="space-y-3" @submit.prevent="submitCreate()">
                <template x-if="panel === 'teachers'">
                    <div class="space-y-3">
                        <input class="hub-input" x-model="form.name" required placeholder="Nombre del docente">
                        <input class="hub-input" x-model="form.email" type="email" placeholder="Correo (opcional)">
                        <input class="hub-input" x-model="form.subject_name" placeholder="Materia (opcional)">
                        <input class="hub-input" x-model="form.grade" placeholder="Grado, ej. 1ro">
                    </div>
                </template>
                <template x-if="panel === 'students'">
                    <div class="space-y-3">
                        <input class="hub-input" x-model="form.name" required placeholder="Nombre del alumno">
                        <div class="grid grid-cols-2 gap-2">
                            <input class="hub-input" x-model="form.grade" placeholder="Grado">
                            <input class="hub-input" x-model="form.section" placeholder="Sección">
                        </div>
                    </div>
                </template>
                <template x-if="panel === 'courses'">
                    <div class="space-y-3">
                        <input class="hub-input" x-model="form.subject_name" required placeholder="Materia">
                        <div class="grid grid-cols-2 gap-2">
                            <input class="hub-input" x-model="form.grade" required placeholder="Grado">
                            <input class="hub-input" x-model="form.section" placeholder="Sección">
                        </div>
                    </div>
                </template>
                <div class="flex gap-2 pt-2">
                    <button type="button" class="hub-btn hub-btn-ghost flex-1 justify-center" @click="creating = false">Cancelar</button>
                    <button class="hub-btn hub-btn-solid flex-1 justify-center" :disabled="saving"><i class="fa-solid fa-check"></i> Crear</button>
                </div>
            </form>
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
                students: @json(route('director.gestion.students.store')),
                student: (id) => @json(url('/director/gestion/students')).replace(/\/$/, '') + '/' + id,
                courses: @json(route('director.gestion.courses.store')),
                course: (id) => @json(url('/director/gestion/courses')).replace(/\/$/, '') + '/' + id,
                assign: @json(route('director.gestion.assign')),
                unassign: (id) => @json(url('/director/gestion/courses')).replace(/\/$/, '') + '/' + id + '/unassign',
                bulkDestroy: @json(route('director.gestion.bulk-destroy')),
                destroySubject: @json(route('director.gestion.courses.destroy-subject')),
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
                panel: new URLSearchParams(location.search).get('panel') || 'teachers',
                query: '',
                counts: { teachers: 0, teachers_active: 0, teachers_pending: 0, students: 0, courses: 0 },
                teachers: [], invites: [], students: [], courses: [], grades: [],
                highlights: {},
                selected: null,
                selectedCourse: null,
                selectedIds: [],
                pendingTags: [],
                gradeFilter: '',
                expandedGrade: null,
                creating: false,
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
                    return this.panel === 'teachers' ? 'Plantel docente' : this.panel === 'students' ? 'Alumnos' : 'Oferta por grado';
                },
                get panelHint() {
                    if (this.panel === 'courses') return 'Seis tarjetas, una por grado. Ábrelas para ver secciones, borrar una materia o dejar un curso huérfano listo para reasignar.';
                    if (this.panel === 'teachers') return 'Si eliminas un profesor, sus cursos quedan huérfanos (sin docente) para reasignarlos. Arrastra una materia agrupada sobre el docente.';
                    return 'Doble clic para editar. Usa seleccionar todo para borrar la nómina visible.';
                },
                get createLabel() {
                    return this.panel === 'teachers' ? 'Invitar profesor' : this.panel === 'students' ? 'Matricular alumno' : 'Crear curso';
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
                get visibleRows() {
                    if (this.panel === 'teachers') return this.filteredPeople.map(p => ({ kind: p.kind, id: p.id }));
                    if (this.panel === 'students') return this.filteredStudents.map(p => ({ kind: 'student', id: p.id }));
                    return this.filteredCourses.map(p => ({ kind: 'course', id: p.id }));
                },
                get allVisibleSelected() {
                    const rows = this.visibleRows;
                    return rows.length > 0 && rows.every(row => this.isSelected(row.kind, row.id));
                },
                get emptyState() {
                    if (this.panel === 'courses') return false;
                    if (this.panel === 'teachers') return this.filteredPeople.length === 0;
                    return this.filteredStudents.length === 0;
                },
                get emptyCopy() { return this.query ? 'Nada coincide con esa búsqueda.' : 'Todavía no hay registros. Crea el primero o pídeselo a AulaSync.'; },
                get assignableCourses() {
                    const taken = new Set((this.selected?.courses || []).map(c => c.id).concat(this.pendingTags));
                    return this.courses.filter(c => !taken.has(c.id) && (!this.gradeFilter || c.grade === this.gradeFilter));
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
                            if (!subjects[name]) subjects[name] = { name, items: [] };
                            subjects[name].items.push(course);
                        });
                        return {
                            ...meta,
                            courses,
                            subjects: Object.values(subjects),
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
                    const payload = { teachers: [], invites: [], students: [], courses: [] };
                    this.selectedIds.forEach((item) => {
                        if (item.kind === 'teacher') payload.teachers.push(item.id);
                        else if (item.kind === 'invite') payload.invites.push(item.id);
                        else if (item.kind === 'student') payload.students.push(item.id);
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
                    this.grades = json.grades;
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
                    this.form = { name: '', email: '', subject_name: '', grade: '', section: '' };
                    this.creating = true;
                },
                async submitCreate() {
                    this.saving = true;
                    try {
                        if (this.panel === 'teachers') await this.api('POST', routes.teachers, this.form);
                        if (this.panel === 'students') await this.api('POST', routes.students, this.form);
                        if (this.panel === 'courses') await this.api('POST', routes.courses, this.form);
                        this.creating = false;
                        await this.refresh();
                        this.showToast('Creado.');
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
