@extends('super-admin.layout')
@section('title', 'Resumen')
@section('content')
    <h1>Resumen</h1>
    <p class="sub">Quién entra a AulaSync y si el producto crece. Las cifras salen de usuarios, inicios de sesión y actividad real — no de estimaciones.</p>

    <div class="grid">
        <div class="stat"><b>{{ $overview['colegios'] }}</b><span>Colegios registrados</span></div>
        <div class="stat"><b>{{ $overview['colegios_activos'] }}</b><span>Colegios con actividad en 30 días</span></div>
        <div class="stat"><b>{{ $overview['directores_activos'] }} / {{ $overview['directores'] }}</b><span>Directores activos en 30 días</span></div>
        <div class="stat"><b>{{ $overview['docentes_activos'] }} / {{ $overview['docentes'] }}</b><span>Docentes activos en 30 días</span></div>
        <div class="stat"><b>{{ $overview['usuarios_hoy'] }}</b><span>Personas que entraron hoy</span></div>
        <div class="stat"><b>{{ $overview['usuarios_7d'] }}</b><span>Personas activas en 7 días</span></div>
        <div class="stat"><b>{{ $overview['usuarios_30d'] }}</b><span>Personas activas en 30 días</span></div>
        <div class="stat"><b>{{ $overview['nuevos_usuarios'] }}</b><span>Usuarios nuevos en el periodo</span></div>
        <div class="stat"><b>{{ $overview['sesiones_activas'] }}</b><span>Sesiones abiertas ahora</span></div>
        <div class="stat"><b>{{ $overview['logins_periodo'] }}</b><span>Inicios de sesión en el periodo</span></div>
    </div>

    <div class="card">
        <h3>Altas de usuarios</h3>
        @if ($overview['crecimiento']->isEmpty())
            <p class="empty">No hay altas en este periodo.</p>
        @else
            @php $max = max(1, $overview['crecimiento']->max('total')); @endphp
            <div class="bars">
                @foreach ($overview['crecimiento'] as $row)
                    <div class="bar-row">
                        <span>{{ \App\Support\SuperAdminCopy::day($row->day) }}</span>
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
            <p class="empty">Aún no hay actividad registrada. Aparecerá con inicios de sesión y uso del chat.</p>
        @else
            <table>
                <thead><tr><th>Cuándo</th><th>Quién / de dónde</th><th>Qué hizo</th><th>Rol</th><th>Resultado</th></tr></thead>
                <tbody>
                    @foreach ($overview['actividad_reciente'] as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m H:i') }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::source($event->source) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::action($event->action ?: $event->event) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::role($event->role) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::status($event->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
