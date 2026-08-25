@extends('super-admin.layout')
@section('title', $detail['colegio']->name)
@section('content')
    <h1>{{ $detail['colegio']->name }}</h1>
    <p class="sub">
        Director: {{ $detail['colegio']->director?->name ?? '—' }}.
        Solo ves este colegio.
    </p>

    <form method="POST" action="{{ url('/super-admin/colegios/'.$detail['colegio']->id.'/enter') }}" style="margin-bottom:16px;">
        @csrf
        <button class="btn" type="submit">Abrir dashboard de director</button>
    </form>

    @if (session('invitation_url'))
        <div class="card">
            <h3>Enlace mágico de onboarding</h3>
            <p class="sub">Compártelo con el director. Vence en 48 horas. Cuando lo acepte, su cuenta queda permanente y entra por /login.</p>
            <input type="text" readonly value="{{ session('invitation_url') }}" style="width:100%;">
        </div>
    @endif

    <div class="card">
        <h3>Enviar enlace mágico de onboarding al director</h3>
        <form method="POST" action="{{ route('super-admin.colegios.invite-director', $detail['colegio']) }}" class="filters">
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
    </div>

    <div class="grid">
        <div class="stat"><b>{{ $detail['overview']['usuarios_30d'] }}</b><span>Activos 30d</span></div>
        <div class="stat"><b>{{ $detail['usage']['actividades'] }}</b><span>Actividades</span></div>
        <div class="stat"><b>{{ $detail['usage']['planificaciones'] }}</b><span>Planificaciones</span></div>
        <div class="stat"><b>{{ $detail['usage']['documentos']['total'] }}</b><span>Documentos</span></div>
        <div class="stat"><b>{{ $detail['usage']['fallidas'] }}</b><span>Fallos</span></div>
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
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Cursos</h3>
        @if (($detail['courses'] ?? collect())->isEmpty())
            <p class="empty">Este colegio no tiene cursos.</p>
        @else
            <table>
                <thead><tr><th>Materia</th><th>Grado</th><th>Sección</th><th></th></tr></thead>
                <tbody>
                    @foreach ($detail['courses'] as $course)
                        <tr>
                            <td>{{ $course->subject_name }}</td>
                            <td>{{ $course->grade }}</td>
                            <td>{{ $course->section ?: '—' }}</td>
                            <td>
                                <form method="POST"
                                      action="{{ route('super-admin.colegios.cursos.destroy', [$detail['colegio'], $course]) }}"
                                      @submit="ask($event, '¿Eliminar el curso {{ $course->subject_name }} {{ $course->grade }}? Esta acción no se puede deshacer.')">
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

    <div class="card">
        <h3>Profesores</h3>
        @if (($detail['teachers'] ?? collect())->isEmpty())
            <p class="empty">Este colegio no tiene docentes registrados.</p>
        @else
            <table>
                <thead><tr><th>Nombre</th><th>Correo</th><th></th></tr></thead>
                <tbody>
                    @foreach ($detail['teachers'] as $teacher)
                        <tr>
                            <td>{{ $teacher->name }}</td>
                            <td>{{ $teacher->email }}</td>
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

    <div class="card">
        <h3>Alumnos</h3>
        @if (($detail['students'] ?? collect())->isEmpty())
            <p class="empty">Este colegio no tiene alumnos.</p>
        @else
            <table>
                <thead><tr><th>Nombre</th><th>Grado</th><th>Sección</th><th></th></tr></thead>
                <tbody>
                    @foreach ($detail['students'] as $student)
                        <tr>
                            <td>{{ $student->name }}</td>
                            <td>{{ $student->grade }}</td>
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
        @endif
    </div>

    <div class="card">
        <h3>IA de dirección (sin texto de conversación)</h3>
        @if ($detail['director_intents']->isEmpty())
            <p class="empty">Sin operaciones de IA de dirección.</p>
        @else
            <table>
                <thead><tr><th>Intención</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @foreach ($detail['director_intents'] as $log)
                        <tr>
                            <td>{{ $log->intent }}</td>
                            <td>{{ $log->status }}</td>
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
                            <td>{{ $doc->kind }}</td>
                            <td>{{ $doc->status }}</td>
                            <td>{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
