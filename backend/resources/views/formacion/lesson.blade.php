@extends('formacion.layout')
@section('title', $lesson->title)
@section('content')
    <p><a class="back" href="{{ route('formacion.show', $program) }}">← Volver a formación</a></p>
    <p class="kicker" style="margin-top:22px;">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }} · {{ $program->title }}</p>
    <h1>{{ $lesson->title }}</h1>
    <div class="player">
        @if($video['kind'] === 'iframe')
            <iframe src="{{ $video['src'] }}" title="{{ $lesson->title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        @elseif($video['kind'] === 'file')
            <video controls src="{{ $video['src'] }}"></video>
        @elseif($video['kind'] === 'link')
            <div style="display:grid;place-items:center;height:100%;color:#fff;padding:24px;text-align:center;">
                <a href="{{ $video['href'] }}" target="_blank" rel="noopener noreferrer">Abrir el video</a>
            </div>
        @else
            <div style="display:grid;place-items:center;height:100%;color:#fff;">El video todavía no está disponible.</div>
        @endif
    </div>
    @if($lesson->description)
        <p class="lead">{{ $lesson->description }}</p>
    @endif
    <p>Tu progreso: {{ $summary['percent'] }}% · {{ $summary['completed'] }} de {{ $summary['total'] }}</p>
    <div class="actions">
        @if($completed)
            <span class="ghost">Clase completada</span>
        @else
            <form method="POST" action="{{ route('formacion.lessons.complete', [$program, $lesson]) }}">
                @csrf
                <button class="done" type="submit">Marcar como completada</button>
            </form>
        @endif
        @if($next && $nextOpen)
            <a class="go" href="{{ route('formacion.lessons.show', [$program, $next]) }}">Siguiente clase →</a>
        @endif
    </div>
@endsection
