@extends('super-admin.layout')

@section('title', 'Panel de Super Administrador')

@section('content')
    <h1>Panel de Super Administrador</h1>
    <p class="sub">Bienvenido, {{ auth()->user()->name }}. Desde aquí administras cuentas y entras a cualquier colegio.</p>

    <div class="grid">
        <div class="stat"><b>{{ $stats['users'] }}</b><span>Usuarios</span></div>
        <div class="stat"><b>{{ $stats['colegios'] }}</b><span>Colegios</span></div>
        <div class="stat"><b>{{ $stats['directors'] }}</b><span>Directores</span></div>
        <div class="stat"><b>{{ $stats['teachers'] }}</b><span>Docentes</span></div>
        <div class="stat"><b>{{ $stats['students'] }}</b><span>Alumnos</span></div>
        <div class="stat"><b>{{ $stats['courses'] }}</b><span>Cursos</span></div>
    </div>

    <div class="card" style="margin-top:18px;">
        <h3 style="margin:0 0 12px;">Gestión</h3>
        <a class="btn" href="{{ url('/super-admin/users') }}"><i class="fa-solid fa-users"></i> Gestión de Usuarios</a>
    </div>

    <div class="card">
        <h3 style="margin:0 0 12px;">Colegios</h3>
        @if ($colegios->isEmpty())
            <p class="sub">Todavía no hay colegios registrados.</p>
        @else
            <table>
                <thead>
                    <tr><th>Colegio</th><th>Director</th><th>Usuarios</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($colegios as $colegio)
                        <tr>
                            <td><strong>{{ $colegio->name }}</strong></td>
                            <td>{{ $colegio->director?->name ?? '—' }}</td>
                            <td>{{ $colegio->users_count }}</td>
                            <td>
                                <form method="POST" action="{{ url('/super-admin/colegios/'.$colegio->id.'/enter') }}">
                                    @csrf
                                    <button class="btn btn-ghost" type="submit">Entrar como director</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
