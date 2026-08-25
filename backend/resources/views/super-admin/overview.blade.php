@extends('super-admin.layout')
@section('title', 'Resumen')
@section('content')
    <h1>Resumen</h1>
    <p class="sub">Quién entra a AulaSync y si el producto crece. Las cifras salen de usuarios, inicios de sesión y actividad real — no de estimaciones.</p>

    <div class="grid">
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-school metric-icon"></i><span class="trend-badge neutral">Base</span></div>
            <b>{{ $overview['colegios'] }}</b><span>Colegios registrados</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-chart-line metric-icon emerald"></i><span class="trend-badge up">Activos</span></div>
            <b>{{ $overview['colegios_activos'] }}</b><span>Colegios con actividad en 30 días</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-user-tie metric-icon"></i><span class="trend-badge {{ $overview['directores_activos'] > 0 ? 'up' : 'warn' }}">{{ $overview['directores'] > 0 ? round(($overview['directores_activos'] / max(1,$overview['directores'])) * 100) : 0 }}%</span></div>
            <b>{{ $overview['directores_activos'] }} / {{ $overview['directores'] }}</b><span>Directores activos en 30 días</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-chalkboard-user metric-icon cyan"></i><span class="trend-badge {{ $overview['docentes_activos'] > 0 ? 'up' : 'warn' }}">{{ $overview['docentes'] > 0 ? round(($overview['docentes_activos'] / max(1,$overview['docentes'])) * 100) : 0 }}%</span></div>
            <b>{{ $overview['docentes_activos'] }} / {{ $overview['docentes'] }}</b><span>Docentes activos en 30 días</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-bolt metric-icon"></i><span class="trend-badge up">Hoy</span></div>
            <b>{{ $overview['usuarios_hoy'] }}</b><span>Personas que entraron hoy</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-regular fa-calendar-days metric-icon cyan"></i><span class="trend-badge neutral">7d</span></div>
            <b>{{ $overview['usuarios_7d'] }}</b><span>Personas activas en 7 días</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-calendar-week metric-icon"></i><span class="trend-badge neutral">30d</span></div>
            <b>{{ $overview['usuarios_30d'] }}</b><span>Personas activas en 30 días</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-user-plus metric-icon emerald"></i><span class="trend-badge {{ $overview['nuevos_usuarios'] > 0 ? 'up' : 'neutral' }}">Periodo</span></div>
            <b>{{ $overview['nuevos_usuarios'] }}</b><span>Usuarios nuevos en el periodo</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-link metric-icon"></i><span class="trend-badge neutral">Live</span></div>
            <b>{{ $overview['sesiones_activas'] }}</b><span>Sesiones abiertas ahora</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-right-to-bracket metric-icon cyan"></i><span class="trend-badge neutral">Periodo</span></div>
            <b>{{ $overview['logins_periodo'] }}</b><span>Inicios de sesión en el periodo</span>
        </div>
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
                        <div class="bar" title="{{ $row->total }} altas"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
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
                        @php
                            $status = \App\Support\SuperAdminCopy::status($event->status);
                            $badgeClass = str_contains(mb_strtolower($status), 'correct') ? 'success' : (str_contains(mb_strtolower($status), 'fall') ? 'failed' : 'neutral');
                            $source = \App\Support\SuperAdminCopy::source($event->source);
                        @endphp
                        <tr>
                            <td>{{ $event->created_at?->format('d/m H:i') }}</td>
                            <td>
                                <span class="table-identity">
                                    <span class="table-avatar">{{ strtoupper(substr($source, 0, 1)) }}</span>
                                    <span>{{ $source }}</span>
                                </span>
                            </td>
                            <td>{{ \App\Support\SuperAdminCopy::action($event->action ?: $event->event) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::role($event->role) }}</td>
                            <td><span class="status-badge {{ $badgeClass }}">{{ $status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
