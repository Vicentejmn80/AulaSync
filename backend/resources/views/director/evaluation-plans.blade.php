<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Planes de Evaluación · Director</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
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
        :root:not(.dark) .bg-white\/10 { background: var(--bg-card); }
        :root:not(.dark) .border-white\/10 { border-color: var(--nova-glass-border); }
        :root:not(.dark) .text-cyan-200 { color: var(--nova-cyan); }
        .balance-pill {
            display: inline-flex; align-items: center; gap: 6px;
            border-radius: 999px; padding: 4px 12px; font-size: 11px; font-weight: 800;
        }
        .balance-pill.ok { background: rgba(16,185,129,.18); color: #6ee7b7; }
        .balance-pill.warn { background: rgba(245,158,11,.18); color: #fcd34d; }
    </style>
</head>
<body class="min-h-screen">
@include('partials.theme-switcher')
<main class="mx-auto max-w-7xl px-5 py-6 lg:px-8">
    <header class="mb-8 flex flex-col gap-5 rounded-[2rem] border border-white/10 bg-white/5 p-5 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('director.dashboard') }}" class="text-sm font-bold text-cyan-200"><i class="fa-solid fa-arrow-left mr-2"></i>Centro de mando</a>
            <p class="mt-2 text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Assessment Plus</p>
            <h1 class="mt-1 text-3xl font-black text-white">Planes de Evaluación</h1>
            <p class="mt-1 text-sm text-slate-400">Visibilidad global de los planes de evaluación por curso, con su peso total en vivo.</p>
        </div>
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Grado</label>
                <select name="grade" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                    <option value="">Todos</option>
                    @foreach($grades as $grade)
                        <option value="{{ $grade }}" @selected($gradeFilter === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-400">Docente</label>
                <select name="teacher_id" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                    <option value="">Todos</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}" @selected($teacherFilter === $teacher->id)>{{ $teacher->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-bold text-white">Filtrar</button>
        </form>
    </header>

    <section class="mb-8 grid gap-4 md:grid-cols-3">
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Planes encontrados</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $plans->count() }}</p>
        </article>
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Balanceados (~100%)</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $plans->where('is_balanced', true)->count() }}</p>
        </article>
        <article class="glass-card rounded-[1.5rem] p-5">
            <p class="text-sm text-slate-400">Requieren ajuste</p>
            <p class="mt-2 text-4xl font-black text-white">{{ $plans->where('is_balanced', false)->count() }}</p>
        </article>
    </section>

    <section class="glass-card rounded-[2rem] p-6">
        <h2 class="text-xl font-black text-white">Planes por curso</h2>
        <p class="mb-4 text-sm text-slate-400">Cada curso tiene un único plan de evaluación (fusionado automáticamente).</p>

        @if($plans->isEmpty())
            <p class="text-sm text-slate-400">No hay planes de evaluación registrados con estos filtros.</p>
        @else
            <div class="space-y-4">
                @foreach($plans as $plan)
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-white">{{ $plan['title'] }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $plan['course']['subject_name'] ?? 'Sin curso' }}
                                    @if(!empty($plan['course']['grade']))
                                        · {{ $plan['course']['grade'] }}{{ !empty($plan['course']['section']) ? ' / '.$plan['course']['section'] : '' }}
                                    @endif
                                    · Docente: {{ $plan['teacher']['name'] ?? '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="balance-pill {{ $plan['is_balanced'] ? 'ok' : 'warn' }}">
                                    <i class="fa-solid {{ $plan['is_balanced'] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
                                    Total: {{ $plan['total_weight'] }}%
                                </span>
                                <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-slate-200">{{ $plan['items_count'] }} ítems</span>
                            </div>
                        </div>

                        @if(count($plan['items']))
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-slate-400">
                                        <tr>
                                            <th class="py-2">Unidad</th>
                                            <th>Tipo</th>
                                            <th>Categoría</th>
                                            <th>Peso</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($plan['items'] as $item)
                                            <tr class="border-t border-white/10 text-slate-200">
                                                <td class="py-2">{{ $item['unit_name'] }}</td>
                                                <td>{{ $item['evaluation_title'] ?? $item['assessment_type'] }}</td>
                                                <td>{{ $item['category'] === 'formative' ? 'Formativa' : 'Sumativa' }}</td>
                                                <td>{{ $item['weight_percentage'] }}%</td>
                                                <td>{{ $item['due_date'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="mt-3 text-sm text-slate-400">Este plan aún no tiene ítems.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</main>
</body>
</html>
