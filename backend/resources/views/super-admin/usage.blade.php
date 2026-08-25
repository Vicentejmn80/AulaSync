@extends('super-admin.layout')
@section('title', 'Uso de producto')
@section('content')
    <h1>Uso de producto</h1>
    <p class="sub">Qué partes de AulaSync se usan de verdad: documentos, planificaciones, actividades y el chat.</p>

    <div class="grid">
        <div class="stat"><b>{{ $usage['documentos']['total'] }}</b><span>Documentos procesados</span></div>
        <div class="stat"><b>{{ $usage['planificaciones'] }}</b><span>Planificaciones</span></div>
        <div class="stat"><b>{{ $usage['actividades'] }}</b><span>Actividades</span></div>
        <div class="stat"><b>{{ $usage['tareas'] }}</b><span>Tareas</span></div>
        <div class="stat"><b>{{ $usage['evaluaciones_ia'] }}</b><span>Evaluaciones con IA</span></div>
        <div class="stat"><b>{{ $usage['consultas'] }}</b><span>Consultas académicas</span></div>
        <div class="stat"><b>{{ $usage['exitosas'] }}</b><span>Acciones correctas</span></div>
        <div class="stat"><b>{{ $usage['fallidas'] }}</b><span>Acciones que fallaron</span></div>
    </div>

    <div class="card">
        <h3>Funciones más utilizadas</h3>
        @if ($usage['mas_usadas']->isEmpty())
            <p class="empty">No hay uso de funciones en este periodo.</p>
        @else
            @php $max = max(1, $usage['mas_usadas']->max('total')); @endphp
            <div class="bars">
                @foreach ($usage['mas_usadas'] as $row)
                    <div class="bar-row">
                        <span>{{ \App\Support\SuperAdminCopy::action($row->action) }}</span>
                        <div class="bar"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
                        <strong>{{ $row->total }}</strong>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card">
        <h3>Qué más se pide en el chat</h3>
        @if ($usage['acciones_ia']->isEmpty())
            <p class="empty">Todavía no hay uso de IA registrado.</p>
        @else
            <table>
                <thead><tr><th>Qué hizo</th><th>De dónde</th><th>Veces</th></tr></thead>
                <tbody>
                    @foreach ($usage['acciones_ia'] as $row)
                        <tr>
                            <td>{{ \App\Support\SuperAdminCopy::action($row->action) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::source($row->source) }}</td>
                            <td>{{ $row->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Casi nadie usa (funciones conocidas sin actividad)</h3>
        @if ($usage['menos_usadas'] === [])
            <p class="empty">No hay contraste suficiente, o todas las funciones conocidas ya aparecen.</p>
        @else
            <p>{{ implode(' · ', array_map(fn ($name) => \App\Support\SuperAdminCopy::action($name), $usage['menos_usadas'])) }}</p>
        @endif
    </div>

    <div class="card">
        <h3>Errores más frecuentes</h3>
        @if ($usage['errores']->isEmpty())
            <p class="empty">No hay códigos de error en este periodo.</p>
        @else
            <table>
                <thead><tr><th>Qué pasó</th><th>Veces</th></tr></thead>
                <tbody>
                    @foreach ($usage['errores'] as $row)
                        <tr><td>{{ \App\Support\SuperAdminCopy::error($row->error_code) }}</td><td>{{ $row->total }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
