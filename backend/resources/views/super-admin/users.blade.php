@extends('super-admin.layout')

@section('title', 'Gestión de Usuarios')

@section('content')
    <h1>Gestión de Usuarios</h1>
    <p class="sub">Cambia el rol, el colegio y si ya completaron onboarding. El super admin no pasa por el wizard.</p>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Colegio</th>
                    <th>Onboarding</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td colspan="4">
                            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                <form method="POST" action="{{ url('/super-admin/users/'.$user->id) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role">
                                        @foreach (['super_admin' => 'Super admin', 'director' => 'Director', 'profesor' => 'Docente', 'representante' => 'Representante'] as $value => $label)
                                            <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="colegio_id">
                                        <option value="">Sin colegio</option>
                                        @foreach ($colegios as $colegio)
                                            <option value="{{ $colegio->id }}" @selected((int) $user->colegio_id === (int) $colegio->id)>{{ $colegio->name }}</option>
                                        @endforeach
                                    </select>
                                    <label style="font-size:13px;color:#6B4D87;">
                                        <input type="checkbox" name="onboarding_completed" value="1" @checked($user->onboarding_completed)> Completado
                                    </label>
                                    <button class="btn" type="submit">Guardar</button>
                                </form>
                                @if ((int) $user->id !== (int) auth()->id())
                                    <form method="POST"
                                          action="{{ route('super-admin.users.destroy', $user) }}"
                                          @submit="ask($event, {{ json_encode('¿Eliminar a '.$user->name.'? Esta acción no se puede deshacer.') }})">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">
                                            <i class="fa-solid fa-trash"></i> Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
