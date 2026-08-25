@extends('super-admin.layout')
@section('title', 'Salud del sistema')
@section('content')
    <h1>Salud del sistema</h1>
    <p class="sub">Si AulaSync está respondiendo bien: fallos del chat, documentos que no se procesaron y procesos automáticos que no terminaron.</p>

    <div class="grid">
        @php $isStable = \App\Support\SuperAdminCopy::status($health['estado']) === 'Estable'; @endphp
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-heart-pulse metric-icon emerald"></i><span class="trend-badge {{ $isStable ? 'up' : 'warn' }}">{{ \App\Support\SuperAdminCopy::status($health['estado']) }}</span></div>
            <b>{{ \App\Support\SuperAdminCopy::status($health['estado']) }}</b><span>Estado general</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-bug metric-icon"></i><span class="trend-badge {{ $health['acciones_fallidas'] > 0 ? 'warn' : 'up' }}">Acciones</span></div>
            <b>{{ $health['acciones_fallidas'] }}</b><span>Acciones que fallaron</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-robot metric-icon cyan"></i><span class="trend-badge {{ $health['fallos_ia'] > 0 ? 'warn' : 'up' }}">IA</span></div>
            <b>{{ $health['fallos_ia'] }}</b><span>Fallos del chat o de IA</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-regular fa-file-circle-xmark metric-icon"></i><span class="trend-badge {{ $health['documentos_fallidos'] > 0 ? 'warn' : 'up' }}">Docs</span></div>
            <b>{{ $health['documentos_fallidos'] }}</b><span>Documentos que no se procesaron</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-solid fa-gears metric-icon"></i><span class="trend-badge {{ $health['failed_jobs'] > 0 ? 'warn' : 'up' }}">Jobs</span></div>
            <b>{{ $health['failed_jobs'] }}</b><span>Procesos automáticos sin completar</span>
        </div>
        <div class="stat">
            <div class="stat-head"><i class="fa-regular fa-clock metric-icon emerald"></i><span class="trend-badge neutral">Respuesta</span></div>
            <b>{{ $health['latencia_ms'] !== null ? $health['latencia_ms'].' ms' : 'Sin datos' }}</b><span>Tiempo medio de respuesta</span>
        </div>
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
                        @php
                            $errorText = \App\Support\SuperAdminCopy::error($row->error_code);
                            $source = \App\Support\SuperAdminCopy::source($row->source);
                        @endphp
                        <tr>
                            <td>{{ $row->created_at?->format('d/m H:i') }}</td>
                            <td>
                                <span class="table-identity">
                                    <span class="table-avatar">{{ strtoupper(substr($source, 0, 1)) }}</span>
                                    <span>{{ $source }}</span>
                                </span>
                            </td>
                            <td>{{ \App\Support\SuperAdminCopy::action($row->action) }}</td>
                            <td><span class="status-badge failed">{{ $errorText }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
