@extends('super-admin.layout')
@section('title', 'Salud del sistema')
@section('content')
    <h1>Salud del sistema</h1>
    <p class="sub">Si AulaSync está respondiendo bien: fallos del chat, documentos que no se procesaron y procesos automáticos que no terminaron.</p>

    <div class="grid">
        <div class="stat"><b>{{ \App\Support\SuperAdminCopy::status($health['estado']) }}</b><span>Estado general</span></div>
        <div class="stat"><b>{{ $health['acciones_fallidas'] }}</b><span>Acciones que fallaron</span></div>
        <div class="stat"><b>{{ $health['fallos_ia'] }}</b><span>Fallos del chat o de IA</span></div>
        <div class="stat"><b>{{ $health['documentos_fallidos'] }}</b><span>Documentos que no se procesaron</span></div>
        <div class="stat"><b>{{ $health['failed_jobs'] }}</b><span>Procesos automáticos sin completar</span></div>
        <div class="stat"><b>{{ $health['latencia_ms'] !== null ? $health['latencia_ms'].' ms' : 'Sin datos' }}</b><span>Tiempo medio de respuesta</span></div>
    </div>

    <div class="card">
        <h3>Errores recientes</h3>
        @if ($health['errores_recientes']->isEmpty())
            <p class="empty">No hay errores registrados en este periodo.</p>
        @else
            <table>
                <thead><tr><th>Cuándo</th><th>De dónde</th><th>Qué intentó</th><th>Qué pasó</th></tr></thead>
                <tbody>
                    @foreach ($health['errores_recientes'] as $row)
                        <tr>
                            <td>{{ $row->created_at?->format('d/m H:i') }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::source($row->source) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::action($row->action) }}</td>
                            <td>{{ \App\Support\SuperAdminCopy::error($row->error_code) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
