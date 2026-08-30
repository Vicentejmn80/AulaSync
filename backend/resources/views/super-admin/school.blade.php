@extends('super-admin.layout')
@section('title', $detail['colegio']->name)
@section('content')
    @php
        $subjects = $detail['subjects'] ?? collect();
        $studentsByGrade = ($detail['students'] ?? collect())->groupBy(function ($student) {
            return \App\Support\GradeLabel::canonical($student->grade) ?: ($student->grade ?: 'Sin grado');
        });
    @endphp

    <h1>{{ $detail['colegio']->name }}</h1>
    <p class="sub">
        Director: {{ $detail['colegio']->director?->name ?? '—' }}.
        Las cifras y listados de esta página son solo de este colegio.
    </p>

    <form method="POST" action="{{ url('/super-admin/colegios/'.$detail['colegio']->id.'/enter') }}" style="margin-bottom:16px;">
        @csrf
        <button class="btn" type="submit">Abrir dashboard de director</button>
    </form>

    @if (session('invitation_url'))
        <div class="card">
            <h3>Enlace mágico de onboarding</h3>
            <p class="sub">Compártelo con el director. Vence en 48 horas.</p>
            <input type="text" readonly value="{{ session('invitation_url') }}" style="width:100%;">
        </div>
    @endif

    <div class="grid">
        <div class="stat"><b>{{ $detail['overview']['usuarios_30d'] }}</b><span>Activos 30d</span></div>
        <div class="stat"><b>{{ $subjects->count() }}</b><span>Materias</span></div>
        <div class="stat"><b>{{ ($detail['teachers'] ?? collect())->count() }}</b><span>Docentes</span></div>
        <div class="stat"><b>{{ ($detail['students'] ?? collect())->count() }}</b><span>Alumnos</span></div>
        <div class="stat"><b>{{ $detail['usage']['fallidas'] }}</b><span>Fallos</span></div>
    </div>

    <div class="roster-split">
        <div class="card">
            <h3>Materias</h3>
            <p class="sub">Una fila por materia. Los chips son los grados donde se dicta.</p>
            @if ($subjects->isEmpty())
                <p class="empty">Este colegio no tiene materias cargadas.</p>
            @else
                @foreach ($subjects as $subject)
                    @php $label = mb_convert_case((string) $subject['name'], MB_CASE_TITLE, 'UTF-8'); @endphp
                    <div class="subject-line" x-data="{ open: false }">
                        <div>
                            <button type="button" @click="open = !open" style="background:none;border:0;padding:0;text-align:left;cursor:pointer;color:inherit;">
                                <strong>{{ $label }}</strong>
                                <small>{{ $subject['course_count'] }} {{ $subject['course_count'] === 1 ? 'curso' : 'cursos' }} · {{ $subject['grades']->count() }} {{ $subject['grades']->count() === 1 ? 'grado' : 'grados' }}</small>
                            </button>
                            <div class="chip-row" style="justify-content:flex-start;margin-top:8px;">
                                @foreach ($subject['grades'] as $grade)
                                    <span class="grade-chip">{{ $grade }}</span>
                                @endforeach
                            </div>
                            <div x-show="open" x-cloak style="margin-top:10px;">
                                @foreach ($subject['courses'] as $course)
                                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 0;">
                                        <span>{{ $course->grade }}{{ $course->section ? ' '.$course->section : '' }}</span>
                                        <form method="POST"
                                              action="{{ route('super-admin.colegios.cursos.destroy', [$detail['colegio'], $course]) }}"
                                              @submit="ask($event, '¿Eliminar {{ $label }} {{ $course->grade }}? Esta acción no se puede deshacer.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger" type="submit">Quitar</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <div class="card">
            <h3>Docentes</h3>
            @if (($detail['teachers'] ?? collect())->isEmpty())
                <p class="empty">No hay docentes registrados.</p>
            @else
                <table>
                    <thead><tr><th>Nombre</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($detail['teachers'] as $teacher)
                            <tr>
                                <td>
                                    <strong>{{ $teacher->name }}</strong>
                                    <div class="muted">{{ $teacher->email }}</div>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('super-admin.colegios.profesores.destroy', [$detail['colegio'], $teacher]) }}"
                                          @submit="ask($event, '¿Eliminar al docente {{ $teacher->name }}? Se desvinculará de sus cursos.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="card">
        <h3>Alumnos por grado</h3>
        @if (($detail['students'] ?? collect())->isEmpty())
            <p class="empty">Este colegio no tiene alumnos.</p>
        @else
            @foreach ($studentsByGrade as $grade => $students)
                <div class="grade-group">
                    <h4>{{ $grade }} · {{ $students->count() }} {{ $students->count() === 1 ? 'alumno' : 'alumnos' }}</h4>
                    <table>
                        <thead><tr><th>Nombre</th><th>Sección</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($students as $student)
                                <tr>
                                    <td>{{ $student->name }}</td>
                                    <td>{{ $student->section ?: '—' }}</td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('super-admin.colegios.alumnos.destroy', [$detail['colegio'], $student]) }}"
                                              @submit="ask($event, '¿Eliminar al alumno {{ $student->name }}? Se limpiarán matrículas y registros asociados.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger" type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    <div class="card">
        <h3>Usuarios del colegio</h3>
        @if ($detail['users']->isEmpty())
            <p class="empty">Este colegio no tiene usuarios.</p>
        @else
            <table>
                <thead><tr><th>Nombre</th><th>Rol</th><th>Último acceso</th></tr></thead>
                <tbody>
                    @foreach ($detail['users'] as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::role($user->role) }}</td>
                            <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Qué le pidió el director a la IA</h3>
        <p class="sub">Sin el texto de la conversación. Solo la acción y si se completó.</p>
        @if ($detail['director_intents']->isEmpty())
            <p class="empty">Todavía no hay pedidos del chat de dirección.</p>
        @else
            <table>
                <thead><tr><th>Qué hizo</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @foreach ($detail['director_intents'] as $log)
                        @php
                            $status = \App\Support\SuperAdminCopy::status($log->status);
                            $badge = str_contains(mb_strtolower($status), 'esperando') ? 'warn' : (str_contains(mb_strtolower($status), 'fall') ? 'failed' : 'success');
                        @endphp
                        <tr>
                            <td>{{ \App\Support\SuperAdminCopy::action($log->intent) }}</td>
                            <td><span class="status-badge {{ $badge }}">{{ $status }}</span></td>
                            <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Documentos de inteligencia</h3>
        @if ($detail['documentos']->isEmpty())
            <p class="empty">Sin documentos en el periodo.</p>
        @else
            <table>
                <thead><tr><th>Archivo</th><th>Tipo</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @foreach ($detail['documentos'] as $doc)
                        <tr>
                            <td>{{ $doc->original_name }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::category($doc->kind) }}</td>
                            <td><span class="status-badge neutral">{{ \App\Support\SuperAdminCopy::status($doc->status) }}</span></td>
                            <td>{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <details class="card">
        <summary style="cursor:pointer;font-weight:800;">Enviar enlace de onboarding al director</summary>
        <form method="POST" action="{{ route('super-admin.colegios.invite-director', $detail['colegio']) }}" class="filters" style="margin-top:14px;">
            @csrf
            <div>
                <label>Correo del director</label>
                <input type="email" name="email" required placeholder="director@colegio.edu">
            </div>
            <button class="btn" type="submit"><i class="fa-solid fa-link"></i> Generar enlace</button>
        </form>
        @if (($pendingInvitations ?? collect())->isNotEmpty())
            <table style="margin-top:14px;">
                <thead><tr><th>Correo</th><th>Estado</th><th>Vence</th><th>Enlace</th></tr></thead>
                <tbody>
                    @foreach ($pendingInvitations as $invite)
                        <tr>
                            <td>{{ $invite->email }}</td>
                            <td>{{ $invite->isPending() ? 'Pendiente' : 'Vencida' }}</td>
                            <td>{{ $invite->expires_at?->format('d/m/Y H:i') }}</td>
                            <td><input type="text" readonly value="{{ $invite->acceptUrl() }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </details>
@endsection
