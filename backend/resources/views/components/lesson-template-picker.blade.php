@once
<script>
window.novaLessonTemplate = window.novaLessonTemplate || @json(auth()->user()?->preferred_lesson_structure ?? 'clasica');
</script>
@endonce

@once
<!-- ══════════════════════════════════════════════════════════
     MODAL: Selector de Plantilla de Clase
     Se activa con el evento global 'nova-lesson-template-picker'
     ══════════════════════════════════════════════════════════ -->
<div
    x-data="lessonTemplatePicker()"
    @nova-lesson-template-picker.window="open($event.detail)"
    x-show="visible"
    x-cloak
    class="fixed inset-0 z-[300] flex items-center justify-center p-4"
    style="display:none"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/70 backdrop-blur-sm"
        @click="dismiss()"
    ></div>

    {{-- Panel --}}
    <div
        class="relative z-10 w-full max-w-4xl bg-[#0B0B1E] border border-white/10 rounded-3xl shadow-2xl overflow-hidden"
        x-show="visible"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{-- Glow decoration --}}
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-violet-700/20 blur-[100px] rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-cyan-500/10 blur-[80px] rounded-full pointer-events-none"></div>

        <div class="relative z-10 p-6 max-h-[90vh] overflow-y-auto">
            {{-- Header --}}
            <div class="flex items-start justify-between mb-1">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-600 to-fuchsia-500 flex items-center justify-center shadow-lg shadow-violet-900/30 flex-shrink-0">
                        <i class="fas fa-palette text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-white font-bold text-lg leading-tight">¿Cómo quieres ver tus clases?</h2>
                        <p class="text-gray-400 text-xs mt-0.5">Elige la estructura pedagógica que mejor refleja tu forma de enseñar. Puedes cambiarla cuando quieras.</p>
                    </div>
                </div>
                <button @click="dismiss()" class="text-gray-500 hover:text-white transition-colors p-1 ml-3 flex-shrink-0">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="h-px bg-white/5 my-4"></div>

            {{-- 3 Template Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <template x-for="tpl in templates" :key="tpl.id">
                    <button
                        type="button"
                        @click="selected = tpl.id"
                        :class="selected === tpl.id
                            ? 'ring-2 ring-violet-500 bg-violet-950/60'
                            : 'bg-white/3 hover:bg-white/6'"
                        class="rounded-2xl p-4 border border-white/8 transition-all duration-200 text-left flex flex-col gap-3 cursor-pointer"
                    >
                        {{-- Card header --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-white font-bold text-sm" x-text="tpl.name"></span>
                                    <span
                                        x-show="selected === tpl.id"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-violet-500/20 text-violet-300 text-[10px] font-semibold"
                                    >
                                        <i class="fas fa-check text-[8px]"></i> Activa
                                    </span>
                                </div>
                                <p class="text-gray-400 text-xs mt-1 leading-relaxed" x-text="tpl.description"></p>
                            </div>
                        </div>

                        {{-- Section pills --}}
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="s in tpl.sections" :key="s.label">
                                <span
                                    class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide"
                                    :style="`background:${s.color}22;color:${s.color}`"
                                    x-text="s.label"
                                ></span>
                            </template>
                        </div>

                        {{-- Live preview --}}
                        <div class="space-y-1.5 mt-1">
                            <div class="text-[9px] text-gray-500 uppercase tracking-widest font-semibold">Vista previa</div>
                            <template x-for="s in tpl.sections" :key="'prev-'+s.label">
                                <div
                                    class="rounded-lg p-2"
                                    :style="`border-left:2px solid ${s.color};background:${s.color}10`"
                                >
                                    <div
                                        class="text-[9px] font-black uppercase tracking-wider mb-1"
                                        :style="`color:${s.color}`"
                                        x-text="s.label"
                                    ></div>
                                    <div
                                        class="text-[11px] text-gray-400 line-clamp-2 leading-snug"
                                        x-text="s.preview"
                                    ></div>
                                </div>
                            </template>
                        </div>
                    </button>
                </template>
            </div>

            {{-- Pedagogy note --}}
            <div class="flex items-start gap-2 bg-white/3 rounded-xl p-3 mb-5 border border-white/5">
                <i class="fas fa-graduation-cap text-violet-400 text-sm mt-0.5 flex-shrink-0"></i>
                <p class="text-gray-400 text-xs leading-relaxed">
                    <strong class="text-gray-300">¿Cuál es la mejor?</strong>
                    La estructura <em>Clásica</em> es la más usada en Latinoamérica y funciona para cualquier asignatura.
                    <em>Instrucción Directa</em> es ideal cuando introduces contenido nuevo de forma explícita.
                    <em>Modelo 5E</em> es excelente para ciencias. <em>Basado en Proyectos</em> encaja cuando el curso gira alrededor de un reto auténtico.
                    <strong class="text-gray-300">No hay una respuesta única</strong>: elige la que va con tu estilo.
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-3">
                <button
                    @click="dismiss()"
                    class="px-5 py-2.5 rounded-xl bg-white/5 text-gray-400 text-sm font-semibold hover:bg-white/10 transition-all"
                >
                    Cancelar
                </button>
                <button
                    @click="apply()"
                    :disabled="!selected"
                    class="flex-1 px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white text-sm font-bold shadow-lg shadow-violet-900/40 hover:scale-[1.01] active:scale-95 transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2"
                >
                    <i class="fas fa-check"></i>
                    <span x-text="selected ? 'Usar ' + templates.find(t=>t.id===selected)?.name : 'Elige un estilo'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function lessonTemplatePicker() {
    const TEMPLATES = [
        {
            id: 'clasica',
            name: 'Clásica',
            description: 'La estructura más utilizada en escuelas de Latinoamérica. Clara, sencilla y universal.',
            sections: [
                { label: 'INICIO',      color: '#7C3AED', preview: 'Se activan conocimientos previos. El docente plantea la pregunta guía y los objetivos de la clase.' },
                { label: 'DESARROLLO',  color: '#06B6D4', preview: 'Explicación del contenido, ejemplos prácticos y trabajo colaborativo o individual con la materia.' },
                { label: 'CIERRE',      color: '#22C55E', preview: 'Síntesis de aprendizajes. El alumno comunica lo aprendido y se realiza la evaluación formativa.' },
            ],
        },
        {
            id: 'directa',
            name: 'Instrucción Directa',
            description: 'Modelo explícito. Muy efectivo para introducir conceptos nuevos de forma estructurada.',
            sections: [
                { label: 'MOTIVACIÓN',        color: '#F59E0B', preview: 'Enlace con la experiencia previa del alumno. Se presenta el propósito y relevancia del contenido.' },
                { label: 'PRESENTACIÓN',      color: '#7C3AED', preview: 'El docente modela el nuevo contenido con claridad. Explicación paso a paso con ejemplos resueltos.' },
                { label: 'PRÁCTICA GUIADA',   color: '#06B6D4', preview: 'El alumno practica con apoyo del docente. Se resuelven ejercicios juntos y se corrigen errores.' },
                { label: 'CIERRE REFLEXIVO',  color: '#22C55E', preview: 'Reflexión sobre lo aprendido. El alumno aplica el concepto de forma autónoma y autoevalúa.' },
            ],
        },
        {
            id: 'constructivista',
            name: 'Modelo 5E',
            description: 'Constructivismo por descubrimiento. Ideal para ciencias y proyectos donde el alumno explora.',
            sections: [
                { label: 'ACTIVACIÓN',  color: '#EF4444', preview: 'Se detona la curiosidad. Pregunta provocadora o situación problemática que conecta con lo cotidiano.' },
                { label: 'EXPLORACIÓN', color: '#F59E0B', preview: 'Los alumnos exploran el fenómeno o concepto a través de experimentos, observaciones o lecturas.' },
                { label: 'EXPLICACIÓN', color: '#7C3AED', preview: 'El docente formaliza el concepto. Se utiliza el lenguaje disciplinar y se sistematiza el aprendizaje.' },
                { label: 'APLICACIÓN',  color: '#06B6D4', preview: 'Transferencia del conocimiento a situaciones nuevas. Resolución de problemas, proyectos o debates.' },
                { label: 'EVALUACIÓN',  color: '#22C55E', preview: 'Verificación del aprendizaje logrado. Autoevaluación, co-evaluación o prueba de cierre del tema.' },
            ],
        },
        {
            id: 'proyecto',
            name: 'Basado en Proyectos',
            description: 'Aprendizaje alrededor de un reto auténtico. Ideal para trabajo colaborativo y productos reales.',
            sections: [
                { label: 'DESAFÍO',        color: '#F59E0B', preview: 'Pregunta esencial o reto auténtico que da sentido al proyecto.' },
                { label: 'INVESTIGACIÓN',  color: '#7C3AED', preview: 'Los alumnos buscan información, evidencias y diseñan su plan de trabajo.' },
                { label: 'CREACIÓN',       color: '#06B6D4', preview: 'Elaboran un producto, prototipo o solución con iteraciones y feedback.' },
                { label: 'PRESENTACIÓN',   color: '#EC4899', preview: 'Comunican resultados ante la clase, familias o la comunidad.' },
                { label: 'REFLEXIÓN',      color: '#22C55E', preview: 'Metacognición, autoevaluación y aprendizajes que se pueden transferir.' },
            ],
        },
    ];

    return {
        visible:   false,
        selected:  window.novaLessonTemplate || 'clasica',
        templates: TEMPLATES,
        context:   {},

        open(detail = {}) {
            this.selected = window.novaLessonTemplate || 'clasica';
            this.context = detail || {};
            this.visible  = true;
        },

        dismiss() {
            this.visible = false;
        },

        async apply() {
            if (!this.selected) return;

            window.novaLessonTemplate = this.selected;
            const ctx = this.context || {};
            const activityIds = Array.isArray(ctx.activity_ids) ? ctx.activity_ids : [];
            if (ctx.activity_id) activityIds.push(ctx.activity_id);
            if (ctx.id && !ctx.planificacion_id) activityIds.push(ctx.id);

            try {
                const res = await fetch('/teacher/api/lesson-template', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        lesson_template: this.selected,
                        activity_id: ctx.activity_id || ctx.id || null,
                        activity_ids: activityIds,
                        planificacion_id: ctx.planificacion_id || null,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    console.warn('Could not save template preference', data);
                }
            } catch (e) {
                console.warn('Could not save template preference', e);
            }

            this.visible = false;

            window.dispatchEvent(new CustomEvent('ai-canvas-refresh'));
            window.dispatchEvent(new CustomEvent('ai-toast', {
                detail: {
                    message: 'Estilo aplicado. Las clases nuevas y las recién creadas usarán esta estructura.',
                    type: 'success',
                    icon: 'fa-check',
                },
            }));
        },
    };
}
</script>
@endonce
