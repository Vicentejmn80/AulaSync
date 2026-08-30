@extends('super-admin.layout')
@section('title', 'Hallazgos')
@section('content')
    <h1>Hallazgos</h1>
    <p class="sub">Lectura corta por colegio. Si no hay señal suficiente, lo dice.</p>

    @if (($schoolDossiers ?? collect())->isNotEmpty())
        <p class="section-kicker">Hallazgos por colegio</p>
        <div class="school-stack">
            @foreach ($schoolDossiers as $school)
                @php $insights = $school['insights']; @endphp
                <x-super-admin-school-card :school="$school">
                    <div class="card"><h3><i class="fa-solid fa-star" style="color:#4f46e5;margin-right:6px;"></i>Lo que más utilizan</h3><p>{{ $insights['mas_utilizan'] }}</p></div>
                    <div class="card"><h3><i class="fa-solid fa-compass" style="color:#0891b2;margin-right:6px;"></i>Lo que están intentando hacer</h3><p>{{ $insights['intentando'] }}</p></div>
                    <div class="card"><h3><i class="fa-solid fa-triangle-exclamation" style="color:#b45309;margin-right:6px;"></i>Dónde están teniendo problemas</h3><p>{{ $insights['problemas'] }}</p></div>
                    <div class="card"><h3><i class="fa-solid fa-screwdriver-wrench" style="color:#7c3aed;margin-right:6px;"></i>Qué deberíamos mejorar</h3><p>{{ $insights['mejorar'] }}</p></div>
                    <div class="card"><h3><i class="fa-regular fa-eye-slash" style="color:#475569;margin-right:6px;"></i>Qué casi nadie utiliza</h3><p>{{ $insights['casi_nadie'] }}</p></div>
                    <div class="card"><h3><i class="fa-solid fa-chart-area" style="color:#0f766e;margin-right:6px;"></i>Tendencia</h3><p>{{ $insights['tendencia'] }}</p></div>
                    <div class="card"><h3><i class="fa-solid fa-wallet" style="color:#1d4ed8;margin-right:6px;"></i>Cuánto cuesta la IA</h3><p>{{ $insights['costo'] }}</p></div>
                </x-super-admin-school-card>
            @endforeach
        </div>
    @endif

    <details class="card platform-strip">
        <summary style="cursor:pointer;font-weight:800;">Hallazgos de plataforma (suma)</summary>
        <p style="margin-top:10px;"><strong>Lo que más utilizan:</strong> {{ $insights['mas_utilizan'] }}</p>
        <p><strong>Intentando:</strong> {{ $insights['intentando'] }}</p>
        <p><strong>Problemas:</strong> {{ $insights['problemas'] }}</p>
    </details>
@endsection
