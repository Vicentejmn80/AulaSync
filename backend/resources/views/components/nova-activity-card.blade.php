@props(['activity'])

@php
$bgColor = $activity['color'] ?? '#6366f1';
$title = $activity['title'] ?? 'Sin título';
$course = $activity['course'] ?? '';
$date = $activity['date'] ?? '';
$type = $activity['type'] ?? 'actividad';
$weight = $activity['weight'] ?? 0;
$activityId = $activity['id'] ?? 0;

$formattedDate = '';
if ($date) {
    try {
        $dt = \Carbon\Carbon::parse($date);
        $formattedDate = $dt->locale('es')->isoFormat('dddd D [de] MMMM');
    } catch (\Throwable $e) {
        $formattedDate = $date;
    }
}

$typeBadge = match($type) {
    'clase' => 'CLASE',
    'tarea' => 'TAREA',
    'actividad' => 'ACTIVIDAD',
    default => 'ACTIVIDAD',
};
@endphp

<div class="nova-activity-card group" 
     x-data="{ hover: false }"
     @mouseenter="hover = true"
     @mouseleave="hover = false">
    <div class="card-border-strip" style="background: {{ $bgColor }};"></div>
    <div class="card-content">
        <div class="card-header">
            <h4 class="card-title">{{ $title }}</h4>
            <div class="card-badges">
                <span class="badge badge-type">{{ $typeBadge }}</span>
                @if($weight > 0)
                    <span class="badge badge-weight">{{ $weight }}%</span>
                @endif
            </div>
        </div>
        <div class="card-meta">
            <span class="meta-item">
                <i class="fa-solid fa-book-open"></i>
                {{ $course }}
            </span>
            <span class="meta-item">
                <i class="fa-solid fa-calendar-day"></i>
                {{ $formattedDate }}
            </span>
        </div>
        <button 
            @click="window.dispatchEvent(new CustomEvent('open-activity-detail', { detail: {{ $activityId }} }))"
            class="card-action-btn"
            :class="{ 'active': hover }">
            Ver detalle →
        </button>
    </div>
</div>

<style>
.nova-activity-card {
    position: relative;
    background: white;
    border-radius: 0.875rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
    overflow: hidden;
    display: flex;
    margin-bottom: 0.75rem;
}

.nova-activity-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

html.dark .nova-activity-card {
    background: rgba(30, 41, 59, 0.4);
    border: 1px solid rgba(148, 163, 184, 0.1);
}

.card-border-strip {
    width: 4px;
    flex-shrink: 0;
}

.card-content {
    flex: 1;
    padding: 0.875rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
}

.card-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    line-height: 1.4;
}

html.dark .card-title {
    color: #e2e8f0;
}

.card-badges {
    display: flex;
    gap: 0.375rem;
    flex-shrink: 0;
}

.badge {
    font-size: 0.625rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.badge-type {
    background: linear-gradient(135deg, #c026d3 0%, #7c3aed 100%);
    color: white;
}

.badge-weight {
    background: #fef3c7;
    color: #78350f;
}

.card-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: #64748b;
    flex-wrap: wrap;
}

html.dark .card-meta {
    color: #94a3b8;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.meta-item i {
    font-size: 0.625rem;
    opacity: 0.7;
}

.card-action-btn {
    align-self: flex-start;
    font-size: 0.75rem;
    font-weight: 600;
    color: #7c3aed;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.375rem 0;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.card-action-btn:hover,
.card-action-btn.active {
    color: #c026d3;
    gap: 0.5rem;
}

html.dark .card-action-btn {
    color: #a78bfa;
}

html.dark .card-action-btn:hover {
    color: #e9d5ff;
}
</style>
