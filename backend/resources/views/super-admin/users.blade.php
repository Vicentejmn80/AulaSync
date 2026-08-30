@extends('super-admin.layout')

@section('title', 'Gestión de Usuarios')

@section('content')
    <h1>Gestión de Usuarios</h1>
    <p class="sub">Usuarios agrupados por colegio. Cambia el rol, el plantel y si ya completaron onboarding.</p>

    @php
        $grouped = $users->groupBy(fn ($user) => $user->colegio?->name ?: ($user->isSuperAdmin() ? 'Super admin' : 'Sin colegio'));
    @endphp

    <div class="school-stack">
        @foreach ($grouped as $schoolName => $schoolUsers)
            <article class="school-card" x-data="{ open: true }" :class="open && 'is-open'">
                <button type="button" class="school-card-head" @click="open = !open" style="grid-template-columns: 1fr auto 28px;">
                    <div class="school-ident">
                        <span class="table-avatar">{{ strtoupper(substr($schoolName, 0, 1)) }}</span>
                        <div>
                            <strong>{{ $schoolName }}</strong>
                            <small>{{ $schoolUsers->count() }} usuario{{ $schoolUsers->count() === 1 ? '' : 's' }}</small>
                        </div>
                    </div>
                    <div class="school-pulse">
                        <b>{{ $schoolUsers->where('role', 'profesor')->count() }}</b>
                        <span>Docentes</span>
                    </div>
                    <i class="fa-solid fa-chevron-down school-chevron"></i>
                </button>
                <div class="school-card-body" x-show="open" x-cloak>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Rol y colegio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schoolUsers as $user)
                                <tr>
                                    <td>
                                        <span class="table-identity">
                                            <span class="table-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                            <span>{{ $user->name }}</span>
                                        </span>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                                            <form method="POST" action="{{ url('/super-admin/users/'.$user->id) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:#f8fafc;border:1px solid #e2e8f0;padding:7px 8px;border-radius:12px;">
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
                                                <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
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
            </article>
        @endforeach
    </div>
@endsection
