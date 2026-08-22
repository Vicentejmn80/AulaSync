@extends('super-admin.layout')
@section('title', $detail['colegio']->name)
@section('content')
    <h1>{{ $detail['colegio']->name }}</h1>
    <p class="sub">
        Director: {{ $detail['colegio']->director?->name ?? '—' }}.
        Solo ves este colegio.
    </p>

    <form method="POST" action="{{ url('/super-admin/colegios/'.$detail['colegio']->id.'/enter') }}" style="margin-bottom:16px;">
        @csrf
        <button class="btn" type="submit">Abrir dashboard de director</button>
    </form>

    <div class="grid">
        <div class="stat"><b>{{ $detail['overview']['usuarios_30d'] }}</b><span>Activos 30d</span></div>
        <div class="stat"><b>{{ $detail['usage']['actividades'] }}</b><span>Actividades</span></div>
        <div class="stat"><b>{{ $detail['usage']['planificaciones'] }}</b><span>Planificaciones</span></div>
        <div class="stat"><b>{{ $detail['usage']['documentos']['total'] }}</b><span>Documentos</span></div>
        <div class="stat"><b>{{ $detail['usage']['fallidas'] }}</b><span>Fallos</span></div>
    </div>

    <div class="card">
        <h3>Usuarios del colegio</h3>
        @if ($detail['users']->isEmpty())
            <p class="empty">Este colegio no tiene usuarios.</p>
        @else
            <table>
                <thead><tr><th>Nombre</th><th>Rol</th><th>Último acceso</th></tr></thead>
                <tbody>
                    @foreach ($detail['users'] as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>IA de dirección (sin texto de conversación)</h3>
        @if ($detail['director_intents']->isEmpty())
            <p class="empty">Sin operaciones de IA de dirección.</p>
        @else
            <table>
                <thead><tr><th>Intención</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @foreach ($detail['director_intents'] as $log)
                        <tr>
                            <td>{{ $log->intent }}</td>
                            <td>{{ $log->status }}</td>
                            <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h3>Documentos de inteligencia</h3>
        @if ($detail['documentos']->isEmpty())
            <p class="empty">Sin documentos en el periodo.</p>
        @else
            <table>
                <thead><tr><th>Archivo</th><th>Tipo</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @foreach ($detail['documentos'] as $doc)
                        <tr>
                            <td>{{ $doc->original_name }}</td>
                            <td>{{ $doc->kind }}</td>
                            <td>{{ $doc->status }}</td>
                            <td>{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
