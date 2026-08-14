<x-app-layout>
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 fw-bold mb-1">Panel de Representante</h1>
                <p class="text-muted mb-0">
                    Bienvenido, {{ auth()->user()->name }}.
                    @if($school)
                        Vinculado a <strong>{{ $school->name }}</strong>
                    @endif
                </p>
            </div>
        </div>

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
                    <div class="col-12 col-md-6 col-lg-4">
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

                                @if($student->courses->isNotEmpty())
                                    <div class="mb-3">
                                        <small class="text-muted fw-semibold d-block mb-1">Cursos</small>
                                        @foreach($student->courses as $course)
                                            <span class="badge bg-violet-100 text-violet-700 me-1 mb-1">{{ $course->name }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <a href="#" class="btn btn-outline-primary btn-sm rounded-3 w-100">
                                    <i class="fa-solid fa-chart-simple me-1"></i> Ver rendimiento
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @push('styles')
    <style>
        .bg-violet-100 { background-color: #e0e7ff; }
        .text-violet-600 { color: #7c3aed; }
        .bg-violet-100 { background-color: #f5f3ff; }
        .text-violet-700 { color: #6d28d9; }
        html.dark .bg-violet-100 { background-color: rgba(139, 92, 246, 0.15); }
        html.dark .text-violet-600 { color: #a78bfa; }
        html.dark .text-violet-700 { color: #c4b5fd; }
        html.dark .card { background: var(--bg-card); border: 1px solid var(--nova-glass-border); }
    </style>
    @endpush
</x-app-layout>
