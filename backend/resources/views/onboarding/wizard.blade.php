<x-onboarding-layout>
    @php($preselectedRole = $preselectedRole ?? '')
    <div class="onboarding-shell py-5">
        <button type="button" class="theme-toggle-btn" id="themeToggleBtn" aria-label="Cambiar tema">
            <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
        </button>
        <div class="container">
            <div class="onboarding-card mx-auto animate__animated animate__fadeIn" x-data="onboardingWizard">
                <div class="text-center mb-4">
                    <p class="onboarding-kicker mb-2">AulaSync</p>
                    <h1 class="onboarding-title mb-2">Configura tu asistente inteligente</h1>
                    <p class="text-muted mb-0">Una experiencia personalizada según tu rol y contexto.</p>
                </div>

                <div class="onboarding-progress mb-4">
                    <div class="onboarding-progress-bar" :style="{ width: progress + '%' }"></div>
                </div>

                <form x-ref="wizardForm" method="POST" action="{{ route('onboarding.save') }}">
                    @csrf
                    <input type="hidden" name="role" x-model="role">
                    <input type="hidden" name="school_code" x-model="schoolCode">
                    <input type="hidden" name="family_code" x-model="familyValidated ? familyCode : ''">
                    <template x-for="id in selectedStudentIds" :key="id">
                        <input type="hidden" name="student_ids[]" :value="id">
                    </template>

                    @php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag())
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

                    {{-- PASO 1: Selección de rol --}}
                    <div x-show="step === 1" x-cloak>
                        <div class="animate__animated animate__fadeIn">
                            <h2 class="h5 fw-bold mb-3 text-center">Paso 1 · Identidad</h2>
                            <p class="text-muted text-center mb-4">¿Cómo quieres que configuremos tu panel?</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <button type="button" class="role-card w-100" :class="{ 'active': role === 'profesor' }"
                                        @click="selectRole('profesor')">
                                    <span class="role-icon">
                                        <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </span>
                                    <span class="role-title">Soy Docente</span>
                                    <span class="role-subtitle">Planificación de clases y actividades</span>
                                </button>
                                <button type="button" class="role-card w-100" :class="{ 'active': role === 'representante' }"
                                        @click="selectRole('representante')">
                                    <span class="role-icon">
                                        <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </span>
                                    <span class="role-title">Soy Representante</span>
                                    <span class="role-subtitle">Seguimiento en vivo de calificaciones y rendimiento</span>
                                </button>
                                <button type="button" class="role-card w-100" :class="{ 'active': role === 'director' }"
                                        @click="selectRole('director')">
                                    <span class="role-icon">
                                        <svg class="w-8 h-8 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </span>
                                    <span class="role-title">Soy Director</span>
                                    <span class="role-subtitle">Visión institucional y liderazgo pedagógico</span>
                                </button>
                            </div>
                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-primary rounded-3 px-4" :disabled="!role" @click="nextStep">
                                    Continuar
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- PASO 2: Bifurcación Profesor (Tarjetas + Código + Perfil en un solo paso) --}}
                    <div x-show="step === 2 && role === 'profesor'" x-cloak>
                        <div class="animate__animated animate__fadeIn">
                            <h2 class="h5 fw-bold mb-3 text-center">¿Cómo te vinculas?</h2>
                            <p class="text-muted text-center mb-4">Elige cómo conectarte a tu institución educativa.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                {{-- Tarjeta 1: Tengo Código de Escuela --}}
                                <button type="button" class="bifurcated-card text-start w-100"
                                        :class="{ 'active': teacherPath === 'code' }"
                                        @click="teacherPath = 'code'; showSchoolCode = true;">
                                    <div class="flex flex-col items-center text-center gap-3 p-4">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-cyan-400 shadow-lg">
                                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        </div>
                                        <span class="role-title">Tengo Código de Escuela</span>
                                        <span class="role-subtitle">Ingresa el código que te dio tu director para vincularte al colegio.</span>
                                    </div>
                                </button>

                                {{-- Tarjeta 2: Soy Profesor Independiente --}}
                                <button type="button" class="bifurcated-card text-start w-100"
                                        :class="{ 'active': teacherPath === 'independent' }"
                                        @click="openDemoModal">
                                    <div class="flex flex-col items-center text-center gap-3 p-4">
                                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-fuchsia-500 to-violet-500 shadow-lg">
                                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                        <span class="role-title">Soy Profesor Independiente</span>
                                        <span class="role-subtitle">Activa tu suite personal de IA sin vínculo escolar.</span>
                                    </div>
                                </button>
                            </div>

                            {{-- Input de código escolar (se revela con animación) --}}
                            <div x-show="showSchoolCode" x-cloak x-transition:enter="fade-in-custom" class="mb-6" style="position:relative;z-index:20;">
                                <div class="rounded-2xl border border-violet-200/30 bg-white/[.045] p-4" @click.stop>
                                    <label for="teacherInviteCode" class="form-label fw-semibold mb-2">Código de invitación docente</label>
                                    <div class="d-flex flex-column flex-md-row gap-2">
                                        <input id="teacherInviteCode"
                                               type="text"
                                               name="school_code_visible"
                                               class="form-control rounded-3 flex-grow-1"
                                               style="pointer-events:auto;position:relative;z-index:21;"
                                               x-model="schoolCode"
                                               @input="onSchoolCodeInput"
                                               @keydown.enter.prevent="validateSchoolCode"
                                               placeholder="Ej: DOC-8X92K"
                                               autocomplete="off"
                                               autocapitalize="characters"
                                               spellcheck="false"
                                               maxlength="20"
                                               inputmode="text">
                                        <button type="button"
                                                class="btn btn-outline-primary rounded-3 px-3"
                                                :disabled="!schoolCode.trim() || validatingCode"
                                                @click="validateSchoolCode">
                                            <span x-show="!validatingCode">Validar</span>
                                            <span x-show="validatingCode">
                                                <i class="fa-solid fa-spinner fa-spin"></i> Validando...
                                            </span>
                                        </button>
                                    </div>
                                    <p class="small mt-2 mb-0"
                                       :class="{
                                           'text-success': schoolValidationStatus === 'ok',
                                           'text-danger': schoolValidationStatus === 'error',
                                           'text-muted': schoolValidationStatus === 'idle'
                                       }"
                                       x-text="schoolValidationMessage || 'Usa el código DOC- que te dio el director. También sirve el código institucional del colegio.'"></p>
                                    <template x-if="schoolValidated">
                                        <div class="small text-success mt-2">
                                            <i class="fa-solid fa-check-circle me-1"></i>
                                            Vinculado a <strong x-text="validatedSchoolName"></strong>
                                            <template x-if="validatedSchoolDirector">
                                                <span> · Director: <strong x-text="validatedSchoolDirector"></strong></span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Perfil docente (se revela solo tras validación exitosa) --}}
                            <div x-show="schoolValidated" x-cloak x-transition:enter="fade-in-custom" class="mb-4">
                                <hr class="border-white/10 my-6">
                                <template x-if="schoolCodeType === 'teacher_invite'">
                                    <div class="mb-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/5 p-4">
                                        <h3 class="h5 fw-bold mb-2 text-center">Tu aula ya está lista</h3>
                                        <p class="text-muted text-center mb-3">El director te asignó estos cursos. Al finalizar, los alumnos y la configuración quedan vinculados a tu cuenta.</p>
                                        <ul class="mb-0 ps-3">
                                            <template x-for="course in assignedCourses" :key="course.id || (course.subject_name + course.grade)">
                                                <li class="mb-1">
                                                    <strong x-text="course.subject_name"></strong>
                                                    · <span x-text="course.grade"></span>
                                                    <span x-show="course.section" x-text="' / ' + course.section"></span>
                                                    <span class="text-muted" x-show="course.students_count != null" x-text="' · ' + course.students_count + ' alumno(s)'"></span>
                                                </li>
                                            </template>
                                        </ul>
                                        <p class="small text-muted mt-3 mb-0" x-show="assignedCourses.length === 0">Aún no hay cursos asignados a este código. El director puede crearlos ahora; tú ya quedarás vinculado al colegio.</p>
                                    </div>
                                </template>
                                <h3 class="h5 fw-bold mb-3 text-center">Completa tu perfil docente</h3>
                                <p class="text-muted text-center mb-4" x-show="schoolCodeType === 'teacher_invite'">Solo tu perfil pedagógico. Las materias y alumnos los preparó el director.</p>
                                <p class="text-muted text-center mb-4" x-show="schoolCodeType !== 'teacher_invite'">El director te asigna las materias. Completa solo tu perfil pedagógico.</p>
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

                                    <div class="col-12" x-show="schoolCodeType !== 'teacher_invite'">
                                        <label class="form-label fw-semibold mb-3">Materias que enseñas *</label>
                                        <p class="text-muted small mb-3">Selecciona todas las que apliquen.</p>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                            <x-onboarding.subject-card value="matematicas" label="Matemáticas" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>' />
                                            <x-onboarding.subject-card value="ciencias" label="Ciencias" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>' />
                                            <x-onboarding.subject-card value="lenguaje" label="Lenguaje" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>' />
                                            <x-onboarding.subject-card value="historia" label="Historia" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' />
                                            <x-onboarding.subject-card value="ingles" label="Inglés" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>' />
                                            <x-onboarding.subject-card value="arte" label="Arte" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>' />
                                            <x-onboarding.subject-card value="musica" label="Música" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>' />
                                            <x-onboarding.subject-card value="educacion_fisica" label="Ed. Física" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/></svg>' />
                                            <x-onboarding.subject-card value="tecnologia" label="Tecnología" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>' />
                                            <x-onboarding.subject-card value="filosofia" label="Filosofía" icon='<svg class="w-10 h-10 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' />
                                            <x-onboarding.subject-card value="otro" label="Otra materia" icon='<svg class="w-10 h-10 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' />
                                        </div>
                                        <div x-show="showCustomInput" x-cloak class="fade-in-custom">
                                            <label class="form-label text-muted small">Nombre de la materia personalizada *</label>
                                            <input type="text" x-model="customSubject" class="form-control rounded-3" placeholder="Ej: Robótica, Ajedrez, Emprendimiento..." maxlength="120">
                                        </div>
                                        <input type="hidden" name="otra_materia" :value="customSubject">
                                        <template x-for="subj in selectedSubjects.filter(s => s !== 'otro')" :key="subj">
                                            <input type="hidden" name="materias[]" :value="subj">
                                        </template>
                                        <template x-if="isOtroSelected">
                                            <input type="hidden" name="materias[]" value="otro">
                                        </template>
                                    </div>

                                    <div class="col-12" x-show="schoolCodeType !== 'teacher_invite'">
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

                            {{-- Botones de navegación --}}
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-light rounded-3 px-4" @click="teacherPath = ''; showSchoolCode = false; step = 1">Atrás</button>
                                <button type="button" class="btn btn-primary rounded-3 px-4" :disabled="!schoolValidated" @click="handleSubmit">
                                    Finalizar configuración <i class="fa-solid fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- PASO 2 (Director): Configuración institucional --}}
                    <div x-show="step === 2 && role === 'director'" x-cloak>
                        <div class="animate__animated animate__fadeIn">
                            <h2 class="h5 fw-bold mb-3 text-center">Paso 2 · Tu institución</h2>
                            <p class="text-muted text-center mb-4">Configura el contexto institucional para una IA estratégica.</p>
                            <div class="row g-3">
                                <div class="col-12 col-lg-8">
                                    <label class="form-label">Nombre de la institución</label>
                                    <input type="text" class="form-control rounded-3" name="nombre_institucion" placeholder="Ej. Colegio San Martín">
                                </div>
                                <div class="col-12 col-lg-4">
                                    <label class="form-label">Logotipo</label>
                                    <div class="rounded-3 border border-dashed border-violet-300/30 bg-white/[.045] px-3 py-2 text-sm text-slate-400">
                                        <i class="fa-solid fa-image me-1"></i> Placeholder institucional
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Cantidad de sedes</label>
                                    <input type="number" min="1" max="500" class="form-control rounded-3" name="cantidad_sedes" placeholder="1">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Período académico actual</label>
                                    <input type="text" class="form-control rounded-3" name="periodo_academico" placeholder="2026-2027">
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
                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-light rounded-3 px-4" @click="prevStep">Atrás</button>
                                <button type="button" class="btn btn-primary rounded-3 px-4" @click="handleSubmit">
                                    Finalizar configuración
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- PASO 2 (Representante): Colegio + familia + confirmar hijos --}}
                    <div x-show="step === 2 && role === 'representante'" x-cloak>
                        <div class="animate__animated animate__fadeIn">
                            <h2 class="h5 fw-bold mb-3 text-center">Paso 2 · Vincula a tu familia</h2>
                            <p class="text-muted text-center mb-4">Primero el código del colegio (el mismo que usan los docentes). Después el código familiar NV- que te dio la escuela.</p>

                            <div class="mx-auto" style="max-width:520px;">
                                <div class="rounded-2xl border border-violet-200/30 bg-white/[.045] p-4 mb-4">
                                    <label class="form-label fw-semibold mb-2">Código de colegio</label>
                                    <div class="d-flex flex-column flex-md-row gap-2">
                                        <input type="text"
                                               class="form-control rounded-3 flex-grow-1"
                                               x-model="schoolCode"
                                               placeholder="Ej: NIC-4620"
                                               @input="onSchoolCodeInput(); resetFamilyValidation()">
                                        <button type="button"
                                                class="btn btn-outline-primary rounded-3 px-3"
                                                :disabled="!schoolCode.trim() || validatingCode"
                                                @click="validateSchoolCode">
                                            <span x-show="!validatingCode">Validar colegio</span>
                                            <span x-show="validatingCode"><i class="fa-solid fa-spinner fa-spin"></i></span>
                                        </button>
                                    </div>
                                    <p class="small mt-2 mb-0"
                                       :class="{ 'text-success': schoolValidationStatus === 'ok', 'text-danger': schoolValidationStatus === 'error', 'text-muted': schoolValidationStatus === 'idle' }"
                                       x-text="schoolValidationMessage || 'Te lo comparte el director o aparece en las circulares del colegio.'"></p>
                                    <template x-if="schoolValidated">
                                        <div class="small text-success mt-2">
                                            <i class="fa-solid fa-check-circle me-1"></i>
                                            Colegio: <strong x-text="validatedSchoolName"></strong>
                                        </div>
                                    </template>
                                </div>

                                <div class="rounded-2xl border border-violet-200/30 bg-white/[.045] p-4 mb-4" x-show="schoolValidated" x-cloak>
                                    <label class="form-label fw-semibold mb-2">Código familiar</label>
                                    <input type="text"
                                           class="form-control rounded-3 mb-3"
                                           x-model="familyCode"
                                           placeholder="Ej: NV-A3K2-XP"
                                           @input="onFamilyCodeInput"
                                           maxlength="20">
                                    <button type="button"
                                            class="btn btn-primary rounded-3 w-100 py-2"
                                            :disabled="!familyCode.trim() || validatingFamilyCode"
                                            @click="validateFamilyCode">
                                        <span x-show="!validatingFamilyCode">Buscar a mis representados</span>
                                        <span x-show="validatingFamilyCode"><i class="fa-solid fa-spinner fa-spin"></i> Buscando...</span>
                                    </button>
                                    <p class="small mt-3 mb-0"
                                       :class="{ 'text-success': familyValidationStatus === 'ok', 'text-danger': familyValidationStatus === 'error', 'text-muted': familyValidationStatus === 'idle' }"
                                       x-text="familyValidationMessage || 'Está en la boleta o te lo envió el docente al matricular al alumno.'"></p>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-light rounded-3 px-4" @click="step = 1">Atrás</button>
                                <button type="button" class="btn btn-primary rounded-3 px-4" :disabled="!familyConfirmed" @click="handleSubmit">
                                    Entrar al panel familiar <i class="fa-solid fa-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- PASO de carga --}}
                    <div x-show="step === 99" x-cloak>
                        <div class="animate__animated animate__fadeIn text-center py-5">
                            <div class="onboarding-spinner mx-auto mb-4"></div>
                            <h2 class="h5 fw-bold mb-2">Sincronizando tu espacio</h2>
                            <p class="text-muted mb-0" x-text="syncMessage"></p>
                        </div>
                    </div>
                </form>

                {{-- MODAL: Profesor Independiente --}}
                <div x-show="showDemoModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @click.self="closeDemoModal">
                    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
                    <div class="relative w-full max-w-md rounded-[2rem] border border-white/10 bg-slate-900 p-8 shadow-2xl"
                         @click.stop>
                        <button @click="closeDemoModal" class="absolute right-4 top-4 text-slate-400 hover:text-white transition">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>

                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-fuchsia-500 to-violet-500 shadow-lg">
                            <i class="fa-solid fa-rocket text-2xl text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white text-center mb-2">Modo Independiente</h3>
                        <p class="text-sm text-slate-400 text-center mb-6">
                            Estás activando tu suite de IA personal. Disfruta de todas las herramientas sin necesidad de vínculo escolar.
                        </p>

                        <div class="mb-6">
                            <label class="form-label text-sm text-slate-300 mb-2 block">Código de Licencia Premium</label>
                            <input type="text" x-model="licenseCode"
                                   class="form-control rounded-3 w-full"
                                   placeholder="Ej: AulaSync-PREMIUM-2026">
                            <p class="text-xs text-slate-500 mt-1">Déjalo vacío si quieres empezar en modo demo.</p>
                        </div>

                        <div class="flex flex-col gap-3">
                            <button type="button"
                                    @click="joinAsIndependent"
                                    class="w-full rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg transition hover:shadow-xl hover:-translate-y-0.5">
                                <i class="fa-solid fa-sparkles me-2"></i>
                                Ingresar en Modo Demo Libre
                            </button>
                            <button type="button"
                                    @click="closeDemoModal"
                                    class="w-full rounded-2xl border border-white/10 bg-white/5 px-5 py-3.5 text-sm font-semibold text-slate-300 transition hover:bg-white/10">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                {{-- MODAL: Confirmar representados --}}
                <div x-show="showFamilyModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @click.self="showFamilyModal = false">
                    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
                    <div class="relative w-full max-w-lg rounded-[2rem] border border-white/10 bg-slate-900 p-8 shadow-2xl"
                         @click.stop>
                        <button @click="showFamilyModal = false" class="absolute right-4 top-4 text-slate-400 hover:text-white transition">
                            <i class="fa-solid fa-xmark text-xl"></i>
                        </button>
                        <p class="text-xs font-bold uppercase tracking-widest text-cyan-300 mb-2">Confirmación familiar</p>
                        <h3 class="text-xl font-bold text-white mb-2">Eres representante de:</h3>
                        <p class="text-sm text-slate-400 mb-4">
                            Selecciona a los alumnos de esta familia en
                            <strong class="text-white" x-text="familyValidatedSchool || validatedSchoolName"></strong>.
                        </p>
                        <div class="space-y-2 max-h-72 overflow-y-auto mb-5">
                            <template x-for="student in familyStudents" :key="student.id">
                                <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 cursor-pointer">
                                    <input type="checkbox" :value="String(student.id)" x-model="selectedStudentIds">
                                    <span>
                                        <strong class="text-white" x-text="student.name"></strong>
                                        <span class="block text-xs text-slate-400" x-text="[student.grade, student.section].filter(Boolean).join(' / ')"></span>
                                    </span>
                                </label>
                            </template>
                        </div>
                        <button type="button"
                                class="w-full rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-500 px-5 py-3.5 text-sm font-bold text-white"
                                :disabled="selectedStudentIds.length === 0"
                                @click="confirmFamily()">
                            Confirmar y continuar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        .onboarding-shell {
            min-height: 100vh;
            background: radial-gradient(1200px 600px at 20% -10%, rgba(139, 92, 246, 0.18), transparent 60%),
                        radial-gradient(1000px 500px at 100% 10%, rgba(59, 130, 246, 0.12), transparent 60%),
                        var(--bg-primary);
        }
        .onboarding-card {
            max-width: 960px;
            background: var(--bg-card);
            border: 1px solid rgba(148, 163, 184, 0.2);
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
            color: var(--text-primary);
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
        .role-card, .bifurcated-card {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: #fff;
            padding: 1.25rem;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .role-card:hover, .bifurcated-card:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(79, 70, 229, 0.12); }
        .role-card.active, .bifurcated-card.active {
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
        @keyframes spin { to { transform: rotate(360deg); } }
        .subject-card.selected {
            border-color: #8b5cf6 !important;
            background: linear-gradient(135deg, #f5f3ff 0%, #fdf4ff 100%) !important;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2), 0 10px 25px rgba(139, 92, 246, 0.15) !important;
        }
        .fade-in-custom { animation: fadeInUp 0.4s ease-out forwards; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .onboarding-card .form-label { color: var(--text-secondary); font-weight: 600; }
        .onboarding-card .form-control,
        .onboarding-card .form-select,
        .onboarding-card textarea,
        .onboarding-card input[type="text"],
        .onboarding-card input[type="number"] {
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        .onboarding-card .form-control:focus,
        .onboarding-card .form-select:focus,
        .onboarding-card textarea:focus,
        .onboarding-card input:focus {
            outline: none;
            background: #ffffff;
            color: var(--text-primary);
            border-color: rgba(124, 58, 237, 0.55);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.18);
        }
        .onboarding-card .text-muted { color: var(--text-secondary) !important; }
        .theme-toggle-btn {
            position: fixed; top: 20px; right: 24px; z-index: 50;
            width: 44px; height: 44px; border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(255, 255, 255, 0.8); color: #1f2937;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
        }
        .theme-toggle-btn:hover { transform: translateY(-1px); }
        html.dark .onboarding-shell { background: var(--bg-primary); }
        html.dark .onboarding-card { background: var(--bg-card); border-color: var(--nova-glass-border); box-shadow: var(--nova-shadow); }
        html.dark .onboarding-title { color: var(--text-primary); }
        html.dark .onboarding-kicker { color: var(--nova-violet); }
        html.dark .onboarding-progress { background: var(--bg-tertiary); }
        html.dark .role-card, html.dark .bifurcated-card { background: var(--bg-card); border-color: var(--nova-glass-border); }
        html.dark .role-card.active, html.dark .bifurcated-card.active { border-color: var(--nova-violet); background: linear-gradient(180deg, var(--bg-secondary), rgba(124,58,237,.08)); }
        html.dark .role-title { color: var(--text-primary); }
        html.dark .role-subtitle { color: var(--text-secondary); }
        html.dark .chip { background: var(--bg-tertiary); border-color: var(--nova-glass-border); color: var(--text-primary); }
        html.dark .onboarding-spinner { border-color: var(--bg-tertiary); border-top-color: var(--nova-violet); }
        html.dark .onboarding-card .form-control,
        html.dark .onboarding-card .form-select,
        html.dark .onboarding-card textarea,
        html.dark .onboarding-card input[type="text"],
        html.dark .onboarding-card input[type="number"] {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(148, 163, 184, 0.28);
            color: #f8fafc;
        }
        html.dark .onboarding-card .form-control:focus,
        html.dark .onboarding-card .form-select:focus,
        html.dark .onboarding-card textarea:focus,
        html.dark .onboarding-card input:focus {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(139, 92, 246, 0.65);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            color: #ffffff;
        }
        html.dark .theme-toggle-btn { background: rgba(15, 23, 42, 0.75); border-color: rgba(148, 163, 184, 0.28); color: #e2e8f0; }
    </style>
    @endpush

    @push('scripts')
    <script>
        (function () {
            const btn = document.getElementById('themeToggleBtn');
            const icon = document.getElementById('themeToggleIcon');
            if (!btn || !icon) return;
            const setIcon = () => {
                icon.className = document.documentElement.classList.contains('dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            };
            setIcon();
            btn.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('nova-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                setIcon();
            });
        })();

        document.addEventListener('alpine:init', () => {
            Alpine.data('onboardingWizard', () => ({
                step: @json($preselectedRole ? 2 : 1),
                role: @json($preselectedRole ?? ''),
                teacherPath: @json(($preselectedRole ?? '') === 'profesor' ? 'code' : ''),
                showSchoolCode: @json(($preselectedRole ?? '') === 'profesor' || ($preselectedRole ?? '') === 'representante'),
                showDemoModal: false,
                licenseCode: '',
                selectedSubjects: [],
                showCustomInput: false,
                customSubject: '',
                schoolCode: '',
                schoolCodeType: '',
                assignedCourses: [],
                schoolValidated: false,
                validatingCode: false,
                schoolValidationStatus: 'idle',
                schoolValidationMessage: '',
                validatedSchoolName: '',
                validatedSchoolDirector: '',
                syncMessage: 'Configurando tu asistente...',
                syncMessages: [
                    'Configurando tu asistente...',
                    'Analizando currículo institucional...',
                    '¡Todo listo!'
                ],
                syncTimer: null,
                familyCode: '',
                familyValidated: false,
                validatingFamilyCode: false,
                familyValidationStatus: 'idle',
                familyValidationMessage: '',
                familyValidatedStudent: null,
                familyValidatedSchool: '',
                familyStudents: [],
                selectedStudentIds: [],
                familyConfirmed: false,
                showFamilyModal: false,

                get progress() {
                    if (this.role === 'director' || this.role === 'representante') {
                        return this.step === 1 ? 33 : 100;
                    }
                    return this.step === 1 ? 20 : (this.schoolValidated ? 80 : 45);
                },

                get isOtroSelected() {
                    return this.selectedSubjects.includes('otro');
                },

                selectRole(value) {
                    this.role = value;
                    if (value !== 'profesor') {
                        this.resetSchoolValidation();
                        this.teacherPath = '';
                        this.showSchoolCode = false;
                    }
                    if (value !== 'representante') {
                        this.resetFamilyValidation();
                    }
                    if (value === 'representante' || value === 'profesor') {
                        this.showSchoolCode = true;
                    }
                },

                toggleSubject(value) {
                    const index = this.selectedSubjects.indexOf(value);
                    if (index > -1) {
                        this.selectedSubjects.splice(index, 1);
                    } else {
                        this.selectedSubjects.push(value);
                    }
                    this.showCustomInput = this.isOtroSelected;
                    if (!this.showCustomInput) this.customSubject = '';
                },

                nextStep() {
                    if (!this.role) return;
                    this.step = 2;
                },

                prevStep() {
                    this.step = 1;
                },

                openDemoModal() {
                    this.showDemoModal = true;
                    this.teacherPath = 'independent';
                },

                closeDemoModal() {
                    this.showDemoModal = false;
                    this.teacherPath = '';
                    this.licenseCode = '';
                },

                async joinAsIndependent() {
                    try {
                        const response = await fetch('{{ route('onboarding.demo') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ license_code: this.licenseCode }),
                        });
                        const payload = await response.json();
                        if (payload.redirect) {
                            window.location.href = payload.redirect;
                        }
                    } catch {
                        alert('Error al conectar. Intenta nuevamente.');
                    }
                },

                resetSchoolValidation() {
                    this.schoolCode = '';
                    this.schoolCodeType = '';
                    this.assignedCourses = [];
                    this.schoolValidated = false;
                    this.validatingCode = false;
                    this.schoolValidationStatus = 'idle';
                    this.schoolValidationMessage = '';
                    this.validatedSchoolName = '';
                    this.validatedSchoolDirector = '';
                },

                onSchoolCodeInput() {
                    this.schoolValidated = false;
                    this.schoolCodeType = '';
                    this.assignedCourses = [];
                    this.schoolValidationStatus = 'idle';
                    this.schoolValidationMessage = '';
                    this.validatedSchoolName = '';
                    this.validatedSchoolDirector = '';
                },

                async validateSchoolCode() {
                    const code = this.schoolCode.trim();
                    if (!code) {
                        this.schoolValidationStatus = 'error';
                        this.schoolValidationMessage = 'Debes ingresar un código de escuela.';
                        return;
                    }
                    this.validatingCode = true;
                    this.schoolValidationStatus = 'idle';
                    this.schoolValidationMessage = '';
                    try {
                        const response = await fetch('{{ route('api.validate-school-code') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ school_code: code }),
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.valid) {
                            this.schoolValidated = false;
                            this.schoolCodeType = '';
                            this.assignedCourses = [];
                            this.schoolValidationStatus = 'error';
                            this.schoolValidationMessage = payload.message || 'Código no válido.';
                            this.validatedSchoolName = '';
                            this.validatedSchoolDirector = '';
                            return;
                        }
                        this.schoolValidated = true;
                        this.schoolValidationStatus = 'ok';
                        this.schoolCodeType = payload.type || 'school';
                        this.assignedCourses = payload.assigned_courses || [];
                        this.validatedSchoolName = payload.school?.name ?? '';
                        this.validatedSchoolDirector = payload.director ?? '';
                        this.schoolValidationMessage = payload.message || 'Código válido. Colegio verificado correctamente.';
                    } catch {
                        this.schoolValidated = false;
                        this.schoolValidationStatus = 'error';
                        this.schoolValidationMessage = 'No se pudo validar el código. Intenta nuevamente.';
                    } finally {
                        this.validatingCode = false;
                    }
                },

                validateStep2() {
                    if (this.role === 'profesor') {
                        if (!this.schoolCode.trim()) {
                            alert('Ingresa el código de escuela para continuar');
                            return false;
                        }
                        if (!this.schoolValidated) {
                            alert('Debes validar un código de escuela válido antes de finalizar');
                            return false;
                        }
                        if (this.schoolCodeType !== 'teacher_invite') {
                            if (this.selectedSubjects.length === 0) {
                                alert('Por favor selecciona al menos una materia');
                                return false;
                            }
                            if (this.isOtroSelected && !this.customSubject.trim()) {
                                alert('Por favor escribe el nombre de la materia personalizada');
                                return false;
                            }
                        }
                    }
                    if (this.role === 'representante') {
                        if (!this.schoolValidated) {
                            alert('Primero valida el código de tu colegio.');
                            return false;
                        }
                        if (!this.familyCode.trim()) {
                            alert('Ingresa el código familiar para continuar');
                            return false;
                        }
                        if (!this.familyConfirmed || this.selectedStudentIds.length === 0) {
                            alert('Confirma a qué alumnos representas.');
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
                        alert('Selecciona tu rol antes de continuar.');
                        return;
                    }
                    if (!this.validateStep2()) return;
                    this.step = 99;
                    let index = 0;
                    this.syncMessage = this.syncMessages[index];
                    if (this.syncTimer) clearInterval(this.syncTimer);
                    this.syncTimer = setInterval(() => {
                        index = Math.min(index + 1, this.syncMessages.length - 1);
                        this.syncMessage = this.syncMessages[index];
                    }, 800);
                    setTimeout(() => {
                        if (this.syncTimer) clearInterval(this.syncTimer);
                        const form = this.$refs.wizardForm;
                        const schoolInput = form?.querySelector('input[name="school_code"]');
                        if (schoolInput) {
                            schoolInput.value = this.schoolCode || '';
                        }
                        form.submit();
                    }, 2400);
                },

                onFamilyCodeInput() {
                    this.familyValidated = false;
                    this.familyConfirmed = false;
                    this.familyValidationStatus = 'idle';
                    this.familyValidationMessage = '';
                    this.familyValidatedStudent = null;
                    this.familyValidatedSchool = '';
                    this.familyStudents = [];
                    this.selectedStudentIds = [];
                },

                resetFamilyValidation() {
                    this.familyCode = '';
                    this.onFamilyCodeInput();
                },

                confirmFamily() {
                    this.selectedStudentIds = this.selectedStudentIds.map(id => Number(id));
                    if (this.selectedStudentIds.length === 0) return;
                    this.familyConfirmed = true;
                    this.familyValidated = true;
                    this.showFamilyModal = false;
                    this.familyValidationMessage = 'Familia confirmada. Ya puedes entrar a tu panel.';
                    this.familyValidationStatus = 'ok';
                },

                async validateFamilyCode() {
                    const code = this.familyCode.trim().toUpperCase();
                    if (!code) {
                        this.familyValidationStatus = 'error';
                        this.familyValidationMessage = 'Debes ingresar un código familiar.';
                        return;
                    }
                    if (!this.schoolValidated) {
                        this.familyValidationStatus = 'error';
                        this.familyValidationMessage = 'Primero valida el código de tu colegio.';
                        return;
                    }

                    this.validatingFamilyCode = true;
                    this.familyValidationStatus = 'idle';
                    this.familyValidationMessage = '';

                    try {
                        const response = await fetch('{{ route('api.validate-family-code') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ family_code: code, school_code: this.schoolCode }),
                        });
                        const payload = await response.json();

                        if (!response.ok || !payload.valid) {
                            this.familyValidated = false;
                            this.familyConfirmed = false;
                            this.familyValidationStatus = 'error';
                            this.familyValidationMessage = payload.message || 'Código no encontrado.';
                            this.familyStudents = [];
                            return;
                        }

                        this.familyStudents = payload.students || (payload.student ? [payload.student] : []);
                        this.selectedStudentIds = this.familyStudents.map(s => String(s.id));
                        this.familyValidatedStudent = this.familyStudents[0] || null;
                        this.familyValidatedSchool = payload.school?.name || this.validatedSchoolName;
                        this.familyValidated = true;
                        this.familyValidationStatus = 'ok';
                        this.familyValidationMessage = 'Encontramos a tu familia. Confirma los nombres.';
                        this.showFamilyModal = true;
                    } catch {
                        this.familyValidated = false;
                        this.familyValidationStatus = 'error';
                        this.familyValidationMessage = 'No se pudo validar el código. Intenta nuevamente.';
                        this.familyStudents = [];
                    } finally {
                        this.validatingFamilyCode = false;
                    }
                },
            }));
        });
    </script>
    @endpush
</x-onboarding-layout>
