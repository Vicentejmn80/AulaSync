@extends('super-admin.layout')
@section('title', 'Resumen')
@section('content')
    <h1>Resumen</h1>
    <p class="sub">Cada colegio tiene sus propias cifras. Abre una tarjeta para ver quién entra, qué usan y qué le piden a la IA — sin promediar planteles.</p>

    @if (($schoolDossiers ?? collect())->isEmpty())
        <div class="card"><p class="empty">Todavía no hay colegios registrados.</p></div>
    @else
        <p class="section-kicker">Colegios</p>
        <div class="school-stack">
            @foreach ($schoolDossiers as $school)
                @php $ov = $school['overview']; $usage = $school['usage']; $intel = $school['intelligence']; @endphp
                <x-super-admin-school-card :school="$school">
                    <div class="grid">
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-user-tie metric-icon"></i><span class="trend-badge {{ $ov['directores_activos'] > 0 ? 'up' : 'warn' }}">30d</span></div>
                            <b>{{ $ov['directores_activos'] }} / {{ $ov['directores'] }}</b><span>Directores activos</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-chalkboard-user metric-icon cyan"></i><span class="trend-badge {{ $ov['docentes_activos'] > 0 ? 'up' : 'warn' }}">30d</span></div>
                            <b>{{ $ov['docentes_activos'] }} / {{ $ov['docentes'] }}</b><span>Docentes activos</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-bolt metric-icon"></i><span class="trend-badge up">Hoy</span></div>
                            <b>{{ $ov['usuarios_hoy'] }}</b><span>Entraron hoy</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-regular fa-calendar-days metric-icon cyan"></i><span class="trend-badge neutral">7d</span></div>
                            <b>{{ $ov['usuarios_7d'] }}</b><span>Activos en 7 días</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-calendar-week metric-icon"></i><span class="trend-badge neutral">30d</span></div>
                            <b>{{ $ov['usuarios_30d'] }}</b><span>Activos en 30 días</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-user-plus metric-icon emerald"></i><span class="trend-badge {{ $ov['nuevos_usuarios'] > 0 ? 'up' : 'neutral' }}">Periodo</span></div>
                            <b>{{ $ov['nuevos_usuarios'] }}</b><span>Usuarios nuevos</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-link metric-icon"></i><span class="trend-badge neutral">Live</span></div>
                            <b>{{ $ov['sesiones_activas'] }}</b><span>Sesiones abiertas (15 min)</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-right-to-bracket metric-icon cyan"></i><span class="trend-badge neutral">Periodo</span></div>
                            <b>{{ $ov['logins_periodo'] }}</b><span>Inicios de sesión</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-comments metric-icon"></i><span class="trend-badge neutral">Chat</span></div>
                            <b>{{ $usage['consultas'] }}</b><span>Consultas a la IA</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-wand-magic-sparkles metric-icon emerald"></i><span class="trend-badge up">IA</span></div>
                            <b>{{ $usage['evaluaciones_ia'] }}</b><span>Evaluaciones con IA</span>
                        </div>
                    </div>

                    <p class="section-kicker">Qué más le piden a la IA</p>
                    @if ($intel['intenciones']->isEmpty() && $usage['acciones_ia']->isEmpty())
                        <p class="empty">Este colegio todavía no le pide nada a la IA en el periodo.</p>
                    @else
                        <table>
                            <thead><tr><th>Pedido</th><th>Veces</th></tr></thead>
                            <tbody>
                                @foreach ($intel['intenciones']->take(6) as $row)
                                    <tr><td>{{ \App\Support\SuperAdminCopy::action($row->intent) }}</td><td>{{ $row->total }}</td></tr>
                                @endforeach
                                @foreach ($usage['acciones_ia']->take(6) as $row)
                                    <tr>
                                        <td>{{ \App\Support\SuperAdminCopy::action($row->action) }} · {{ \App\Support\SuperAdminCopy::source($row->source) }}</td>
                                        <td>{{ $row->total }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <p class="section-kicker">Qué dejan de usar</p>
                    @if ($usage['menos_usadas'] === [])
                        <p class="empty">No hay contraste suficiente en este colegio.</p>
                    @else
                        <p>{{ implode(' · ', array_map(fn ($name) => \App\Support\SuperAdminCopy::action($name), $usage['menos_usadas'])) }}</p>
                    @endif

                    <p class="section-kicker">Actividad reciente</p>
                    @if ($ov['actividad_reciente']->isEmpty())
                        <p class="empty">Aún no hay actividad registrada en este colegio.</p>
                    @else
                        <table>
                            <thead><tr><th>Cuándo</th><th>De dónde</th><th>Qué hizo</th><th>Resultado</th></tr></thead>
                            <tbody>
                                @foreach ($ov['actividad_reciente'] as $event)
                                    @php
                                        $status = \App\Support\SuperAdminCopy::status($event->status);
                                        $badgeClass = str_contains(mb_strtolower($status), 'correct') ? 'success' : (str_contains(mb_strtolower($status), 'fall') ? 'failed' : 'neutral');
                                    @endphp
                                    <tr>
                                        <td>{{ $event->created_at?->format('d/m H:i') }}</td>
                                        <td>{{ \App\Support\SuperAdminCopy::source($event->source) }}</td>
                                        <td>{{ \App\Support\SuperAdminCopy::action($event->action ?: $event->event) }}</td>
                                        <td><span class="status-badge {{ $badgeClass }}">{{ $status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-super-admin-school-card>
            @endforeach
        </div>
    @endif

    <details class="card platform-strip">
        <summary style="cursor:pointer;font-weight:800;">Totales de plataforma (suma de todos los colegios)</summary>
        <p class="sub" style="margin:10px 0 0;">Colegios registrados: <strong>{{ $overview['colegios'] }}</strong> · Activos en 30 días: <strong>{{ $overview['colegios_activos'] }}</strong> · Sesiones abiertas (ult. 15 min): <strong>{{ $overview['sesiones_activas'] }}</strong></p>
    </details>
@endsection
