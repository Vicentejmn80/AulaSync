@extends('super-admin.layout')
@section('title', 'Uso de producto')
@section('content')
    <h1>Uso de producto</h1>
    <p class="sub">Qué usa cada colegio de verdad: documentos, planificaciones, actividades y el chat. Las cifras de abajo son del plantel, no un promedio.</p>

    @if (($schoolDossiers ?? collect())->isEmpty())
        <div class="card"><p class="empty">No hay colegios para desglosar. El total de plataforma aparece más abajo.</p></div>
    @else
        <p class="section-kicker">Uso por colegio</p>
        <div class="school-stack">
            @foreach ($schoolDossiers as $school)
                @php $usage = $school['usage']; @endphp
                <x-super-admin-school-card :school="$school">
                    <div class="grid">
                        <div class="stat">
                            <div class="stat-head"><i class="fa-regular fa-file-lines metric-icon"></i><span class="trend-badge neutral">Docs</span></div>
                            <b>{{ $usage['documentos']['total'] }}</b><span>Documentos procesados</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-list-check metric-icon cyan"></i><span class="trend-badge neutral">Clase</span></div>
                            <b>{{ $usage['planificaciones'] }}</b><span>Planificaciones</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-pen-ruler metric-icon"></i><span class="trend-badge neutral">Trabajo</span></div>
                            <b>{{ $usage['actividades'] }}</b><span>Actividades</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-book-open metric-icon cyan"></i><span class="trend-badge neutral">Tarea</span></div>
                            <b>{{ $usage['tareas'] }}</b><span>Tareas</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-wand-magic-sparkles metric-icon emerald"></i><span class="trend-badge up">IA</span></div>
                            <b>{{ $usage['evaluaciones_ia'] }}</b><span>Evaluaciones con IA</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-comments metric-icon"></i><span class="trend-badge neutral">Chat</span></div>
                            <b>{{ $usage['consultas'] }}</b><span>Consultas académicas</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-circle-check metric-icon emerald"></i><span class="trend-badge up">Correctas</span></div>
                            <b>{{ $usage['exitosas'] }}</b><span>Acciones correctas</span>
                        </div>
                        <div class="stat">
                            <div class="stat-head"><i class="fa-solid fa-circle-exclamation metric-icon"></i><span class="trend-badge {{ $usage['fallidas'] > 0 ? 'warn' : 'up' }}">Fallos</span></div>
                            <b>{{ $usage['fallidas'] }}</b><span>Acciones que fallaron</span>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Funciones más utilizadas</h3>
                        @if ($usage['mas_usadas']->isEmpty())
                            <p class="empty">Este colegio no usó funciones en el periodo.</p>
                        @else
                            @php $max = max(1, $usage['mas_usadas']->max('total')); @endphp
                            <div class="bars">
                                @foreach ($usage['mas_usadas'] as $row)
                                    <div class="bar-row">
                                        <span>{{ \App\Support\SuperAdminCopy::action($row->action) }}</span>
                                        <div class="bar" title="{{ $row->total }} veces"><i style="width: {{ round($row->total / $max * 100) }}%"></i></div>
                                        <strong>{{ $row->total }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="card">
                        <h3>Qué más se pide en el chat</h3>
                        @if ($usage['acciones_ia']->isEmpty())
                            <p class="empty">Todavía no hay uso de IA en este colegio.</p>
                        @else
                            <table>
                                <thead><tr><th>Qué hizo</th><th>De dónde</th><th>Veces</th></tr></thead>
                                <tbody>
                                    @foreach ($usage['acciones_ia'] as $row)
                                        <tr>
                                            <td>{{ \App\Support\SuperAdminCopy::action($row->action) }}</td>
                                            <td>{{ \App\Support\SuperAdminCopy::source($row->source) }}</td>
                                            <td><span class="status-badge neutral">{{ $row->total }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>

                    <div class="card">
                        <h3>Qué dejan de usar</h3>
                        @if ($usage['menos_usadas'] === [])
                            <p class="empty">No hay contraste suficiente, o todas las funciones conocidas ya aparecen.</p>
                        @else
                            <p>{{ implode(' · ', array_map(fn ($name) => \App\Support\SuperAdminCopy::action($name), $usage['menos_usadas'])) }}</p>
                        @endif
                    </div>

                    <div class="card">
                        <h3>Errores más frecuentes</h3>
                        @if ($usage['errores']->isEmpty())
                            <p class="empty">No hay códigos de error en este colegio.</p>
                        @else
                            <table>
                                <thead><tr><th>Qué pasó</th><th>Veces</th></tr></thead>
                                <tbody>
                                    @foreach ($usage['errores'] as $row)
                                        <tr><td>{{ \App\Support\SuperAdminCopy::error($row->error_code) }}</td><td><span class="status-badge failed">{{ $row->total }}</span></td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </x-super-admin-school-card>
            @endforeach
        </div>
    @endif

    <details class="card platform-strip">
        <summary style="cursor:pointer;font-weight:800;">Totales de plataforma</summary>
        <p class="sub" style="margin:10px 0 0;">
            Evaluaciones con IA: <strong>{{ $usage['evaluaciones_ia'] }}</strong>
            · Consultas: <strong>{{ $usage['consultas'] }}</strong>
            · Acciones correctas: <strong>{{ $usage['exitosas'] }}</strong>
        </p>
        @if (! $usage['mas_usadas']->isEmpty())
            <p style="margin:8px 0 0;">Más usada en toda la plataforma: {{ \App\Support\SuperAdminCopy::action($usage['mas_usadas']->first()->action) }}</p>
        @endif
    </details>
@endsection
