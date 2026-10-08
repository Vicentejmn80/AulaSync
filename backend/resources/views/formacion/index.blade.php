@extends('formacion.layout')
@section('title', 'Tus formaciones')
@section('content')
    <section class="hero">
        <p class="kicker">AulaSync Formación</p>
        <h1>Tus formaciones</h1>
        <p class="lead">Solo ves los programas a los que tienes acceso. Esto no es el panel del colegio.</p>
    </section>
    @forelse($programs as $program)
        <a class="card" href="{{ route('formacion.show', $program) }}">
            <span class="num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
                <h2>{{ $program->title }}</h2>
                <p>{{ $program->lessons_count }} {{ $program->lessons_count === 1 ? 'clase' : 'clases' }}</p>
            </div>
            <span class="go">Entrar</span>
        </a>
    @empty
        <p class="empty">Todavía no tienes una formación asignada. Cuando te den acceso, aparecerá aquí.</p>
    @endforelse
@endsection
