@extends('super-admin.layout')
@section('title', 'System Health')
@section('content')
    <h1>System Health</h1>
    <p class="sub">Estado operativo a partir de telemetría, documentos fallidos, logs de IA y cola.</p>

    <div class="grid">
        <div class="stat"><b>{{ $health['estado'] }}</b><span>Estado general</span></div>
        <div class="stat"><b>{{ $health['acciones_fallidas'] }}</b><span>Acciones fallidas</span></div>
        <div class="stat"><b>{{ $health['fallos_ia'] }}</b><span>Fallos de IA</span></div>
        <div class="stat"><b>{{ $health['documentos_fallidos'] }}</b><span>Documentos fallidos</span></div>
        <div class="stat"><b>{{ $health['failed_jobs'] }}</b><span>Jobs fallidos</span></div>
        <div class="stat"><b>{{ $health['latencia_ms'] ?? '—' }}</b><span>Latencia media (ms)</span></div>
    </div>

    <div class="card">
        <h3>Errores recientes</h3>
        @if ($health['errores_recientes']->isEmpty())
            <p class="empty">No hay errores telemetrados en este periodo.</p>
        @else
            <table>
                <thead><tr><th>Cuándo</th><th>Origen</th><th>Acción</th><th>Código</th></tr></thead>
                <tbody>
                    @foreach ($health['errores_recientes'] as $row)
                        <tr>
                            <td>{{ $row->created_at?->format('d/m H:i') }}</td>
                            <td>{{ $row->source }}</td>
                            <td>{{ $row->action }}</td>
                            <td>{{ $row->error_code ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
