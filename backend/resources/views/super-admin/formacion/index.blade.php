@extends('super-admin.layout')
@section('title', 'Formación')
@section('content')
    <section class="card" style="padding:20px;margin-bottom:16px;">
        <h1 style="margin-top:0;">AulaSync Formación</h1>
        <p>Programas privados de un colegio o globales (sin colegio). Quien solo tenga formación no entra al SaaS.</p>
        <form method="POST" action="{{ route('super-admin.formacion.store') }}">
            @csrf
            <p><input name="title" required placeholder="Título" value="{{ old('title') }}" style="width:100%;padding:8px;"></p>
            <p><textarea name="description" placeholder="Descripción" style="width:100%;padding:8px;">{{ old('description') }}</textarea></p>
            <p>
                <select name="colegio_id">
                    <option value="">Programa global</option>
                    @foreach($colegios as $colegio)
                        <option value="{{ $colegio->id }}">{{ $colegio->name }}</option>
                    @endforeach
                </select>
                <select name="status">
                    <option value="draft">Borrador</option>
                    <option value="published">Publicado</option>
                </select>
            </p>
            <button type="submit">Crear programa</button>
        </form>
    </section>
    @foreach($programs as $program)
        <p><a href="{{ route('super-admin.formacion.show', $program) }}">{{ $program->title }}</a>
            — {{ $program->status }} — {{ $program->colegio?->name ?? 'Global' }}
            — {{ $program->lessons_count }} clases — {{ $program->enrollments_count }} accesos</p>
    @endforeach
@endsection
