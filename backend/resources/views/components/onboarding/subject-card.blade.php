@props(['value', 'label', 'icon'])

<div 
    @click="toggleSubject('{{ $value }}')"
    :class="{ 'selected': selectedSubjects.includes('{{ $value }}') }"
    class="subject-card group relative bg-white border-2 border-gray-200 rounded-2xl p-5 cursor-pointer transition-all duration-300 hover:border-violet-300 hover:shadow-lg hover:-translate-y-1 flex flex-col items-center gap-3"
    role="button"
    :aria-pressed="selectedSubjects.includes('{{ $value }}')"
    :aria-label="'Seleccionar materia: {{ $label }}'"
    tabindex="0"
    @keydown.enter="toggleSubject('{{ $value }}')"
    @keydown.space.prevent="toggleSubject('{{ $value }}')"
>
    {{-- Icono --}}
    <div class="subject-icon transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
        {!! $icon !!}
    </div>
    
    {{-- Label --}}
    <span class="text-sm font-semibold text-gray-700 group-[.selected]:text-violet-700 text-center">
        {{ $label }}
    </span>
    
    {{-- Checkmark cuando está seleccionado --}}
    <div class="absolute top-2 right-2 w-6 h-6 bg-violet-500 rounded-full flex items-center justify-center opacity-0 group-[.selected]:opacity-100 transition-opacity shadow-lg">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
</div>

<style>
    .subject-card.selected {
        @apply border-violet-500 bg-gradient-to-br from-violet-50 to-fuchsia-50 shadow-xl;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2);
    }
</style>
