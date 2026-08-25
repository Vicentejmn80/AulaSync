@extends('super-admin.layout')
@section('title', 'Hallazgos')
@section('content')
    <h1>Hallazgos</h1>
    <p class="sub">Lectura corta de los datos. Si no hay señal suficiente, lo dice.</p>

    <div class="card"><h3>Lo que más utilizan</h3><p>{{ $insights['mas_utilizan'] }}</p></div>
    <div class="card"><h3>Lo que están intentando hacer</h3><p>{{ $insights['intentando'] }}</p></div>
    <div class="card"><h3>Dónde están teniendo problemas</h3><p>{{ $insights['problemas'] }}</p></div>
    <div class="card"><h3>Qué deberíamos mejorar</h3><p>{{ $insights['mejorar'] }}</p></div>
    <div class="card"><h3>Qué casi nadie utiliza</h3><p>{{ $insights['casi_nadie'] }}</p></div>
    <div class="card"><h3>Tendencia</h3><p>{{ $insights['tendencia'] }}</p></div>
    <div class="card"><h3>Cuánto cuesta la IA</h3><p>{{ $insights['costo'] }}</p></div>
@endsection
