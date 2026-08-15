<x-app-layout>
    <div class="container py-5" x-data="{ reportOpen: false, studentId: '' }">
        <div class="row mb-4">
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1">Panel de Representante</h1>
                    <p class="text-muted mb-0">
                        Bienvenido, {{ auth()->user()->name }}.
                        @if($school)
                            Vinculado a <strong>{{ $school->name }}</strong>
                        @endif
                    </p>
                </div>
                @if($students->isNotEmpty())
                    <button type="button" class="btn btn-primary rounded-3" @click="reportOpen = true">
                        <i class="fa-solid fa-calendar-xmark me-1"></i> Reportar ausencia
                    </button>
                @endif
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success rounded-4">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger rounded-4">{{ $errors->first() }}</div>
        @endif

        @if($alerts->isNotEmpty())
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3"><i class="fa-solid fa-bell me-1"></i> Alertas de asistencia</h2>
                    @foreach($alerts as $alert)
                        <div class="border-bottom py-2">
                            <strong>{{ $alert->title }}</strong>
                            <div class="text-muted small">{{ $alert->message }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($students->isEmpty())
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fa-solid fa-users-slash fa-3x text-muted"></i>
                </div>
                <h2 class="h5 fw-semibold">No hay estudiantes vinculados</h2>
                <p class="text-muted">No encontramos estudiantes asociados a tu código familiar.</p>
            </div>
        @else
            <div class="row g-4">
                @foreach($students as $student)
                    @php $stats = $attendance[$student->id] ?? ['month_absences' => 0, 'month_tardies' => 0, 'history' => collect(), 'requests' => collect()]; @endphp
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 border-0 shadow-sm rounded-4">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-violet-100 text-violet-600"
                                         style="width: 48px; height: 48px; font-size: 1.25rem; font-weight: 700;">
                                        {{ substr($student->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <h5 class="card-title fw-bold mb-0">{{ $student->name }}</h5>
                                        <small class="text-muted">
                                            {{ $student->grade ?? '—' }}{{ $student->section ? ' · ' . $student->section : '' }}
                                        </small>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="rounded-4 p-3 bg-violet-100">
                                            <small class="text-muted d-block">Ausencias del mes</small>
                                            <strong class="fs-4 text-violet-700">{{ $stats['month_absences'] }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="rounded-4 p-3 bg-violet-100">
                                            <small class="text-muted d-block">Retrasos del mes</small>
                                            <strong class="fs-4 text-violet-700">{{ $stats['month_tardies'] }}</strong>
                                        </div>
                                    </div>
                                </div>

                                @if($student->courses->isNotEmpty())
                                    <div class="mb-3">
                                        <small class="text-muted fw-semibold d-block mb-1">Cursos</small>
                                        @foreach($student->courses as $course)
                                            <span class="badge bg-violet-100 text-violet-700 me-1 mb-1">{{ $course->subject_name }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <h6 class="fw-bold">Historial reciente</h6>
                                @forelse($stats['history'] as $row)
                                    <div class="d-flex justify-content-between small border-bottom py-2">
                                        <span>{{ $row->attended_on->format('d/m') }} · {{ $row->course?->subject_name }}</span>
                                        <span class="fw-semibold">
                                            {{ ['present' => 'Presente', 'absent' => 'Ausente', 'tardy' => 'Tardío'][$row->status] ?? $row->status }}
                                            @if($row->reason) · {{ $row->reason->label }} @endif
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">Todavía no hay marcas de asistencia.</p>
                                @endforelse

                                @if($stats['requests']->isNotEmpty())
                                    <h6 class="fw-bold mt-3">Tus reportes</h6>
                                    @foreach($stats['requests'] as $request)
                                        <div class="small text-muted">
                                            {{ $request->start_date->format('d/m') }}
                                            @if($request->end_date->toDateString() !== $request->start_date->toDateString())
                                                – {{ $request->end_date->format('d/m') }}
                                            @endif
                                            · {{ $request->reason?->label ?? $request->kind }}
                                            · {{ $request->status }}
                                        </div>
                                    @endforeach
                                @endif

                                <button type="button" class="btn btn-outline-primary btn-sm rounded-3 w-100 mt-3"
                                        @click="studentId = '{{ $student->id }}'; reportOpen = true">
                                    <i class="fa-solid fa-calendar-xmark me-1"></i> Reportar ausencia
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div x-show="reportOpen" x-cloak class="position-fixed top-0 start-0 w-100 h-100" style="background:rgba(15,23,42,.45);z-index:40;" @click.self="reportOpen = false">
            <div class="card border-0 shadow-lg rounded-4 mx-auto mt-5" style="max-width: 520px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Reportar ausencia o retraso</h2>
                        <button type="button" class="btn btn-sm btn-light" @click="reportOpen = false">Cerrar</button>
                    </div>
                    <form method="POST" action="{{ route('representante.ausencias.store') }}">
                        @csrf
                        <label class="form-label fw-semibold">Estudiante</label>
                        <select name="student_id" class="form-select mb-3" x-model="studentId" required>
                            <option value="">Selecciona</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}">{{ $student->name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label fw-semibold">Tipo</label>
                        <select name="kind" class="form-select mb-3" required>
                            <option value="absence">Ausencia</option>
                            <option value="tardy">Retraso</option>
                        </select>
                        <label class="form-label fw-semibold">Motivo</label>
                        <select name="reason_id" class="form-select mb-3" required>
                            @foreach($reasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                            @endforeach
                        </select>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Desde</label>
                                <input type="date" name="start_date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Hasta</label>
                                <input type="date" name="end_date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <label class="form-label fw-semibold mt-3">Comentario</label>
                        <textarea name="comment" class="form-control mb-3" rows="3" maxlength="500" placeholder="Opcional. Obligatorio si eliges Asunto familiar u Otro."></textarea>
                        <button class="btn btn-primary w-100 rounded-3">Enviar al colegio</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .bg-violet-100 { background-color: #f5f3ff; }
        .text-violet-600 { color: #7c3aed; }
        .text-violet-700 { color: #6d28d9; }
        html.dark .bg-violet-100 { background-color: rgba(139, 92, 246, 0.15); }
        html.dark .text-violet-600 { color: #a78bfa; }
        html.dark .text-violet-700 { color: #c4b5fd; }
        html.dark .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); }
    </style>
    @endpush
</x-app-layout>
