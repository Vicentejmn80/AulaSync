<x-app-layout>
    @push('styles')
        @include('partials.nova-theme')
        <style>
            .plan-bg { background: var(--bg-primary); color: var(--text-primary); }
            .plan-card { background: var(--bg-card); border-color: var(--nova-glass-border); }
            :root:not(.dark) .text-violet-300,
            :root:not(.dark) .text-slate-100 { color: var(--text-primary); }
            :root:not(.dark) .text-slate-300,
            :root:not(.dark) .text-slate-400 { color: var(--text-secondary); }
            :root:not(.dark) .text-violet-200,
            :root:not(.dark) .text-cyan-300 { color: var(--nova-violet); }
            :root:not(.dark) .shadow-black\/20 { box-shadow: var(--nova-shadow); }
            :root:not(.dark) .border-slate-700\/60 { border-color: var(--nova-glass-border); }
            :root:not(.dark) .bg-slate-800\/50,
            :root:not(.dark) .bg-slate-800\/70 { background: var(--bg-card); }
            :root:not(.dark) .hover\:bg-slate-800\/70:hover { background: var(--bg-tertiary); }
            :root:not(.dark) .border-slate-700\/60 { border-color: var(--nova-glass-border); }
        </style>
    @endpush
    <div class="min-h-screen plan-bg py-8">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black bg-gradient-to-r from-violet-600 to-cyan-500 bg-clip-text text-transparent">
                        Planificaciones Guardadas
                    </h1>
                    <p class="text-slate-500 mt-2">Tu historial de clases para reabrir, editar y mejorar.</p>
                </div>
                <a href="{{ route('teacher.planner.manual') }}"
                   class="inline-flex items-center gap-2 rounded-2xl px-5 py-3 font-bold text-white
                          bg-gradient-to-r from-violet-600 to-cyan-500 shadow-lg shadow-violet-600/30 hover:opacity-90 transition">
                    <i class="fas fa-plus"></i> Nueva planificación
                </a>
            </div>

            @if($plans->isEmpty())
                <div class="rounded-3xl border plan-card p-10 text-center">
                    <i class="fas fa-folder-open text-5xl" style="color:var(--nova-violet);opacity:.7;margin-bottom:1rem;"></i>
                    <h2 class="text-xl font-semibold mb-2" style="color:var(--text-primary);">Aún no tienes planificaciones guardadas</h2>
                    <p style="color:var(--text-secondary);margin-bottom:1.5rem;">Genera una nueva clase y presiona "Guardar en mi historial".</p>
                    <a href="{{ route('teacher.planner.manual') }}"
                       class="inline-flex items-center gap-2 rounded-2xl px-4 py-2.5 font-semibold
                              border" style="border-color:var(--nova-cyan);color:var(--nova-cyan);">
                        Crear planificación manual
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($plans as $plan)
                        <article class="rounded-3xl border plan-card p-5 hover:bg-slate-800/70 transition" style="box-shadow:var(--nova-shadow);">
                            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold mb-3" 
                                  style="border-color:var(--nova-glass-border);background:rgba(124,58,237,.08);color:var(--nova-violet);">
                                Plan guardado
                            </span>
                            <h3 class="text-lg font-bold mb-2" style="color:var(--text-primary);">{{ $plan->tema ?: 'Sin tema' }}</h3>
                            <p class="text-sm mb-5" style="color:var(--text-secondary);">
                                {{ \Illuminate\Support\Str::limit($plan->objetivo ?: 'Sin objetivo', 140) }}
                            </p>
                            <div class="flex items-center justify-between mt-auto">
                                <small style="color:var(--text-tertiary);">{{ $plan->created_at?->format('d/m/Y H:i') }}</small>
                                <a href="{{ route('dashboard', ['plan' => $plan->id]) }}"
                                   class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-white
                                          bg-gradient-to-r from-violet-600 to-cyan-500 hover:opacity-90 transition">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

