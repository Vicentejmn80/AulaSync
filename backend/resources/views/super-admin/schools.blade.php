@extends('super-admin.layout')
@section('title', 'Salud de colegios')
@section('content')
    <h1>Salud de colegios</h1>
    <p class="sub">Adopción por colegio con datos reales. Activo = entró en los últimos 7 días; en riesgo = 8–30 días; inactivo = sin acceso en 30 días.</p>

    <div class="card">
        @if ($schools->isEmpty())
            <p class="empty">No hay colegios registrados.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Colegio</th>
                        <th>Director</th>
                        <th>Usuarios</th>
                        <th>Docentes</th>
                        <th>Actividad</th>
                        <th>Último acceso</th>
                        <th>Funciones</th>
                        <th>Adopción</th>
                        <th>Errores</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schools as $school)
                        <tr>
                            <td>
                                <span class="table-identity">
                                    <span class="table-avatar">{{ strtoupper(substr($school['name'], 0, 1)) }}</span>
                                    <strong>{{ $school['name'] }}</strong>
                                </span>
                            </td>
                            <td>{{ $school['director'] ?? '—' }}</td>
                            <td>{{ $school['usuarios'] }}</td>
                            <td>{{ $school['docentes'] }}</td>
                            <td><span class="status-badge neutral">{{ $school['actividad'] }}</span></td>
                            <td>{{ $school['ultimo_acceso']?->format('d/m/Y H:i') ?? 'Sin login' }}</td>
                            <td><span class="status-badge neutral">{{ $school['funciones'] }}</span></td>
                            <td><span class="status-badge success">{{ $school['adopcion'] }}</span></td>
                            <td><span class="status-badge {{ $school['errores'] > 0 ? 'failed' : 'success' }}">{{ $school['errores'] }}</span></td>
                            <td><span class="pill {{ $school['estado'] }}">{{ \App\Support\SuperAdminCopy::status($school['estado']) }}</span></td>
                            <td>
                                <a class="btn btn-ghost" href="{{ url('/super-admin/colegios/'.$school['id']) }}">Ver</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
