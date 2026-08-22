@extends('super-admin.layout')
@section('title', 'Overview')
@section('content')
    <h1>Overview</h1>
    <p class="sub">Quién está usando AulaSync y si el producto crece. Las cifras salen de usuarios, sesiones, login y actividad real.</p>

    <div class="grid">
        <div class="stat"><b>{{ $overview['colegios'] }}</b><span>Colegios registrados</span></div>
        <div class="stat"><b>{{ $overview['colegios_activos'] }}</b><span>Colegios activos (30d)</span></div>
        <div class="stat"><b>{{ $overview['directores_activos'] }}</b><span>Directores activos / {{ $overview['directores'] }}</span></div>
        <div class="stat"><b>{{ $overview['docentes_activos'] }}</b><span>Docentes activos / {{ $overview['docentes'] }}</span></div>
        <div class="stat"><b>{{ $overview['usuarios_hoy'] }}</b><span>Activos hoy</span></div>
        <div class="stat"><b>{{ $overview['usuarios_7d'] }}</b><span>Activos 7 días</span></div>
        <div class="stat"><b>{{ $overview['usuarios_30d'] }}</b><span>Activos 30 días</span></div>
        <div class="stat"><b>{{ $overview['nuevos_usuarios'] }}</b><span>Nuevos usuarios</span></div>
        <div class="stat"><b>{{ $overview['sesiones_activas'] }}</b><span>Sesiones abiertas</span></div>
        <div class="stat"><b>{{ $overview['logins_periodo'] }}</b><span>Logins en el periodo</span></div>
    </div>

    <div class="card">
        <h3>Crecimiento de usuarios</h3>
        @if ($overview['crecimiento']->isEmpty())
            <p class="empty">No hay altas en este periodo.</p>
        @else
            @php $max = max(1, $overview['crecimiento']->max('total')); @endphp
            <div class="bars">
                @foreach ($overview['crecimiento'] as $row)
                    <div class="bar-row">
                        <span>{{ $row->day }}</span>
                        <div class="bar"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h3>Actividad reciente</h3>
        @if ($overview['actividad_reciente']->isEmpty())
            <p class="empty">Aún no hay telemetría de producto. Aparecerá con logins y acciones de IA.</p>
        @else
            <table>
                <thead><tr><th>Cuándo</th><th>Origen</th><th>Acción</th><th>Rol</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach ($overview['actividad_reciente'] as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m H:i') }}</td>
                            <td>{{ $event->source }}</td>
                            <td>{{ $event->action ?: $event->event }}</td>
                            <td>{{ $event->role ?: '—' }}</td>
                            <td>{{ $event->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
