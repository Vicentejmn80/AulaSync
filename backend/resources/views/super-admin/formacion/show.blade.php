@extends('super-admin.layout')
@section('title', $program->title)
@section('content')
    <p><a href="{{ route('super-admin.formacion.index') }}">← Programas</a></p>
    <h1>{{ $program->title }}</h1>
    <p>URL de alumnos: <code>/formacion/{{ $program->slug }}</code></p>
    <form method="POST" action="{{ route('super-admin.formacion.update', $program) }}">
        @csrf @method('PATCH')
        <p><input name="title" required value="{{ $program->title }}" style="width:100%;padding:8px;"></p>
        <p><textarea name="description" style="width:100%;padding:8px;">{{ $program->description }}</textarea></p>
        <p>
            <select name="status">
                <option value="draft" @selected($program->status==='draft')>Borrador</option>
                <option value="published" @selected($program->status==='published')>Publicado</option>
            </select>
            <select name="colegio_id">
                <option value="">Programa global</option>
                @foreach($colegios as $colegio)
                    <option value="{{ $colegio->id }}" @selected((int)$program->colegio_id===(int)$colegio->id)>{{ $colegio->name }}</option>
                @endforeach
            </select>
        </p>
        <button type="submit">Guardar programa</button>
    </form>

    <h2>Clases</h2>
    @foreach($program->lessons as $lesson)
        <form method="POST" action="{{ route('super-admin.formacion.lessons.update', [$program, $lesson]) }}" style="border-top:1px solid #e2e8f0;padding-top:12px;">
            @csrf @method('PATCH')
            <p><strong>{{ $lesson->position }}.</strong>
                <input name="title" required value="{{ $lesson->title }}">
                <input name="duration_minutes" type="number" min="1" value="{{ $lesson->duration_minutes }}" placeholder="min">
            </p>
            <p><textarea name="description" style="width:100%;">{{ $lesson->description }}</textarea></p>
            <p><input name="video_url" value="{{ $lesson->video_url }}" placeholder="https://…" style="width:100%;"></p>
            <button type="submit">Guardar clase</button>
        </form>
        <form method="POST" action="{{ route('super-admin.formacion.lessons.move', [$program, $lesson]) }}" style="display:inline;">
            @csrf <input type="hidden" name="direction" value="up"><button>Subir</button>
        </form>
        <form method="POST" action="{{ route('super-admin.formacion.lessons.move', [$program, $lesson]) }}" style="display:inline;">
            @csrf <input type="hidden" name="direction" value="down"><button>Bajar</button>
        </form>
    @endforeach

    <h3>Nueva clase</h3>
    <form method="POST" action="{{ route('super-admin.formacion.lessons.store', $program) }}">
        @csrf
        <p><input name="title" required placeholder="Título de la clase"></p>
        <p><textarea name="description" placeholder="Descripción"></textarea></p>
        <p><input name="video_url" placeholder="URL https del video" style="width:100%;"></p>
        <p><input name="duration_minutes" type="number" min="1" placeholder="Minutos"></p>
        <button type="submit">Agregar clase</button>
    </form>

    <h2>Accesos</h2>
    @if($errors->any())
        <p>{{ $errors->first() }}</p>
    @endif
    <form method="POST" action="{{ route('super-admin.formacion.enroll', $program) }}">
        @csrf
        <p><input name="name" required placeholder="Nombre"></p>
        <p><input name="email" type="email" required placeholder="Correo"></p>
        <p><input name="password" type="text" placeholder="Contraseña (obligatoria si el correo es nuevo)"></p>
        <p><label><input type="checkbox" name="saas_access" value="1"> También dar acceso a AulaSync completo</label></p>
        <button type="submit">Dar acceso</button>
    </form>
    @foreach($program->enrollments as $enrollment)
        <p>{{ $enrollment->user?->name }} — {{ $enrollment->user?->email }} — {{ $enrollment->status }}
            — SaaS: {{ $enrollment->user?->hasSaasAccess() ? 'sí' : 'no' }}
            @if($enrollment->status === 'active')
                <form method="POST" action="{{ route('super-admin.formacion.revoke', [$program, $enrollment]) }}" style="display:inline;">
                    @csrf @method('DELETE') <button>Quitar acceso</button>
                </form>
                @if($enrollment->user && ! $enrollment->user->hasSaasAccess())
                    <form method="POST" action="{{ route('super-admin.formacion.grant-saas', [$program, $enrollment]) }}" style="display:inline;">
                        @csrf <button>Habilitar AulaSync</button>
                    </form>
                @endif
            @endif
        </p>
    @endforeach
@endsection
