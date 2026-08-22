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

                <form x-ref="wizardForm" method="POST" action="{{ url('/onboarding') }}">
                    @csrf
                    <input type="hidden" name="role" x-model="role">
                    <input type="hidden" name="school_code" x-model="schoolCode">
                    <input type="hidden" name="teacher_invite_code" x-model="teacherInviteCode">
                    <input type="hidden" name="lesson_template" x-model="lessonTemplate">
                    <input type="hidden" name="modelo_pedagogico" x-model="lessonTemplate">
                    <input type="hidden" name="clases_semana" x-model="clasesSemana">
                    <input type="hidden" name="duracion_clase" x-model="duracionClase">
                    <template x-for="day in classDays" :key="'day-'+day">
                        <input type="hidden" name="dias[]" :value="day">
                    </template>
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

                    {{-- DOCENTE: 3 pantallas (códigos → resumen → pedagogía) --}}
                    <div x-show="step === 2 && role === 'profesor'" x-cloak>
                        <div class="animate__animated animate__fadeIn">

                            {{-- PANTALLA 1: Validación de códigos --}}
                            <div x-show="teacherStep === 1">
                                <h2 class="h5 fw-bold mb-2 text-center text-slate-900">Valida tu vínculo escolar</h2>
                                <p class="text-slate-600 text-center mb-5">Usa el código de la institución y el código DOC- que te dio el director.</p>

                                <div class="mx-auto space-y-4" style="max-width:520px;">
                                    <div>
                                        <label class="ob-label" for="institutionCode">Código de la institución</label>
                                        <input id="institutionCode" type="text" class="ob-input"
                                               x-model="schoolCode"
                                               @input="onSchoolCodeInput"
                                               placeholder="Ej: DXX-6701"
                                               autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20">
                                    </div>
                                    <div>
                                        <label class="ob-label" for="teacherInviteCode">Código de invitación docente</label>
                                        <input id="teacherInviteCode" type="text" class="ob-input"
                                               x-model="teacherInviteCode"
                                               @input="onSchoolCodeInput"
                                               @keydown.enter.prevent="validateTeacherCodes"
                                               placeholder="Ej: DOC-8X92K"
                                               autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20">
                                    </div>
                                    <button type="button" class="ob-btn-primary w-100"
                                            :disabled="!schoolCode.trim() || !teacherInviteCode.trim() || validatingCode"
                                            @click="validateTeacherCodes">
                                        <span x-show="!validatingCode">Validar</span>
                                        <span x-show="validatingCode" x-cloak>
                                            <i class="fa-solid fa-spinner fa-spin me-1"></i> Validando…
                                        </span>
                                    </button>
                                    <p class="small mb-0 text-center"
                                       :class="{
                                           'text-success': schoolValidationStatus === 'ok',
                                           'text-danger': schoolValidationStatus === 'error',
                                           'text-slate-500': schoolValidationStatus === 'idle'
                                       }"
                                       x-text="schoolValidationMessage || 'Ambos códigos deben coincidir con el mismo colegio.'"></p>
                                </div>

                                <p class="text-center mt-4 mb-0">
                                    <button type="button" class="btn btn-link text-indigo-600 text-sm" @click="openDemoModal">
                                        ¿No tienes código? Entrar en modo independiente
                                    </button>
                                </p>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-light rounded-3 px-4" @click="step = 1">Atrás</button>
                                    <button type="button" class="ob-btn-primary" :disabled="!schoolValidated" @click="teacherStep = 2">
                                        Continuar <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- PANTALLA 2: Resumen académico --}}
                            <div x-show="teacherStep === 2" x-cloak>
                                <h2 class="h5 fw-bold mb-2 text-center text-slate-900">Tu aula ya está lista</h2>
                                <p class="text-slate-600 text-center mb-4">El director preconfiguró estos cursos. Al terminar, quedan vinculados a tu cuenta.</p>

                                <div class="wow-card mx-auto">
                                    <div class="flex items-start justify-between gap-3 mb-4">
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-[.2em] text-indigo-600 mb-1">Colegio</p>
                                            <h3 class="text-xl font-black text-slate-900 mb-0" x-text="validatedSchoolName || 'Tu institución'"></h3>
                                            <p class="text-sm text-slate-600 mb-0" x-show="validatedSchoolDirector">
                                                Director: <strong class="text-slate-800" x-text="validatedSchoolDirector"></strong>
                                            </p>
                                            <p class="text-sm text-slate-600 mb-0" x-show="validatedTeacherName">
                                                Perfil: <strong class="text-slate-800" x-text="validatedTeacherName"></strong>
                                            </p>
                                        </div>
                                        <div class="wow-stat">
                                            <span class="wow-stat-value" x-text="studentsTotal"></span>
                                            <span class="wow-stat-label">alumnos</span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                        <div class="wow-mini">
                                            <span class="wow-stat-value text-indigo-700" x-text="assignedCourses.length"></span>
                                            <span class="wow-stat-label">materias</span>
                                        </div>
                                        <div class="wow-mini">
                                            <span class="wow-stat-value text-violet-700" x-text="teacherInviteCode"></span>
                                            <span class="wow-stat-label">tu código</span>
                                        </div>
                                    </div>

                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Materias y grados asignados</p>
                                    <template x-if="assignedCourses.length">
                                        <ul class="space-y-2 mb-0">
                                            <template x-for="course in assignedCourses" :key="course.id || (course.subject_name + course.grade)">
                                                <li class="wow-course">
                                                    <div>
                                                        <strong class="text-slate-900" x-text="course.subject_name"></strong>
                                                        <span class="text-slate-600" x-text="' · ' + (course.grade || '') + (course.section ? ' / ' + course.section : '')"></span>
                                                    </div>
                                                    <span class="wow-badge" x-text="(course.students_count || 0) + ' alumno(s)'"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                    <p class="text-sm text-slate-500 mb-0" x-show="assignedCourses.length === 0">
                                        Aún no hay cursos en esta invitación. El director puede asignártelos; tú ya quedarás en el colegio.
                                    </p>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-light rounded-3 px-4" @click="teacherStep = 1">Atrás</button>
                                    <button type="button" class="ob-btn-primary" @click="teacherStep = 3">
                                        Continuar <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- PANTALLA 3: Configuración pedagógica --}}
                            <div x-show="teacherStep === 3" x-cloak>
                                <h2 class="h5 fw-bold mb-2 text-center text-slate-900">Personaliza tu IA</h2>
                                <p class="text-slate-600 text-center mb-5">Solo dos datos. Las planificaciones nuevas usarán este modelo automáticamente.</p>

                                <div class="mb-5">
                                    <label class="ob-label">Días y horas de clase</label>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <template x-for="opt in dayOptions" :key="opt.value">
                                            <button type="button" class="day-chip"
                                                    :class="{ 'on': classDays.includes(opt.value) }"
                                                    @click="toggleDay(opt.value)"
                                                    x-text="opt.label"></button>
                                        </template>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="ob-label" for="clasesSemana">Clases por semana</label>
                                            <input id="clasesSemana" type="number" min="1" max="20" class="ob-input" x-model.number="clasesSemana">
                                        </div>
                                        <div>
                                            <label class="ob-label" for="duracionClase">Minutos por clase</label>
                                            <input id="duracionClase" type="number" min="15" max="240" class="ob-input" x-model.number="duracionClase">
                                        </div>
                                    </div>
                                </div>

                                <label class="ob-label">Modelo de enseñanza</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-2">
                                    <button type="button" class="model-card" :class="{ 'on': lessonTemplate === 'clasica' }" @click="lessonTemplate = 'clasica'">
                                        <span class="model-emoji">📘</span>
                                        <strong>Clásico</strong>
                                        <small>Inicio · Desarrollo · Cierre</small>
                                    </button>
                                    <button type="button" class="model-card" :class="{ 'on': lessonTemplate === 'constructivista' }" @click="lessonTemplate = 'constructivista'">
                                        <span class="model-emoji">🔬</span>
                                        <strong>Modelo 5E</strong>
                                        <small>Explorar, explicar y aplicar</small>
                                    </button>
                                    <button type="button" class="model-card" :class="{ 'on': lessonTemplate === 'proyecto' }" @click="lessonTemplate = 'proyecto'">
                                        <span class="model-emoji">🛠️</span>
                                        <strong>Basado en Proyectos</strong>
                                        <small>Desafío, creación y reflexión</small>
                                    </button>
                                </div>
                                <p class="text-xs text-slate-500 mb-0">Puedes cambiarlo después desde el calendario. La IA no te lo volverá a preguntar.</p>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-light rounded-3 px-4" @click="teacherStep = 2">Atrás</button>
                                    <button type="button" class="ob-btn-primary" :disabled="classDays.length === 0 || !lessonTemplate" @click="handleSubmit">
                                        Ir a mi hub <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
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
        .ob-label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #334155;
        }
        .ob-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            background: #ffffff !important;
            color: #0f172a !important;
            font-weight: 600;
            padding: 0.75rem 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .ob-input::placeholder { color: #94a3b8 !important; font-weight: 500; }
        .ob-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .ob-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            border: 0;
            border-radius: 0.75rem;
            background: #4f46e5;
            color: #fff;
            font-weight: 700;
            padding: 0.7rem 1.25rem;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
        }
        .ob-btn-primary:hover:not(:disabled) { background: #4338ca; color: #fff; }
        .ob-btn-primary:disabled { opacity: .55; cursor: not-allowed; }
        .wow-card {
            max-width: 560px;
            border-radius: 1.25rem;
            border: 1px solid #e0e7ff;
            background: linear-gradient(180deg, #ffffff 0%, #eef2ff 100%);
            padding: 1.5rem;
            box-shadow: 0 18px 40px rgba(79, 70, 229, 0.12);
            text-align: left;
        }
        .wow-stat, .wow-mini {
            border-radius: 1rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: .65rem .85rem;
            text-align: center;
        }
        .wow-stat-value { display: block; font-weight: 900; color: #312e81; font-size: 1.15rem; }
        .wow-stat-label { display: block; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 700; }
        .wow-course {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: .85rem;
            padding: .7rem .9rem;
        }
        .wow-badge {
            flex-shrink: 0;
            font-size: .7rem;
            font-weight: 700;
            color: #4338ca;
            background: #eef2ff;
            border-radius: 999px;
            padding: .2rem .6rem;
        }
        .day-chip {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            border-radius: 999px;
            padding: .4rem .85rem;
            font-size: .85rem;
            font-weight: 600;
        }
        .day-chip.on { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        .model-card {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            text-align: left;
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 1rem;
            padding: 1rem;
        }
        .model-card strong { color: #0f172a; }
        .model-card small { color: #64748b; }
        .model-card.on { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.18); background: #eef2ff; }
        .model-emoji { font-size: 1.3rem; }
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
        html.dark .ob-label { color: #e2e8f0; }
        html.dark .ob-input,
        html.dark .ob-input:focus {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1;
        }
        html.dark .wow-card { background: linear-gradient(180deg, #ffffff 0%, #eef2ff 100%); }
        html.dark .wow-course, html.dark .wow-stat, html.dark .wow-mini, html.dark .model-card {
            background: #fff;
            color: #0f172a;
        }
        html.dark .model-card strong, html.dark .wow-course strong { color: #0f172a; }
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
                teacherStep: 1,
                teacherInviteCode: '',
                lessonTemplate: 'clasica',
                classDays: ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'],
                clasesSemana: 5,
                duracionClase: 45,
                studentsTotal: 0,
                validatedTeacherName: '',
                dayOptions: [
                    { value: 'lunes', label: 'Lunes' },
                    { value: 'martes', label: 'Martes' },
                    { value: 'miercoles', label: 'Miércoles' },
                    { value: 'jueves', label: 'Jueves' },
                    { value: 'viernes', label: 'Viernes' },
                    { value: 'sabado', label: 'Sábado' },
                ],
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
                    if (this.step === 1) return 15;
                    if (this.teacherStep === 1) return 40;
                    if (this.teacherStep === 2) return 70;
                    return 92;
                },

                get isOtroSelected() {
                    return this.selectedSubjects.includes('otro');
                },

                selectRole(value) {
                    this.role = value;
                    this.teacherStep = 1;
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

                toggleDay(value) {
                    const index = this.classDays.indexOf(value);
                    if (index > -1) {
                        this.classDays.splice(index, 1);
                    } else {
                        this.classDays.push(value);
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
                        const response = await fetch('{{ url('/onboarding/demo') }}', {
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
                    this.teacherInviteCode = '';
                    this.schoolCodeType = '';
                    this.assignedCourses = [];
                    this.studentsTotal = 0;
                    this.validatedTeacherName = '';
                    this.schoolValidated = false;
                    this.validatingCode = false;
                    this.schoolValidationStatus = 'idle';
                    this.schoolValidationMessage = '';
                    this.validatedSchoolName = '';
                    this.validatedSchoolDirector = '';
                    this.teacherStep = 1;
                },

                onSchoolCodeInput() {
                    this.schoolValidated = false;
                    this.schoolCodeType = '';
                    this.assignedCourses = [];
                    this.studentsTotal = 0;
                    this.validatedTeacherName = '';
                    this.schoolValidationStatus = 'idle';
                    this.schoolValidationMessage = '';
                    this.validatedSchoolName = '';
                    this.validatedSchoolDirector = '';
                },

                async validateTeacherCodes() {
                    const school = this.schoolCode.trim();
                    const invite = this.teacherInviteCode.trim();
                    if (!school || !invite) {
                        this.schoolValidationStatus = 'error';
                        this.schoolValidationMessage = 'Ingresa el código de la institución y tu código DOC-.';
                        return;
                    }
                    this.validatingCode = true;
                    this.schoolValidationStatus = 'idle';
                    this.schoolValidationMessage = '';
                    try {
                        const response = await fetch('{{ url('/api/validate-school-code') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                school_code: school,
                                teacher_invite_code: invite,
                            }),
                        });
                        const payload = await response.json();
                        if (!response.ok || !payload.valid) {
                            this.schoolValidated = false;
                            this.schoolCodeType = '';
                            this.assignedCourses = [];
                            this.studentsTotal = 0;
                            this.schoolValidationStatus = 'error';
                            this.schoolValidationMessage = payload.message || 'Códigos no válidos.';
                            this.validatedSchoolName = '';
                            this.validatedSchoolDirector = '';
                            this.validatedTeacherName = '';
                            return;
                        }
                        this.schoolValidated = true;
                        this.schoolValidationStatus = 'ok';
                        this.schoolCodeType = payload.type || 'teacher_invite';
                        this.assignedCourses = payload.assigned_courses || [];
                        this.studentsTotal = payload.students_total ?? this.assignedCourses.reduce((sum, c) => sum + (c.students_count || 0), 0);
                        this.validatedSchoolName = payload.school?.name ?? '';
                        this.validatedSchoolDirector = payload.director ?? '';
                        this.validatedTeacherName = payload.teacher_name ?? '';
                        this.schoolValidationMessage = payload.message || 'Códigos válidos.';
                        this.teacherStep = 2;
                    } catch {
                        this.schoolValidated = false;
                        this.schoolValidationStatus = 'error';
                        this.schoolValidationMessage = 'No se pudo validar. Intenta nuevamente.';
                    } finally {
                        this.validatingCode = false;
                    }
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
                        const response = await fetch('{{ url('/api/validate-school-code') }}', {
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
                        if (!this.schoolCode.trim() || !this.teacherInviteCode.trim()) {
                            alert('Ingresa el código de la institución y tu código DOC-');
                            this.teacherStep = 1;
                            return false;
                        }
                        if (!this.schoolValidated) {
                            alert('Debes validar ambos códigos antes de finalizar');
                            this.teacherStep = 1;
                            return false;
                        }
                        if (!this.classDays.length) {
                            alert('Elige al menos un día de clase');
                            this.teacherStep = 3;
                            return false;
                        }
                        if (!this.lessonTemplate) {
                            alert('Elige un modelo de enseñanza');
                            this.teacherStep = 3;
                            return false;
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
                        const inviteInput = form?.querySelector('input[name="teacher_invite_code"]');
                        if (inviteInput) {
                            inviteInput.value = this.teacherInviteCode || '';
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
                        const response = await fetch('{{ url('/api/validate-family-code') }}', {
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
