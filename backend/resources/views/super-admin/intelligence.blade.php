@extends('super-admin.layout')
@section('title', 'Uso de IA')
@section('content')
    <h1>Uso de IA</h1>
    <p class="sub">Qué pide cada colegio en el chat, si se resolvió y cuánto tardó. No se guardan las preguntas ni las respuestas.</p>

    @if (($schoolDossiers ?? collect())->isEmpty())
        <div class="card"><p class="empty">No hay colegios para desglosar. El total de plataforma aparece más abajo.</p></div>
    @else
        <p class="section-kicker">IA por colegio</p>
        <div class="school-stack">
            @foreach ($schoolDossiers as $school)
                @php $intelligence = $school['intelligence']; @endphp
                <x-super-admin-school-card :school="$school">
                    <div class="grid">
                        <div class="stat">
                            <div class="stat-head"><i class="fa-regular fa-circle-question metric-icon"></i><span class="trend-badge {{ $intelligence['sin_resolver'] > 0 ? 'warn' : 'up' }}">Pendientes</span></div>
                            <b>{{ $intelligence['sin_resolver'] }}</b><span>Consultas sin respuesta clara</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-triangle-exclamation metric-icon cyan"></i><span class="trend-badge {{ $intelligence['director_fallos'] > 0 ? 'warn' : 'up' }}">Director</span></div>
                            <b>{{ $intelligence['director_fallos'] }}</b><span>Acciones del director que fallaron</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-regular fa-clock metric-icon emerald"></i><span class="trend-badge neutral">{{ $intelligence['latencia']['samples'] ?? 0 }} muestras</span></div>
                            <b>{{ $intelligence['latencia']['avg_ms'] !== null ? $intelligence['latencia']['avg_ms'].' ms' : 'Sin datos' }}</b><span>Tiempo medio de respuesta</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-microchip metric-icon"></i><span class="trend-badge neutral">Tokens</span></div>
                            <b>{{ $intelligence['tokens'] ?: 'Sin registro' }}</b><span>Consumo de IA (tokens)</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-sack-dollar metric-icon emerald"></i><span class="trend-badge neutral">Costo</span></div>
                            <b>{{ $intelligence['costo_usd'] !== null ? '$'.$intelligence['costo_usd'] : 'Sin registro' }}</b><span>Costo estimado (USD)</span>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Uso por origen</h3>
                        @if ($intelligence['por_rol']->isEmpty())
                            <p class="empty">Aún no hay uso de IA en este colegio.</p>
                        @else
                            <table>
                                <thead><tr><th>De dónde</th><th>Rol</th><th>Veces</th></tr></thead>
                                <tbody>
                                    @foreach ($intelligence['por_rol'] as $row)
                                        <tr>
                                            <td>{{ \App\Support\SuperAdminCopy::source($row->source) }}</td>
                                            <td>{{ \App\Support\SuperAdminCopy::role($row->role) }}</td>
                                            <td><span class="status-badge neutral">{{ $row->total }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="card">
                        <h3>Qué pide el director</h3>
                        @if ($intelligence['intenciones']->isEmpty())
                            <p class="empty">No hay pedidos del chat de dirección en este colegio.</p>
                        @else
                            <table>
                                <thead><tr><th>Pedido</th><th>Veces</th></tr></thead>
                                <tbody>
                                    @foreach ($intelligence['intenciones'] as $row)
                                        <tr><td>{{ \App\Support\SuperAdminCopy::action($row->intent) }}</td><td>{{ $row->total }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="card">
                        <h3>Temas de uso</h3>
                        @if ($intelligence['categorias']->isEmpty())
                            <p class="empty">Todavía no hay temas para mostrar.</p>
                        @else
                            @php $max = max(1, $intelligence['categorias']->max('total')); @endphp
                            <div class="bars">
                                @foreach ($intelligence['categorias'] as $row)
                                    <div class="bar-row">
                                        <span>{{ \App\Support\SuperAdminCopy::category($row->category) }}</span>
                                        <div class="bar" title="{{ $row->total }} eventos"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
                                        <strong>{{ $row->total }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="card">
                        <h3>Qué está fallando en el chat</h3>
                        @if ($intelligence['acciones_error']->isEmpty())
                            <p class="empty">No hay fallos de IA en este colegio.</p>
                        @else
                            <table>
                                <thead><tr><th>Qué intentó</th><th>Fallos</th></tr></thead>
                                <tbody>
                                    @foreach ($intelligence['acciones_error'] as $row)
                                        <tr><td>{{ \App\Support\SuperAdminCopy::action($row->action) }}</td><td><span class="status-badge failed">{{ $row->total }}</span></td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </x-super-admin-school-card>
            @endforeach
        </div>
    @endif

    <details class="card platform-strip" open>
        <summary style="cursor:pointer;font-weight:800;">Señal de plataforma (eventos sin colegio o suma total)</summary>
        <p class="sub" style="margin:10px 0 12px;">Consultas sin respuesta clara: <strong>{{ $intelligence['sin_resolver'] }}</strong> · Chat del director (consultas) aparece abajo si hubo uso sin colegio asignado.</p>
        @if (! $intelligence['por_rol']->isEmpty())
            <table>
                <thead><tr><th>De dónde</th><th>Rol</th><th>Veces</th></tr></thead>
                <tbody>
                    @foreach ($intelligence['por_rol'] as $row)
                        <tr>
                            <td>{{ \App\Support\SuperAdminCopy::source($row->source) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::role($row->role) }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if (! $intelligence['acciones_error']->isEmpty())
            <p class="section-kicker">Fallos</p>
            <table>
                <thead><tr><th>Qué intentó</th><th>Fallos</th></tr></thead>
                <tbody>
                    @foreach ($intelligence['acciones_error'] as $row)
                        <tr><td>{{ \App\Support\SuperAdminCopy::action($row->action) }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </details>
@endsection
