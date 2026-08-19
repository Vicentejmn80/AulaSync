<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alumnos · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    @include('partials.director-ui-styles')
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div x-data="{ open: false, familyMode: 'new', submitting: false, deleting: null }"
         x-cloak
         @keydown.escape.window="open = false; deleting = null">

        {{-- Modal de matriculación --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @click.stop
                 class="relative w-full max-w-lg max-h-[min(90dvh,90vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl p-5 sm:p-8">

                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-500 shadow-lg">
                            <i class="fa-solid fa-user-graduate text-lg text-white"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">Nueva Matrícula</p>
                            <h2 class="text-xl font-black text-slate-900">Matricular Alumno</h2>
                        </div>
                    </div>
                    <button @click="open = false"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form method="POST"
                      action="{{ route('director.students.store') }}"
                      class="space-y-5"
                      @submit="submitting = true">
                    @csrf

                    <div>
                        <label class="director-label">Nombre completo *</label>
                        <input type="text" name="name" required placeholder="Ej: María González Pérez" class="director-input">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="director-label">Cédula escolar</label>
                            <input type="text" name="document_id" placeholder="Opcional" class="director-input">
                        </div>
                        <div>
                            <label class="director-label">Fecha de nacimiento</label>
                            <input type="date" name="birthdate" class="director-input">
                        </div>
                    </div>

                    <div>
                        <label class="director-label">Curso / grado</label>
                        <select name="course_id" class="director-select">
                            <option value="">Sin curso todavía</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}">
                                    {{ $course->grade }}{{ $course->section ? ' / ' . $course->section : '' }} — {{ $course->subject_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="director-label">Grado</label>
                            <input type="text" name="grade" placeholder="Ej: 3er año" class="director-input">
                        </div>
                        <div>
                            <label class="director-label">Sección</label>
                            <input type="text" name="section" placeholder="A" class="director-input">
                        </div>
                    </div>

                    <div>
                        <label class="director-label">Familia / representante</label>
                        <div class="mb-3 flex gap-2">
                            <button type="button" @click="familyMode = 'new'"
                                    :class="familyMode === 'new' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300'"
                                    class="flex-1 rounded-lg border px-3 py-2 text-xs font-bold transition">Nueva familia</button>
                            <button type="button" @click="familyMode = 'existing'"
                                    :class="familyMode === 'existing' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-300'"
                                    class="flex-1 rounded-lg border px-3 py-2 text-xs font-bold transition">Hermano ya matriculado</button>
                        </div>
                        <input type="hidden" name="family_mode" :value="familyMode">
                        <div x-show="familyMode === 'existing'">
                            <select name="sibling_student_id" class="director-select">
                                <option value="">— Elige al hermano para compartir el código NV- —</option>
                                @foreach($households ?? [] as $mate)
                                    <option value="{{ $mate->id }}">
                                        {{ $mate->name }} · {{ $mate->family_code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-2 text-xs text-indigo-700" x-show="familyMode === 'new'">
                            Se genera un código familiar NV- para que el representante se registre y confirme a este alumno.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="open = false"
                                class="director-btn-secondary flex-1 !py-3 !text-sm">
                            Cancelar
                        </button>
                        <button type="submit" class="director-btn-primary flex-1 !py-3" :disabled="submitting">
                            <span x-show="!submitting"><i class="fa-solid fa-user-plus mr-2"></i>Matricular</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                                <span class="director-spinner" aria-hidden="true"></span>
                                Matriculando…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
            <header class="director-header">
                <div class="flex items-center gap-4">
                    <a href="{{ route('director.dashboard') }}" class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-500 shadow-lg">
                        <i class="fa-solid fa-arrow-left text-xl text-white"></i>
                    </a>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.3em] text-indigo-600">Gestión Académica</p>
                        <h1 class="director-page-title md:text-3xl">Alumnos</h1>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button @click="open = true" class="director-btn-primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Matricular Nuevo Alumno
                    </button>
                    <a href="{{ route('director.boletines') }}"
                       class="director-btn-secondary !py-3 !text-sm">
                        <i class="fa-solid fa-file-lines"></i> Boletines
                    </a>
                    @include('components.user-control-panel')
                </div>
            </header>

            @if($colegio ?? null)
                <div class="mb-5">
                    <x-school-pin-manager :colegio="$colegio" />
                </div>
            @endif

            @if(session('success'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => show = false, 6000)"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="director-alert-success mb-5 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-lg text-emerald-600"></i>
                    {{ session('success') }}
                    <button @click="show = false" class="ml-auto text-emerald-600 hover:text-emerald-800">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            @endif

            <section class="director-card">
                <form method="GET" class="mb-6 flex flex-wrap gap-3">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Buscar por nombre..."
                           class="director-input min-w-[200px] flex-1">
                    <select name="grade" class="director-select w-auto min-w-[10rem]">
                        <option value="">Todos los grados</option>
                        @foreach($grades as $g)
                            <option value="{{ $g }}" {{ request('grade') === $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="director-btn-primary">
                        <i class="fa-solid fa-search"></i>
                        Filtrar
                    </button>
                    @if(request()->anyFilled(['search', 'grade']))
                        <a href="{{ route('director.students') }}" class="director-btn-secondary !py-2.5 !text-sm">
                            <i class="fa-solid fa-xmark"></i> Limpiar
                        </a>
                    @endif
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-widest text-slate-500">
                                <th class="pb-3 pr-4">Alumno</th>
                                <th class="pb-3 pr-4">Grado / Sección</th>
                                <th class="pb-3 pr-4">Cursos</th>
                                <th class="pb-3 pr-4">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-key text-indigo-500"></i>
                                        Código Representante
                                    </span>
                                </th>
                                <th class="pb-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($students as $student)
                                <tr class="group transition hover:bg-slate-50">
                                    <td class="py-3.5 pr-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-xs font-bold text-indigo-700">
                                                {{ strtoupper(substr($student->name, 0, 2)) }}
                                            </div>
                                            <span class="font-semibold text-slate-900">{{ $student->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 pr-4 text-slate-600">
                                        {{ $student->grade }}{{ $student->section ? ' / ' . $student->section : '' }}
                                    </td>
                                    <td class="py-3.5 pr-4">
                                        <span class="director-chip">
                                            {{ $student->courses_count }} curso(s)
                                        </span>
                                    </td>
                                    <td class="py-3.5 pr-4">
                                        @if($student->family_code)
                                            <x-code-reveal
                                                type="family"
                                                :student-id="$student->id"
                                                label="Código familiar"
                                                :pin-hint="isset($colegio) ? \App\Models\Colegio::defaultPinFromInvite($colegio->invite_code) : null"
                                            />
                                        @else
                                            <span class="text-xs italic text-slate-500">Sin código</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <a href="{{ route('director.report-card', $student->id) }}"
                                               class="director-btn-secondary !text-xs">
                                                <i class="fa-solid fa-file-lines"></i>
                                                Boleta
                                            </a>
                                            <button type="button"
                                                    @click="deleting = { id: {{ $student->id }}, name: {{ json_encode($student->name) }}, url: '{{ route('director.students.destroy', $student) }}' }"
                                                    class="director-btn-danger inline-flex items-center gap-1.5 !px-3 !py-1.5 !text-xs"
                                                    title="Eliminar alumno">
                                                <i class="fa-solid fa-trash-can"></i>
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center">
                                        <i class="fa-regular fa-users-slash mb-3 block text-4xl text-slate-400"></i>
                                        <p class="text-slate-600">No se encontraron alumnos con los filtros aplicados.</p>
                                        <button @click="open = true" class="director-btn-primary mt-4">
                                            <i class="fa-solid fa-user-plus"></i>
                                            Matricular el primer alumno
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($students->hasPages())
                    <div class="mt-6">
                        {{ $students->links() }}
                    </div>
                @endif
            </section>
        </main>

        <div x-show="deleting"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 backdrop-blur-sm"
             @click.self="deleting = null">
            <div class="w-full max-w-md rounded-2xl border border-rose-200 bg-white p-6 shadow-2xl">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <h3 class="text-lg font-black text-slate-900">Eliminar alumno</h3>
                <p class="mt-2 text-sm text-slate-600">
                    ¿Eliminar a <strong x-text="deleting?.name"></strong>? Esta acción no se puede deshacer.
                </p>
                <form method="POST" class="mt-5 flex gap-3" :action="deleting?.url">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="deleting = null" class="director-btn-secondary flex-1">Cancelar</button>
                    <button type="submit" class="director-btn-danger flex-1">Sí, eliminar</button>
                </form>
            </div>
        </div>
    </div>

    @include('components.ai-assistant-bubble')
</body>
</html>
