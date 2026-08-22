@extends('super-admin.layout')
@section('title', 'AI Intelligence')
@section('content')
    <h1>AI Intelligence</h1>
    <p class="sub">Eventos de intención, resultado y latencia. No se guardan prompts ni respuestas. El costo solo aparece si la llamada registró tokens.</p>

    <div class="grid">
        <div class="stat"><b>{{ $intelligence['sin_resolver'] }}</b><span>Sin resolver / pendientes</span></div>
        <div class="stat"><b>{{ $intelligence['director_fallos'] }}</b><span>Fallos IA director</span></div>
        <div class="stat"><b>{{ $intelligence['latencia']['avg_ms'] ?? '—' }}</b><span>Latencia media (ms)</span></div>
        <div class="stat"><b>{{ $intelligence['tokens'] ?: '—' }}</b><span>Tokens registrados</span></div>
        <div class="stat"><b>{{ $intelligence['costo_usd'] !== null ? '$'.$intelligence['costo_usd'] : '—' }}</b><span>Costo estimado USD</span></div>
    </div>

    <div class="card">
        <h3>Acciones por rol / origen</h3>
        @if ($intelligence['por_rol']->isEmpty())
            <p class="empty">Aún no hay eventos de IA en este periodo.</p>
        @else
            <table>
                <thead><tr><th>Origen</th><th>Rol</th><th>Eventos</th></tr></thead>
                <tbody>
                    @foreach ($intelligence['por_rol'] as $row)
                        <tr><td>{{ $row->source }}</td><td>{{ $row->role ?: '—' }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Intenciones de dirección</h3>
        @if ($intelligence['intenciones']->isEmpty())
            <p class="empty">No hay logs de Nova Director en este periodo.</p>
        @else
            <table>
                <thead><tr><th>Intención</th><th>Veces</th></tr></thead>
                <tbody>
                    @foreach ($intelligence['intenciones'] as $row)
                        <tr><td>{{ $row->intent }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Categorías de uso</h3>
        @if ($intelligence['categorias']->isEmpty())
            <p class="empty">Sin categorías todavía.</p>
        @else
            @php $max = max(1, $intelligence['categorias']->max('total')); @endphp
            <div class="bars">
                @foreach ($intelligence['categorias'] as $row)
                    <div class="bar-row">
                        <span>{{ $row->category ?: 'other' }}</span>
                        <div class="bar"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h3>Acciones que generan error</h3>
        @if ($intelligence['acciones_error']->isEmpty())
            <p class="empty">Sin errores de IA telemetrados.</p>
        @else
            <table>
                <thead><tr><th>Acción</th><th>Fallos</th></tr></thead>
                <tbody>
                    @foreach ($intelligence['acciones_error'] as $row)
                        <tr><td>{{ $row->action }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Tendencia de uso de IA</h3>
        @if ($intelligence['tendencia']->isEmpty())
            <p class="empty">Sin serie temporal todavía.</p>
        @else
            @php $max = max(1, $intelligence['tendencia']->max('total')); @endphp
            <div class="bars">
                @foreach ($intelligence['tendencia'] as $row)
                    <div class="bar-row">
                        <span>{{ $row->day }}</span>
                        <div class="bar"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
