@extends('formacion.layout')
@section('title', $program->title)
@section('content')
    <section class="hero">
        <p class="kicker">Programa</p>
        <h1>{{ $program->title }}</h1>
        @if($program->description)
            <p class="lead">{{ $program->description }}</p>
        @endif
        <div class="meta">
            <span>{{ $summary['total'] }} clases</span>
            @if($minutes > 0)<span>≈ {{ $minutes }} min</span>@endif
            <span>Tu progreso: {{ $summary['percent'] }}%</span>
        </div>
        <div class="barline" aria-hidden="true"><span style="width:{{ $summary['percent'] }}%"></span></div>
        <p>{{ $summary['completed'] }} de {{ $summary['total'] }} clases completadas</p>
    </section>
    @foreach($rows as $row)
        @php($lesson = $row['lesson'])
        @if($row['open'])
            <a class="card" href="{{ route('formacion.lessons.show', [$program, $lesson]) }}">
        @else
            <div class="card locked">
        @endif
            <span class="num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
                <h2>{{ $lesson->title }}</h2>
                <p>{{ $lesson->duration_minutes ? $lesson->duration_minutes.' min' : 'Clase' }}{{ $row['completed'] ? ' · Completada' : '' }}</p>
            </div>
            @if($row['open'])
                <span class="go">{{ $row['completed'] ? 'Repasar' : 'Comenzar' }}</span>
            </a>
            @else
                <span class="ghost">Bloqueada</span>
            </div>
            @endif
    @endforeach
@endsection
