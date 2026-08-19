<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Boleta · {{ $student->name }}</title>
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
        @media print {
            @page { margin: 16mm 12mm; }
            body { background: #fff !important; color: #000 !important; }
            .no-print { display: none !important; }
            .print-sheet {
                background: #fff !important;
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 14px !important;
                padding: 18px !important;
                backdrop-filter: none !important;
            }
            .print-text,
            .print-text * { color: #0f172a !important; }
            .print-divider { border-color: #cbd5e1 !important; }
            .print-table th {
                background: #f1f5f9 !important;
                color: #334155 !important;
                border-color: #cbd5e1 !important;
            }
            .print-table td {
                color: #0f172a !important;
                border-color: #e2e8f0 !important;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    @php
        $settings = auth()->user()->settings;
        $institutionName = $settings?->nombre_institucion ?? 'AulaSync';
        $schoolYear = data_get($settings?->preferencias, 'periodo_academico', now()->year . '-' . now()->copy()->addYear()->year);
        $issueDate = now()->format('d/m/Y');
    @endphp

    <div class="fixed inset-0 -z-10 overflow-hidden no-print">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-700/35 blur-[120px]"></div>
        <div class="absolute right-0 top-20 h-[28rem] w-[28rem] rounded-full bg-cyan-500/20 blur-[130px]"></div>
        <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-fuchsia-600/20 blur-[110px]"></div>
    </div>

    <main class="mx-auto max-w-5xl px-5 py-6 lg:px-8">
        <header class="mb-6 flex flex-col gap-4 rounded-[2rem] border border-white/10 bg-white/[.045] p-5 backdrop-blur-2xl lg:flex-row lg:items-center lg:justify-between no-print">
            <div class="flex items-center gap-4">
                <a href="{{ auth()->user()?->role === 'director' ? route('director.boletines') : route('teacher.hub') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200">Boleta de Calificaciones</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white">{{ $student->name }}</h1>
                </div>
            </div>
            <div class="flex gap-2">
                @php
                    $pdfUrl = auth()->user()?->role === 'profesor'
                        ? route('teacher.report-card.pdf', $student->id)
                        : route('director.report-card.pdf', $student->id);
                @endphp
                <a href="{{ $pdfUrl }}"
                   class="rounded-xl bg-gradient-to-r from-cyan-500 to-violet-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg transition hover:scale-105">
                    <i class="fa-solid fa-file-pdf mr-2"></i>Descargar PDF
                </a>
                <button onclick="window.print()"
                        class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                    <i class="fa-solid fa-print mr-2"></i>Imprimir
                </button>
            </div>
        </header>

        <div class="glass-card print-sheet rounded-[2rem] p-6 lg:p-8">
            <div class="hidden print:block print-text">
                <div class="flex items-start justify-between border-b border-slate-300 pb-4 print-divider">
                    <div class="flex items-center gap-3">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-slate-300 text-[9px] font-semibold text-slate-500">
                            LOGO
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[.22em] text-slate-500">Informe Oficial</p>
                            <h2 class="mt-1 text-xl font-black text-slate-900">{{ $institutionName }}</h2>
                            <p class="mt-1 text-sm text-slate-600">Año Escolar {{ $schoolYear }}</p>
                        </div>
                    </div>
                    <div class="text-right text-xs text-slate-600">
                        <p class="font-semibold uppercase tracking-wide text-slate-500">Fecha de emisión</p>
                        <p class="mt-1 font-bold text-slate-900">{{ $issueDate }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-6 mt-6 flex items-center justify-between border-b border-white/10 pb-4 print-divider print-text">
                <div>
                    <h2 class="text-xl font-black text-white">{{ $student->name }}</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ $student->grade }} {{ $student->section ? '/ ' . $student->section : '' }}
                        <span class="mx-2">&middot;</span>
                        {{ $courseData->count() }} asignatura(s)
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-bold uppercase tracking-wider text-cyan-200">Promedio Global</p>
                    <p class="text-4xl font-black {{ $globalAverage >= 70 ? 'text-emerald-200' : ($globalAverage >= 60 ? 'text-amber-200' : 'text-rose-200') }}">
                        {{ round($globalAverage, 1) }}%
                    </p>
                </div>
            </div>

            @foreach($courseData as $course)
                <div class="mb-6 last:mb-0 rounded-2xl border border-white/10 bg-white/[.035] p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-white">{{ $course['course_name'] }}</h3>
                            <p class="text-xs text-slate-500"><i class="fa-solid fa-chalkboard-user mr-1"></i>{{ $course['teacher_name'] }}</p>
                        </div>
                        <span class="rounded-full px-4 py-1 text-sm font-black {{ $course['promedio'] >= 70 ? 'bg-emerald-400/20 text-emerald-200' : ($course['promedio'] >= 60 ? 'bg-amber-400/20 text-amber-200' : 'bg-rose-400/20 text-rose-200') }}">
                            {{ $course['promedio'] }}%
                        </span>
                    </div>

                    @if(count($course['activities']) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm print-table">
                                <thead>
                                    <tr class="border-b border-white/10 text-left text-xs font-bold uppercase tracking-wider text-slate-400">
                                        <th class="pb-2 pr-4">Actividad</th>
                                        <th class="pb-2 pr-4">Tipo</th>
                                        <th class="pb-2 pr-4">Nota</th>
                                        <th class="pb-2 pr-4">Máx</th>
                                        <th class="pb-2 pr-4">%</th>
                                        <th class="pb-2">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($course['activities'] as $act)
                                        <tr class="border-b border-white/5 text-slate-300 last:border-0">
                                            <td class="py-2.5 pr-4 font-medium text-white">{{ $act['title'] }}</td>
                                            <td class="py-2.5 pr-4 text-xs uppercase">{{ $act['type'] }}</td>
                                            <td class="py-2.5 pr-4 font-bold">{{ $act['score'] }}</td>
                                            <td class="py-2.5 pr-4 text-slate-500">{{ $act['max_score'] }}</td>
                                            <td class="py-2.5 pr-4">{{ $act['percentage'] }}%</td>
                                            <td class="py-2.5 text-slate-500">{{ $act['due_date'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500 italic">Sin actividades calificadas en esta asignatura.</p>
                    @endif
                </div>
            @endforeach

            @if($courseData->isEmpty())
                <div class="py-12 text-center">
                    <i class="fa-regular fa-clipboard mb-3 text-4xl text-slate-500"></i>
                    <p class="text-slate-400">Este alumno no tiene notas registradas en ninguna asignatura.</p>
                </div>
            @endif

            <div class="mt-12 hidden border-t border-slate-300 pt-8 print:block print-divider print-text">
                <div class="grid grid-cols-2 gap-10">
                    <div class="text-center">
                        <div class="mx-auto h-14 w-full max-w-[260px] border-b border-slate-500"></div>
                        <p class="mt-3 text-sm font-semibold text-slate-700">Firma del Director(a)</p>
                    </div>
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-full max-w-[260px] items-end justify-center border-b border-slate-500">
                            <span class="mb-1 text-[11px] uppercase tracking-wide text-slate-500">Sello institucional</span>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-slate-700">Sello Oficial del Establecimiento</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="no-print">
        @include('components.ai-assistant-bubble')
    </div>
</body>
</html>
