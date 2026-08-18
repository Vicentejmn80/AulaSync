<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alumnos · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background:var(--bg-primary); color:var(--text-primary); }
        .glass-card {
            background: linear-gradient(145deg, rgba(255,255,255,.105), rgba(255,255,255,.035));
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
            backdrop-filter: blur(22px);
        }
        :root:not(.dark) .glass-card {
            background: var(--bg-card);
            border-color: var(--nova-glass-border);
            box-shadow: var(--nova-shadow);
        }
        :root:not(.dark) .text-white,
        :root:not(.dark) .text-white\/80,
        :root:not(.dark) .text-slate-100,
        :root:not(.dark) .text-slate-200,
        :root:not(.dark) .text-slate-300,
        :root:not(.dark) .text-slate-400 { color: var(--text-primary); }
        :root:not(.dark) .bg-white\/5,
        :root:not(.dark) .bg-white\/\[\.045\],
        :root:not(.dark) .bg-white\/10 { background: var(--bg-card); }
        :root:not(.dark) .border-white\/10 { border-color: var(--nova-glass-border); }
    </style>
</head>
<body class="min-h-screen">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-fuchsia-600/20 blur-[110px]"></div>
    </div>

    {{-- ══════════════════════════════════════════
         MODAL DE MATRICULACIÓN (Alpine.js)
    ══════════════════════════════════════════ --}}
    <div x-data="{ open: false, familyMode: 'new' }"
         x-cloak
         @keydown.escape.window="open = false">

        {{-- Overlay --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">

            {{-- Panel del modal --}}
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 @click.stop
                 class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl border border-white/15 bg-[#0f172a] shadow-2xl shadow-black/50 p-8">

                {{-- Encabezado del modal --}}
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                            <i class="fa-solid fa-user-graduate text-white text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-cyan-400">Nueva Matrícula</p>
                            <h2 class="text-xl font-black text-white">Matricular Alumno</h2>
                        </div>
                    </div>
                    <button @click="open = false"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 text-slate-400 transition hover:bg-white/10 hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                {{-- Formulario --}}
                <form method="POST" action="{{ route('director.students.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-300">
                            Nombre completo <span class="text-cyan-400">*</span>
                        </label>
                        <input type="text" name="name" required placeholder="Ej: María González Pérez"
                               class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-600 transition focus:border-cyan-500/50 focus:outline-none focus:ring-2 focus:ring-cyan-500/30">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-300">Cédula escolar</label>
                            <input type="text" name="document_id" placeholder="Opcional"
                                   class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-300">Fecha de nacimiento</label>
                            <input type="date" name="birthdate"
                                   class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-300">Curso / grado</label>
                        <select name="course_id"
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300 focus:outline-none">
                            <option value="" class="bg-[#0f172a]">Sin curso todavía</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" class="bg-[#0f172a]">
                                    {{ $course->grade }}{{ $course->section ? ' / ' . $course->section : '' }} — {{ $course->subject_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-300">Grado</label>
                            <input type="text" name="grade" placeholder="Ej: 3er año"
                                   class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-300">Sección</label>
                            <input type="text" name="section" placeholder="A"
                                   class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-300">Familia / representante</label>
                        <div class="mb-3 flex gap-2">
                            <button type="button" @click="familyMode = 'new'"
                                    :class="familyMode === 'new' ? 'bg-violet-600 text-white' : 'bg-white/5 text-slate-300'"
                                    class="flex-1 rounded-xl px-3 py-2 text-xs font-bold">Nueva familia</button>
                            <button type="button" @click="familyMode = 'existing'"
                                    :class="familyMode === 'existing' ? 'bg-violet-600 text-white' : 'bg-white/5 text-slate-300'"
                                    class="flex-1 rounded-xl px-3 py-2 text-xs font-bold">Hermano ya matriculado</button>
                        </div>
                        <input type="hidden" name="family_mode" :value="familyMode">
                        <div x-show="familyMode === 'existing'">
                            <select name="sibling_student_id"
                                    class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300 focus:outline-none">
                                <option value="" class="bg-[#0f172a]">— Elige al hermano para compartir el código NV- —</option>
                                @foreach($households ?? [] as $mate)
                                    <option value="{{ $mate->id }}" class="bg-[#0f172a]">
                                        {{ $mate->name }} · {{ $mate->family_code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p class="mt-2 text-xs text-cyan-300" x-show="familyMode === 'new'">
                            Se genera un código familiar NV- para que el representante se registre y confirme a este alumno.
                        </p>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" @click="open = false"
                                class="flex-1 rounded-xl border border-white/10 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-xl bg-gradient-to-r from-violet-500 to-cyan-500 py-3 text-sm font-bold text-white">
                            <i class="fa-solid fa-user-plus mr-2"></i>Matricular
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 shadow-2xl shadow-black/20 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('director.dashboard') }}" class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                        <i class="fa-solid fa-arrow-left text-xl text-white"></i>
                    </a>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Gestión Académica</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight text-white md:text-3xl">Alumnos</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Botón principal "Matricular" --}}
                    <button @click="open = true"
                            class="flex items-center gap-2 rounded-2xl bg-gradient-to-r from-violet-500 to-cyan-500 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-500/30 transition hover:scale-105 hover:shadow-violet-500/50 active:scale-100">
                        <i class="fa-solid fa-user-plus"></i>
                        Matricular Nuevo Alumno
                    </button>
                    <a href="{{ route('director.boletines') }}"
                       class="flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-white/10">
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

            {{-- Flash de éxito --}}
            @if(session('success'))
                <div x-data="{ show: true }"
                     x-show="show"
                     x-init="setTimeout(() => show = false, 6000)"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="mb-5 flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-300">
                    <i class="fa-solid fa-circle-check text-lg text-emerald-400"></i>
                    {{ session('success') }}
                    <button @click="show = false" class="ml-auto text-emerald-500 hover:text-emerald-300">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            @endif

            <section class="glass-card rounded-[2rem] p-6">
                <form method="GET" class="mb-6 flex flex-wrap gap-3">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Buscar por nombre..."
                           class="flex-1 min-w-[200px] rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/50">
                    <select name="grade"
                            class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500/50">
                        <option value="">Todos los grados</option>
                        @foreach($grades as $g)
                            <option value="{{ $g }}" {{ request('grade') === $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-xl bg-gradient-to-r from-cyan-500 to-violet-500 px-5 py-2.5 text-sm font-bold text-white transition hover:scale-105">
                        <i class="fa-solid fa-search mr-2"></i>Filtrar
                    </button>
                    @if(request()->anyFilled(['search', 'grade']))
                        <a href="{{ route('director.students') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm text-slate-300 hover:bg-white/5">
                            <i class="fa-solid fa-xmark mr-1"></i>Limpiar
                        </a>
                    @endif
                </form>

                {{-- Tabla de alumnos --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/10 text-left text-xs font-bold uppercase tracking-widest text-slate-500">
                                <th class="pb-3 pr-4">Alumno</th>
                                <th class="pb-3 pr-4">Grado / Sección</th>
                                <th class="pb-3 pr-4">Cursos</th>
                                <th class="pb-3 pr-4">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-key text-cyan-500/70"></i>
                                        Código Representante
                                    </span>
                                </th>
                                <th class="pb-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[.06]">
                            @forelse($students as $student)
                                <tr class="group transition hover:bg-white/[.03]">
                                    <td class="py-3.5 pr-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500/30 to-cyan-500/30 text-cyan-200 font-bold text-xs">
                                                {{ strtoupper(substr($student->name, 0, 2)) }}
                                            </div>
                                            <span class="font-semibold text-white">{{ $student->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 pr-4 text-slate-400">
                                        {{ $student->grade }}{{ $student->section ? ' / ' . $student->section : '' }}
                                    </td>
                                    <td class="py-3.5 pr-4">
                                        <span class="inline-flex items-center rounded-lg bg-white/5 px-2.5 py-1 text-xs font-medium text-slate-300">
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
                                            <span class="text-xs text-slate-600 italic">Sin código</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 text-right">
                                        <a href="{{ route('director.report-card', $student->id) }}"
                                           class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/5 hover:text-white">
                                            <i class="fa-solid fa-file-lines"></i>
                                            Boleta
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center">
                                        <i class="fa-regular fa-users-slash mb-3 block text-4xl text-slate-500"></i>
                                        <p class="text-slate-400">No se encontraron alumnos con los filtros aplicados.</p>
                                        <button @click="open = true"
                                                class="mt-4 inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-violet-500/80 to-cyan-500/80 px-5 py-2.5 text-sm font-bold text-white transition hover:from-violet-500 hover:to-cyan-500">
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

    </div>{{-- /x-data --}}

    @include('components.ai-assistant-bubble')
</body>
</html>
