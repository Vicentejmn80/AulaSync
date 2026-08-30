@props(['school'])
@php
    $status = \App\Support\SuperAdminCopy::status($school['estado'] ?? 'inactivo');
    $initial = strtoupper(substr((string) ($school['name'] ?? 'C'), 0, 1));
@endphp
<article class="school-card" x-data="{ open: false }" :class="open && 'is-open'">
    <button type="button" class="school-card-head" @click="open = !open" :aria-expanded="open">
        <div class="school-ident">
            <span class="table-avatar">{{ $initial }}</span>
            <div>
                <strong>{{ $school['name'] }}</strong>
                <small>{{ $school['director'] ? 'Director: '.$school['director'] : 'Sin director asignado' }}</small>
            </div>
        </div>
        <div class="school-pulse">
            <b>{{ $school['usuarios'] ?? 0 }}</b>
            <span>Usuarios</span>
        </div>
        <div class="school-pulse">
            <b>{{ $school['docentes'] ?? 0 }}</b>
            <span>Docentes</span>
        </div>
        <div class="school-pulse">
            <b>{{ $school['eventos'] ?? $school['actividad'] ?? 0 }}</b>
            <span>Actividad</span>
        </div>
        <div class="school-pulse">
            <span class="pill {{ $school['estado'] ?? 'inactivo' }}">{{ $status }}</span>
            <span>Adopción {{ $school['adopcion'] ?? 'nula' }}</span>
        </div>
        <i class="fa-solid fa-chevron-down school-chevron"></i>
    </button>
    <div class="school-card-body" x-show="open" x-cloak>
        {{ $slot }}
        <div style="margin-top:12px;">
            <a class="btn btn-ghost" href="{{ url('/super-admin/colegios/'.$school['id']) }}">Abrir ficha completa</a>
        </div>
    </div>
</article>
