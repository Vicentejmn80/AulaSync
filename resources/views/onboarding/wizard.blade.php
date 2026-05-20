<x-app-layout>
    <div class="onboarding-shell py-5">
        <div class="container">
            <div class="onboarding-card mx-auto animate__animated animate__fadeIn" x-data="onboardingWizard()">
                <div class="text-center mb-4">
                    <p class="onboarding-kicker mb-2">Nova Academy</p>
                    <h1 class="onboarding-title mb-2">Configura tu asistente inteligente</h1>
                    <p class="text-muted mb-0">Una experiencia personalizada según tu rol y contexto.</p>
                </div>

                <div class="onboarding-progress mb-4">
                    <div class="onboarding-progress-bar" :style="{ width: progress + '%' }"></div>
                </div>

                <form x-ref="wizardForm" method="POST" action="{{ route('onboarding.save') }}">
                    @csrf
                    {{-- role siempre en el DOM, valor reactivo de Alpine --}}
                    <input type="hidden" name="role" x-model="role">

                    @if($errors->any())
                        <div class="alert alert-danger py-3 mb-4">
                            <strong>Revisa los datos:</strong>
                            <ul class="mb-0 mt-2 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- PASO 1: x-show mantiene el DOM, solo oculta visualmente --}}
                    <div x-show="step === 1" x-cloak>
                        <div class="animate__animated animate__fadeIn">
                            <h2 class="h5 fw-bold mb-3 text-center">Paso 1 · Identidad</h2>
                            <p class="text-muted text-center mb-4">¿Cómo quieres que configuremos tu panel?</p>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <button type="button" class="role-card w-100" :class="{ 'active': role === 'profesor' }" @click="selectRole('profesor')">
                                        <span class="role-icon">👩‍🏫</span>
                                        <span class="role-title">Soy Docente</span>
                                        <span class="role-subtitle">Planificación de clases y actividades</span>
                                    </button>
                                </div>
                                <div class="col-12 col-md-6">
                                    <button type="button" class="role-card w-100" :class="{ 'active': role === 'director' }" @click="selectRole('director')">
                                        <span class="role-icon">🏫</span>
                                        <span class="role-title">Soy Director</span>
                                        <span class="role-subtitle">Visión institucional y liderazgo pedagógico</span>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-primary rounded-3 px-4" :disabled="!role" @click="nextStep">
                                    Continuar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- PASO 2: x-show → inputs permanecen en el DOM al enviar --}}
                    <div x-show="step === 2" x-cloak>
                        <div class="animate__animated animate__fadeIn">
                            <h2 class="h5 fw-bold mb-3 text-center">Paso 2 · Contexto</h2>

                            {{-- Sección Profesor --}}
                            <div x-show="role === 'profesor'">
                                <p class="text-muted text-center mb-4">Cuéntanos sobre tus clases para personalizar la IA.</p>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Nivel educativo</label>
                                        <select class="form-select rounded-3" name="nivel_educativo">
                                            <option value="">Selecciona...</option>
                                            <option value="primaria">Primaria</option>
                                            <option value="secundaria">Secundaria</option>
                                            <option value="bachillerato">Bachillerato</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Clases por semana</label>
                                        <input type="number" min="1" max="20" class="form-control rounded-3" name="clases_semana" placeholder="5">
                                    </div>
                                    
                                    {{-- NUEVA SECCIÓN: Materias con Subject Cards --}}
                                    <div class="col-12">
                                        <label class="form-label fw-semibold mb-3">Materias que enseñas *</label>
                                        <p class="text-muted small mb-3">Selecciona todas las que apliquen. Puedes agregar una personalizada al final.</p>
                                        
                                        {{-- Grid Responsivo: 2 cols móvil / 4 cols desktop --}}
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                            <x-onboarding.subject-card 
                                                value="matematicas" 
                                                label="Matemáticas" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="ciencias" 
                                                label="Ciencias" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="lenguaje" 
                                                label="Lenguaje" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="historia" 
                                                label="Historia" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="ingles" 
                                                label="Inglés" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="arte" 
                                                label="Arte" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="musica" 
                                                label="Música" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="educacion_fisica" 
                                                label="Ed. Física" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="tecnologia" 
                                                label="Tecnología" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>' 
                                            />
                                            <x-onboarding.subject-card 
                                                value="filosofia" 
                                                label="Filosofía" 
                                                icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' 
                                            />
                                            {{-- Tarjeta "Otro" --}}
                                            <x-onboarding.subject-card 
                                                value="otro" 
                                                label="Otra materia" 
                                                icon='<svg class="w-10 h-10 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' 
                                            />
                                        </div>
                                        
                                        {{-- Input personalizado (aparece con fade-in cuando se selecciona "Otro") --}}
                                        <div x-show="showCustomInput" x-cloak class="fade-in-custom">
                                            <label class="form-label text-muted small">Nombre de la materia personalizada *</label>
                                            <input 
                                                type="text" 
                                                x-model="customSubject"
                                                name="otra_materia" 
                                                class="form-control rounded-3" 
                                                placeholder="Ej: Robótica, Ajedrez, Emprendimiento..."
                                                maxlength="120"
                                            >
                                        </div>
                                        
                                        {{-- Hidden inputs para enviar las materias seleccionadas --}}
                                        <template x-for="subj in selectedSubjects.filter(s => s !== 'otro')" :key="subj">
                                            <input type="hidden" name="materias[]" :value="subj">
                                        </template>
                                        <input type="hidden" name="materias[]" value="otro" x-show="isOtroSelected" x-cloak>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Grados / cursos</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <label class="chip"><input type="checkbox" name="cursos[]" value="1ro"> 1ro</label>
                                            <label class="chip"><input type="checkbox" name="cursos[]" value="2do"> 2do</label>
                                            <label class="chip"><input type="checkbox" name="cursos[]" value="3ro"> 3ro</label>
                                            <label class="chip"><input type="checkbox" name="cursos[]" value="4to"> 4to</label>
                                            <label class="chip"><input type="checkbox" name="cursos[]" value="5to"> 5to</label>
                                            <label class="chip"><input type="checkbox" name="cursos[]" value="6to"> 6to</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Días de clase</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <label class="chip"><input type="checkbox" name="dias[]" value="lunes"> Lunes</label>
                                            <label class="chip"><input type="checkbox" name="dias[]" value="martes"> Martes</label>
                                            <label class="chip"><input type="checkbox" name="dias[]" value="miercoles"> Miércoles</label>
                                            <label class="chip"><input type="checkbox" name="dias[]" value="jueves"> Jueves</label>
                                            <label class="chip"><input type="checkbox" name="dias[]" value="viernes"> Viernes</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Sección Director --}}
                            <div x-show="role === 'director'">
                                <p class="text-muted text-center mb-4">Configura el contexto institucional para una IA estratégica.</p>
                                <div class="row g-3">
                                    <div class="col-12 col-lg-8">
                                        <label class="form-label">Nombre de la institución</label>
                                        <input type="text" class="form-control rounded-3" name="nombre_institucion" placeholder="Ej. Colegio San Martín" :required="role === 'director'">
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <label class="form-label">Logotipo</label>
                                        <div class="rounded-3 border border-dashed border-violet-300 bg-violet-50 px-3 py-2 text-sm text-violet-600">
                                            <i class="fa-solid fa-image me-1"></i> Placeholder institucional
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Cantidad de sedes</label>
                                        <input type="number" min="1" max="500" class="form-control rounded-3" name="cantidad_sedes" placeholder="1" :required="role === 'director'">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Período académico actual</label>
                                        <input type="text" class="form-control rounded-3" name="periodo_academico" placeholder="2026-2027" :required="role === 'director'">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Cantidad estimada de docentes</label>
                                        <input type="number" min="1" max="5000" class="form-control rounded-3" name="cantidad_docentes" placeholder="35">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Visión pedagógica</label>
                                        <textarea class="form-control rounded-3" rows="4" name="vision_pedagogica" placeholder="Describe tus objetivos institucionales..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-light rounded-3 px-4" @click="prevStep">Atrás</button>
                                <button type="button" class="btn btn-primary rounded-3 px-4" @click="handleSubmit">
                                    Finalizar configuración
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- PASO 3: Pantalla de carga mientras el form se envía --}}
                    <div x-show="step === 3" x-cloak>
                        <div class="animate__animated animate__fadeIn text-center py-5">
                            <div class="onboarding-spinner mx-auto mb-4"></div>
                            <h2 class="h5 fw-bold mb-2">Sincronizando tu espacio</h2>
                            <p class="text-muted mb-0" x-text="syncMessage"></p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        [x-cloak] { display: none !important; }
        .onboarding-shell {
            min-height: calc(100vh - 140px);
            background: radial-gradient(1200px 600px at 20% -10%, rgba(139, 92, 246, 0.18), transparent 60%),
                        radial-gradient(1000px 500px at 100% 10%, rgba(59, 130, 246, 0.12), transparent 60%),
                        #f8fafc;
        }
        .onboarding-card {
            max-width: 960px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #eef2ff;
            border-radius: 28px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            padding: 2.5rem;
        }
        .onboarding-kicker {
            font-size: 0.78rem;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            color: #6366f1;
            font-weight: 700;
        }
        .onboarding-title {
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #0f172a;
        }
        .onboarding-progress {
            height: 10px;
            background: #eef2ff;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
        }
        .onboarding-progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, #8b5cf6, #c455ed, #3b82f6);
            background-size: 200% 100%;
            transition: width 0.5s ease;
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .role-card {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: #fff;
            padding: 1.25rem;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            transition: all 0.25s ease;
        }
        .role-card:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(79, 70, 229, 0.12); }
        .role-card.active {
            border-color: #8b5cf6;
            box-shadow: 0 12px 26px rgba(79, 70, 229, 0.18);
            background: linear-gradient(180deg, #ffffff, #f8f5ff);
        }
        .role-icon { font-size: 1.6rem; }
        .role-title { font-weight: 700; color: #111827; }
        .role-subtitle { font-size: 0.9rem; color: #64748b; }
        .chip {
            border: 1px solid #dbeafe;
            background: #f8fbff;
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .chip input { accent-color: #6366f1; }
        .onboarding-spinner {
            width: 64px;
            height: 64px;
            border: 4px solid #e2e8f0;
            border-top-color: #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Subject Card Styles */
        .subject-card.selected {
            border-color: #8b5cf6 !important;
            background: linear-gradient(135deg, #f5f3ff 0%, #fdf4ff 100%) !important;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2), 0 10px 25px rgba(139, 92, 246, 0.15) !important;
        }
        
        /* Fade-in Animation */
        .fade-in-custom {
            animation: fadeInUp 0.4s ease-out forwards;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        function onboardingWizard() {
            return {
                step: 1,
                role: '',
                selectedSubjects: [],
                showCustomInput: false,
                customSubject: '',
                syncMessage: 'Configurando tu asistente...',
                syncMessages: [
                    'Configurando tu asistente...',
                    'Analizando currículo institucional...',
                    '¡Todo listo!'
                ],
                syncTimer: null,
                
                get progress() {
                    return this.step === 1 ? 33 : this.step === 2 ? 66 : 100;
                },
                
                get isOtroSelected() {
                    return this.selectedSubjects.includes('otro');
                },
                
                selectRole(value) {
                    this.role = value;
                },
                
                toggleSubject(value) {
                    const index = this.selectedSubjects.indexOf(value);
                    if (index > -1) {
                        this.selectedSubjects.splice(index, 1);
                    } else {
                        this.selectedSubjects.push(value);
                    }
                    
                    // Watch: Si "otro" se deselecciona, limpiar input
                    this.showCustomInput = this.isOtroSelected;
                    if (!this.showCustomInput) {
                        this.customSubject = '';
                    }
                },
                
                nextStep() {
                    if (!this.role) return;
                    this.step = 2;
                },
                
                prevStep() {
                    this.step = 1;
                },
                
                validateStep2() {
                    if (this.role === 'profesor') {
                        if (this.selectedSubjects.length === 0) {
                            alert('Por favor selecciona al menos una materia');
                            return false;
                        }
                        if (this.isOtroSelected && !this.customSubject.trim()) {
                            alert('Por favor escribe el nombre de la materia personalizada');
                            return false;
                        }
                    }
                    if (this.role === 'director') {
                        const form = this.$refs.wizardForm;
                        const requiredFields = [
                            ['nombre_institucion', 'Escribe el nombre de la institución'],
                            ['cantidad_sedes', 'Indica la cantidad de sedes'],
                            ['periodo_academico', 'Indica el período académico actual'],
                        ];

                        for (const [name, message] of requiredFields) {
                            const field = form.querySelector(`[name="${name}"]`);
                            if (!field || !String(field.value || '').trim()) {
                                alert(message);
                                field?.focus();
                                return false;
                            }
                        }
                    }
                    return true;
                },
                
                handleSubmit() {
                    if (!this.role) {
                        alert('Selecciona tu rol (Docente o Director) antes de continuar.');
                        return;
                    }
                    
                    // Validar paso 2
                    if (!this.validateStep2()) {
                        return;
                    }
                    
                    // Mostrar pantalla de carga
                    this.step = 3;
                    var index = 0;
                    this.syncMessage = this.syncMessages[index];
                    if (this.syncTimer) clearInterval(this.syncTimer);
                    this.syncTimer = setInterval(() => {
                        index = Math.min(index + 1, this.syncMessages.length - 1);
                        this.syncMessage = this.syncMessages[index];
                    }, 800);
                    
                    setTimeout(() => {
                        if (this.syncTimer) clearInterval(this.syncTimer);
                        this.$refs.wizardForm.submit();
                    }, 2400);
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
