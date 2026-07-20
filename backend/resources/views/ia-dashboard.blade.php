@php return; @endphp
{{--<x-app-layout>
    <div id="app-dashboard-root" class="dashboard-root {{ ($role ?? 'profesor') === 'director' ? 'dashboard-state-editor' : 'dashboard-state-form' }}" data-onboarding="{{ json_encode($onboardingData ?? []) }}" data-initial-plan="{{ json_encode($initialPlan ?? null) }}">
        <!-- ========== FASE 1: FORMULARIO INICIAL ========== -->
        @if(($role ?? 'profesor') !== 'director')
        <div class="dashboard-phase dashboard-phase-form animate__animated animate__fadeIn">
            <div class="container py-5">
                <div class="card dashboard-hero text-white p-5 mb-5" style="border-radius: 24px;">
                    <div class="dashboard-hero-bg"><i class="fas fa-brain"></i></div>
                    <div class="position-relative">
                        <h1 class="display-5 fw-bold mb-3">Diseña actividades asombrosas</h1>
                        <p class="lead opacity-85 mb-0">Usa la IA para planificar tus clases en segundos.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="card dashboard-card p-4 mb-4">
                            <h5 class="mb-4"><i class="fas fa-magic me-2 text-primary"></i>¿Qué quieres enseñar hoy?</h5>
                            <textarea id="temaClase" class="form-control border-0 p-3 bg-light rounded-3" rows="4" placeholder="Ej: Las leyes de Newton para 5to grado..." style="resize: none;"></textarea>
                            <button type="button" id="btnGenerar" class="btn btn-primary btn-lg w-100 mt-3 rounded-3 fw-bold py-3">
                                <i class="fas fa-bolt me-2"></i> GENERAR PLANIFICACIÓN
                            </button>
                            <p class="text-uppercase text-muted small fw-bold mt-4 mb-2">Temas sugeridos</p>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge tema-badge" data-tema="Fotosíntesis para primaria">🌿 Fotosíntesis</span>
                                <span class="badge tema-badge" data-tema="Álgebra básica: Ecuaciones de 1er grado">🔢 Álgebra</span>
                                <span class="badge tema-badge" data-tema="Guerra Mundial II: Causas y consecuencias">📜 Historia</span>
                                <span class="badge tema-badge" data-tema="El ciclo del agua para niños">💧 Ciclo del Agua</span>
                            </div>
                        </div>
                        @if(isset($recentPlans) && $recentPlans->count())
                        <div class="card dashboard-card p-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Tus planificaciones recientes</h6>
                            <div class="d-flex flex-column gap-2">
                                @foreach($recentPlans as $recentPlan)
                                    <a href="{{ route('dashboard', ['plan' => $recentPlan->id]) }}" class="dashboard-recent-link">
                                        <span class="fw-semibold">{{ $recentPlan->tema ?: 'Sin tema' }}</span>
                                        <span class="small text-muted">{{ $recentPlan->created_at?->format('d/m H:i') }}</span>
                                    </a>
                                @endforeach
                            </div>
                            <a href="{{ route('historial') }}" class="btn btn-light rounded-3 mt-3 align-self-start">
                                <i class="fas fa-folder-open me-1"></i>Ver todo mi historial
                            </a>
                        </div>
                        @endif
                    </div>
                    <div class="col-lg-5">
                        <div class="card dashboard-card h-100 p-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3 text-primary">Espacio de Trabajo</h6>
                            <div id="resultadoIA" class="dashboard-workspace-placeholder dashboard-ia-esperando">
                                <i class="fas fa-brain fa-3x text-primary opacity-25 mb-3"></i>
                                <p class="fw-bold text-dark mb-1">Hola Profe {{ $onboardingData['nombre'] ?? 'Docente' }}, hoy es {{ $onboardingData['dia_hoy'] ?? 'un gran día' }}.</p>
                                <p class="text-muted mb-2">¿Listo para planificar {{ $onboardingData['materia_principal'] ?? 'tus materias' }}?</p>
                                <p class="small text-muted">Escribe un tema abajo y haz clic en Generar.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- ========== FASE 2: CARGA INMERSIVA ========== -->
        <div class="dashboard-phase dashboard-phase-loading" aria-hidden="true">
            <div class="dashboard-loading-overlay">
                <div class="dashboard-loading-content">
                    <div class="dashboard-loading-brain animate__animated animate__pulse animate__infinite">
                        <i class="fas fa-brain"></i>
                    </div>
                    <p class="dashboard-loading-title">Creando tu planificación</p>
                    <p class="dashboard-loading-step" id="loadingStep">Analizando objetivos...</p>
                    <div class="dashboard-loading-emojis">
                        <span class="emoji-fly">📚</span><span class="emoji-fly">✏️</span><span class="emoji-fly">🎯</span><span class="emoji-fly">📋</span><span class="emoji-fly">💡</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== FASE 3: PANEL DASHBOARD (EDITOR) ========== -->
        <div class="dashboard-phase dashboard-phase-editor" aria-hidden="true">
            <aside class="dashboard-sidebar">
                <div class="dashboard-sidebar-header">
                    <a href="{{ route('dashboard') }}" class="dashboard-sidebar-brand">
                        <i class="fas fa-robot me-2"></i>Nova Academy
                    </a>
                </div>
                @if(($role ?? 'profesor') === 'director')
                <div class="dashboard-sidebar-badge">
                    <span class="badge bg-primary bg-opacity-10 text-primary w-100 py-2 rounded-3 text-start">
                        <i class="fas fa-school me-1"></i>
                        Director institucional
                        @if(!empty($onboardingData['modelo_pedagogico']))
                            <span class="d-block small opacity-75 mt-1">{{ ucfirst($onboardingData['modelo_pedagogico']) }}</span>
                        @endif
                    </span>
                </div>
                @elseif(isset($onboardingData) && !empty($onboardingData['materia_principal']) && ($onboardingData['materia_principal'] ?? '') !== 'hoy')
                <div class="dashboard-sidebar-badge">
                    <span class="badge bg-primary bg-opacity-10 text-primary w-100 py-2 rounded-3 text-start">
                        <i class="fas fa-chalkboard-teacher me-1"></i>
                        {{ $onboardingData['materia_principal'] }}
                        @if(!empty($onboardingData['nivel_educativo']))
                            <span class="d-block small opacity-75 mt-1">{{ ucfirst($onboardingData['nivel_educativo']) }}</span>
                        @endif
                    </span>
                </div>
                @endif
                <nav class="dashboard-sidebar-nav">
                    @if(($role ?? 'profesor') !== 'director')
                    <button type="button" class="dashboard-nav-item dashboard-nav-back" id="btnNuevaPlanificacion">
                        <i class="fas fa-plus-circle me-2"></i>Nueva planificación
                    </button>
                    @endif
                    <div class="dashboard-nav-section">Menú</div>
                    @if(($role ?? 'profesor') === 'director')
                    <a href="#" class="dashboard-nav-item" data-placeholder="reportes"><i class="fas fa-chart-pie me-2"></i>Reportes Institucionales</a>
                    <a href="#" class="dashboard-nav-item" data-placeholder="malla"><i class="fas fa-project-diagram me-2"></i>Configuración de Malla</a>
                    <a href="#" class="dashboard-nav-item" data-placeholder="estrategia"><i class="fas fa-bullseye me-2"></i>Estrategia Académica</a>
                    @else
                    {{-- ── Gestión Académica (Docente) ── --}}
                    <div class="dashboard-nav-section">Gestión Académica</div>
                    <a href="{{ route('teacher.courses.index') }}" class="dashboard-nav-item">
                        <i class="fas fa-chalkboard me-2"></i>Cursos y Alumnos
                    </a>
                    <a href="{{ route('teacher.activities.index') }}" class="dashboard-nav-item">
                        <i class="fas fa-clipboard-list me-2"></i>Actividades
                    </a>
                    <a href="{{ route('teacher.grades.index') }}" class="dashboard-nav-item">
                        <i class="fas fa-microphone-lines me-2"></i>Notas con IA
                    </a>
                    {{-- ── Planificación ── --}}
                    <div class="dashboard-nav-section mt-2">Planificación</div>
                    <a href="{{ route('historial') }}" class="dashboard-nav-item"><i class="fas fa-folder-open me-2"></i>Mi Historial</a>
                    <a href="#" class="dashboard-nav-item" data-placeholder="calendario"><i class="fas fa-calendar-alt me-2"></i>Mi Calendario</a>
                    <a href="#" class="dashboard-nav-item" data-placeholder="tareas"><i class="fas fa-tasks me-2"></i>Generador de Tareas</a>
                    <a href="#" class="dashboard-nav-item" data-placeholder="flashcards"><i class="fas fa-layer-group me-2"></i>Creador de Flashcards</a>
                    @endif
                    <div class="dashboard-nav-section mt-3">Cuenta</div>
                    <a href="#" class="dashboard-nav-item" data-placeholder="ajustes"><i class="fas fa-cog me-2"></i>Ajustes</a>
                </nav>
            </aside>

            <main class="dashboard-main">
                @if(($role ?? 'profesor') === 'director')
                <div class="dashboard-director-head mb-4">
                    <h2 class="h4 fw-bold mb-1">Panel de Gestión Institucional</h2>
                    <p class="text-muted mb-0">Visión ejecutiva de tu institución con asistencia estratégica de IA.</p>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="card border-0 dashboard-plan-pro-card h-100">
                            <div class="card-body">
                                <p class="text-uppercase small fw-bold text-muted mb-2">Estado de Planificaciones</p>
                                <h3 class="h5 fw-bold text-primary mb-1">85% completadas esta semana</h3>
                                <p class="small text-muted mb-0">Seguimiento de cumplimiento docente por nivel y área.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 dashboard-plan-pro-card h-100">
                            <div class="card-body">
                                <p class="text-uppercase small fw-bold text-muted mb-2">Enfoque Pedagógico</p>
                                <h3 class="h5 fw-bold text-primary mb-1">Prioridad: {{ $onboardingData['modelo_pedagogico'] ?? 'Modelo en definición' }}</h3>
                                <p class="small text-muted mb-0">Alineación curricular y recomendaciones institucionales.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 dashboard-plan-pro-card h-100">
                            <div class="card-body">
                                <p class="text-uppercase small fw-bold text-muted mb-2">Alertas Inteligentes</p>
                                <h3 class="h5 fw-bold text-primary mb-1">3 focos de mejora</h3>
                                <p class="small text-muted mb-0">Asistencia para intervenir oportunidades académicas clave.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 dashboard-card p-4 animate__animated animate__fadeInUp">
                    <h5 class="fw-bold mb-3"><i class="fas fa-chalkboard me-2 text-primary"></i>Asistente Estratégico</h5>
                    <p class="text-muted mb-3">Usa prompts ejecutivos para tareas de dirección institucional:</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="dashboard-toolbar-btn" data-placeholder="director-circular">Redactar circulares</button>
                        <button type="button" class="dashboard-toolbar-btn" data-placeholder="director-kpi">Analizar KPI escolares</button>
                        <button type="button" class="dashboard-toolbar-btn" data-placeholder="director-mejora">Crear planes de mejora docente</button>
                    </div>
                    <textarea class="form-control bg-light rounded-3 border-0 p-3" rows="5" placeholder="Escribe tu solicitud estratégica para la IA institucional..."></textarea>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-primary rounded-3 px-4" data-placeholder="director-enviar">
                            <i class="fas fa-paper-plane me-1"></i>Consultar IA estratégica
                        </button>
                    </div>
                </div>
                @else
                <div class="dashboard-toolbar">
                    <div class="dashboard-toolbar-actions">
                        <button
                            type="button"
                            class="dashboard-toolbar-btn bg-green-600 hover:bg-green-700 text-white"
                            id="btnGuardarPlan"
                            title="Guardar planificación completa en historial"
                        >
                            💾 Guardar en mi historial
                        </button>
                        <button type="button" class="dashboard-toolbar-btn" data-action="mas-dificil" title="Hacer más difícil"><i class="fas fa-chart-line me-1"></i>Más difícil</button>
                        <button type="button" class="dashboard-toolbar-btn" data-action="actividad-grupal" title="Añadir actividad grupal"><i class="fas fa-users me-1"></i>Actividad grupal</button>
                        <button type="button" class="dashboard-toolbar-btn" data-action="traducir" title="Traducir a Inglés"><i class="fas fa-language me-1"></i>Traducir</button>
                        <button type="button" class="dashboard-toolbar-btn dashboard-toolbar-btn-primary" data-action="pdf" title="Generar PDF"><i class="fas fa-file-pdf me-1"></i>Generar PDF</button>
                    </div>
                </div>

                <div class="dashboard-canvas-wrap">
                    <div id="dashboardCanvas" class="dashboard-canvas">
                        <!-- Se rellena por JS con tarjetas editables -->
                    </div>
                </div>

                <div class="dashboard-plan-pro">
                    <h5 class="dashboard-plan-pro-title mb-3"><i class="fas fa-graduation-cap me-2 text-primary"></i>Plan Pro — Nivel docente experto</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card dashboard-plan-pro-card h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary"><i class="fas fa-universal-access me-1"></i> Adecuación Curricular (NEE)</h6>
                                    <p class="small text-muted mb-2">Adapta Inicio, Desarrollo y Cierre para alumnado con necesidades específicas.</p>
                                    <select class="form-select form-select-sm mb-2" id="planProNeeCondition">
                                        <option value="TDAH">TDAH</option>
                                        <option value="Autismo (TEA)">Autismo (TEA)</option>
                                        <option value="Dislexia">Dislexia</option>
                                        <option value="Dificultades de atención">Dificultades de atención</option>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btnPlanProNee"><i class="fas fa-magic me-1"></i> Generar adecuación</button>
                                    <div id="planProNeeResult" class="dashboard-plan-pro-result mt-2"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card dashboard-plan-pro-card h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary"><i class="fas fa-calendar-alt me-1"></i> Calendario y Logística</h6>
                                    <p class="small text-muted mb-2">Duración por fase y recordatorios para tu agenda.</p>
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btnPlanProCalendario"><i class="fas fa-clock me-1"></i> Generar recordatorios</button>
                                    <div id="planProCalendarioResult" class="dashboard-plan-pro-result mt-2"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card dashboard-plan-pro-card h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary"><i class="fas fa-shopping-basket me-1"></i> Asistente de Materiales</h6>
                                    <p class="small text-muted mb-2">Lista de compras: básicos, específicos y recursos digitales.</p>
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btnPlanProMateriales"><i class="fas fa-list me-1"></i> Generar lista</button>
                                    <div id="planProMaterialesResult" class="dashboard-plan-pro-result mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-extras mt-4">
                    <div class="dashboard-extra-card" data-extra="flashcards">
                        <i class="fas fa-layer-group fa-2x text-primary mb-2"></i>
                        <h6 class="fw-bold">Flashcards</h6>
                        <p class="small text-muted mb-2">Genera tarjetas de estudio desde esta planificación.</p>
                        <span class="badge bg-light text-dark">Próximamente</span>
                    </div>
                    <div class="dashboard-extra-card" data-extra="tarea">
                        <i class="fas fa-print fa-2x text-primary mb-2"></i>
                        <h6 class="fw-bold">Hoja de tarea</h6>
                        <p class="small text-muted mb-2">Imprime una hoja de actividades lista para el aula.</p>
                        <span class="badge bg-light text-dark">Próximamente</span>
                    </div>
                </div>
                @endif
            </main>
        </div>
    </div>

    <!-- Toast para "Próximamente" -->
    <div id="dashboardToast" class="dashboard-toast" role="alert" aria-live="polite"></div>

    @push('styles')
    <style>
        /* ---------- Variables y base ---------- */
        .dashboard-root {
            --dash-primary: #6366f1;
            --dash-primary-dark: #4f46e5;
            --dash-electric: #0ea5e9;
            --dash-purple: #8b5cf6;
            --dash-bg: #f8fafc;
            --dash-card: #ffffff;
            --dash-radius: 24px;
            --dash-radius-sm: 16px;
            --dash-shadow: 0 4px 24px rgba(99, 102, 241, 0.08);
            --dash-shadow-soft: 0 10px 40px rgba(0, 0, 0, 0.06);
        }

        .dashboard-root { min-height: 100vh; background: var(--dash-bg); }

        /* ---------- Estados: qué fase se ve ---------- */
        .dashboard-state-form .dashboard-phase-form { display: block !important; }
        .dashboard-state-form .dashboard-phase-loading,
        .dashboard-state-form .dashboard-phase-editor { display: none !important; }

        .dashboard-state-loading .dashboard-phase-form { display: none !important; }
        .dashboard-state-loading .dashboard-phase-loading { display: flex !important; }
        .dashboard-state-loading .dashboard-phase-editor { display: none !important; }

        .dashboard-state-editor .dashboard-phase-form { display: none !important; }
        .dashboard-state-editor .dashboard-phase-loading { display: none !important; }
        .dashboard-state-editor .dashboard-phase-editor { display: flex !important; flex: 1; width: 100%; }

        /* ---------- Fase 1: Formulario ---------- */
        .dashboard-hero {
            background: linear-gradient(135deg, var(--dash-primary) 0%, var(--dash-purple) 100%);
            overflow: hidden;
            position: relative;
            box-shadow: var(--dash-shadow-soft);
        }
        .dashboard-hero-bg {
            position: absolute;
            top: 0; right: 0; bottom: 0;
            opacity: 0.12;
            font-size: 12rem;
            transform: rotate(-15deg);
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .dashboard-card {
            border: none;
            border-radius: var(--dash-radius-sm);
            box-shadow: var(--dash-shadow);
        }
        .tema-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 0.5rem 0.75rem;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .tema-badge:hover {
            transform: translateY(-2px);
            box-shadow: var(--dash-shadow);
        }
        .dashboard-recent-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 0.85rem;
            border-radius: 12px;
            background: #f8fafc;
            color: #0f172a;
            text-decoration: none;
            border: 1px solid #e2e8f0;
        }
        .dashboard-recent-link:hover {
            border-color: #c7d2fe;
            background: #f5f7ff;
        }
        .dashboard-director-head {
            background: #fff;
            border-radius: var(--dash-radius-sm);
            box-shadow: var(--dash-shadow);
            padding: 1rem 1.25rem;
        }
        .dashboard-workspace-placeholder {
            min-height: 280px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* ---------- Fase 2: Carga inmersiva ---------- */
        .dashboard-phase-loading {
            position: fixed;
            inset: 0;
            z-index: 9999;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #0ea5e9 100%);
        }
        .dashboard-loading-overlay {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        .dashboard-loading-content {
            text-align: center;
            color: #fff;
        }
        .dashboard-loading-brain {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.95;
        }
        .dashboard-loading-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .dashboard-loading-step {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        .dashboard-loading-emojis {
            font-size: 2rem;
            letter-spacing: 0.5rem;
        }
        .emoji-fly {
            display: inline-block;
            animation: emojiFloat 2s ease-in-out infinite;
        }
        .emoji-fly:nth-child(2) { animation-delay: 0.2s; }
        .emoji-fly:nth-child(3) { animation-delay: 0.4s; }
        .emoji-fly:nth-child(4) { animation-delay: 0.6s; }
        .emoji-fly:nth-child(5) { animation-delay: 0.8s; }
        @keyframes emojiFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* ---------- Fase 3: Sidebar + Main ---------- */
        .dashboard-phase-editor {
            min-height: 100vh;
            display: flex;
            flex: 1;
            width: 100%;
        }
        .dashboard-sidebar {
            width: 280px;
            min-width: 280px;
            background: var(--dash-card);
            border-radius: 0 var(--dash-radius) var(--dash-radius) 0;
            box-shadow: var(--dash-shadow-soft);
            padding: 1.5rem 0;
        }
        .dashboard-sidebar-brand {
            color: var(--dash-primary);
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            padding: 0 1.5rem;
            display: block;
        }
        .dashboard-sidebar-brand:hover { color: var(--dash-primary-dark); }
        .dashboard-sidebar-badge {
            padding: 0 1rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 0.5rem;
        }
        .dashboard-nav-section {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            padding: 0.75rem 1.5rem 0.25rem;
        }
        .dashboard-nav-item {
            display: flex;
            align-items: center;
            padding: 0.65rem 1.5rem;
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }
        .dashboard-nav-item:hover { background: #f1f5f9; color: var(--dash-primary); }
        .dashboard-nav-back {
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 1rem;
            color: var(--dash-primary);
        }
        .dashboard-nav-back:hover { background: rgba(99, 102, 241, 0.08); }

        /* ---------- Main: Toolbar + Canvas ---------- */
        .dashboard-main {
            flex: 1;
            min-width: 0;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        .dashboard-toolbar {
            background: var(--dash-card);
            border-radius: var(--dash-radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--dash-shadow);
        }
        .dashboard-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .dashboard-toolbar-btn {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .dashboard-toolbar-btn:hover {
            background: #f1f5f9;
            border-color: var(--dash-primary);
            color: var(--dash-primary);
        }
        .dashboard-toolbar-btn-primary {
            background: linear-gradient(135deg, var(--dash-primary), var(--dash-purple));
            color: #fff;
            border: none;
        }
        .dashboard-toolbar-btn-primary:hover {
            opacity: 0.95;
            color: #fff;
        }
        .dashboard-toolbar-btn.bg-green-600 {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
        .dashboard-toolbar-btn.bg-green-600:hover,
        .dashboard-toolbar-btn.hover\:bg-green-700:hover {
            background: #15803d;
            border-color: #15803d;
            color: #fff;
        }

        /* ---------- Canvas (tarjetas editables) ---------- */
        .dashboard-canvas-wrap { margin-bottom: 2rem; }
        .dashboard-canvas { display: flex; flex-direction: column; gap: 1.25rem; }
        .dashboard-canvas-card {
            background: var(--dash-card);
            border-radius: var(--dash-radius-sm);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--dash-shadow);
            border-left: 4px solid var(--dash-primary);
        }
        .dashboard-canvas-card.inicio { border-left-color: #10b981; }
        .dashboard-canvas-card.desarrollo { border-left-color: var(--dash-electric); }
        .dashboard-canvas-card.cierre { border-left-color: var(--dash-purple); }
        .dashboard-canvas-card.recursos { border-left-color: #94a3b8; }
        .dashboard-canvas-card-improve { position: relative; }
        .dashboard-improve-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            opacity: 0;
            transition: opacity 0.2s;
            border: none;
            background: rgba(99, 102, 241, 0.12);
            color: var(--dash-primary);
            padding: 0.35rem 0.75rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
        }
        .dashboard-canvas-card-improve:hover .dashboard-improve-btn { opacity: 1; }
        .dashboard-improve-btn:hover { background: rgba(99, 102, 241, 0.2); }
        .dashboard-improve-inline {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .dashboard-improve-inline.show { display: block; }
        .dashboard-improve-inline input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .dashboard-improve-inline input:focus { outline: none; border-color: var(--dash-primary); }
        .dashboard-canvas-card h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }
        .dashboard-canvas-card [contenteditable="true"],
        .dashboard-canvas-card .canvas-textarea {
            min-height: 2rem;
            padding: 0.5rem 0;
            border: none;
            border-bottom: 1px dashed #e2e8f0;
            background: transparent;
            outline: none;
            width: 100%;
            resize: none;
            font-size: 0.95rem;
        }
        .dashboard-canvas-card [contenteditable="true"]:focus,
        .dashboard-canvas-card .canvas-textarea:focus {
            border-bottom-color: var(--dash-primary);
        }
        .dashboard-canvas-list { list-style: none; padding: 0; margin: 0; }
        .dashboard-canvas-list li {
            padding: 0.5rem 0;
            border-bottom: 1px dashed #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .dashboard-canvas-list li:last-child { border-bottom: none; }
        .dashboard-canvas-list li [contenteditable="true"] { flex: 1; border: none; padding: 0; min-height: 1.5rem; }

        /* ---------- Plan Pro ---------- */
        .dashboard-plan-pro { margin-top: 2rem; }
        .dashboard-plan-pro-title { font-size: 1.1rem; color: #1e293b; }
        .dashboard-plan-pro-card {
            border-radius: var(--dash-radius-sm);
            box-shadow: var(--dash-shadow);
            border: none;
        }
        .dashboard-plan-pro-result {
            font-size: 0.85rem;
            max-height: 280px;
            overflow-y: auto;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .dashboard-plan-pro-result ul { margin: 0; padding-left: 1rem; }
        .dashboard-plan-pro-result .plan-pro-duracion { color: var(--dash-primary); font-weight: 600; }
        .dashboard-plan-pro-result .plan-pro-alerta { background: #fef3c7; padding: 0.35rem 0.5rem; border-radius: 8px; margin-top: 0.5rem; }

        /* ---------- Extras (Flashcards, Tarea) ---------- */
        .dashboard-extras {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
        }
        .dashboard-extra-card {
            background: var(--dash-card);
            border-radius: var(--dash-radius-sm);
            padding: 1.5rem;
            box-shadow: var(--dash-shadow);
            text-align: center;
            border: 2px dashed #e2e8f0;
        }
        .dashboard-extra-card:hover { border-color: var(--dash-primary); }

        /* ---------- Toast ---------- */
        .dashboard-toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 14px;
            font-weight: 500;
            box-shadow: var(--dash-shadow-soft);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
            z-index: 10000;
        }
        .dashboard-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    window.initialPlan = @json($initialPlan ?? null);
    (function() {
        'use strict';

        var LOADING_STEPS = [
            'Analizando objetivos...',
            'Diseñando actividades dinámicas...',
            'Estructurando inicio, desarrollo y cierre...',
            'Preparando recursos...',
            'Casi listo...'
        ];
        var stepIndex = 0;
        var stepInterval = null;

        function escaparHtml(texto) {
            if (texto == null || texto === '') return '';
            var div = document.createElement('div');
            div.textContent = texto;
            return div.innerHTML;
        }

        function extractTextValue(value) {
            if (value == null) return '';
            if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
                return String(value).trim();
            }
            if (Array.isArray(value)) {
                var arrTexts = [];
                for (var i = 0; i < value.length; i++) {
                    var t = extractTextValue(value[i]);
                    if (t) arrTexts.push(t);
                }
                return arrTexts.join(' | ');
            }
            if (typeof value === 'object') {
                if (Array.isArray(value.actividades)) {
                    return extractTextValue(value.actividades);
                }
                var preferredKeys = ['contenido', 'description', 'descripcion', 'texto', 'text', 'actividad', 'title', 'name', 'detalle'];
                for (var k = 0; k < preferredKeys.length; k++) {
                    var key = preferredKeys[k];
                    if (value[key] != null) {
                        var keyText = extractTextValue(value[key]);
                        if (keyText) return keyText;
                    }
                }
                try {
                    return JSON.stringify(value);
                } catch (e) {
                    return String(value);
                }
            }
            return String(value);
        }

        function normalizeActivities(input) {
            var result = [];
            function pushNormalized(item) {
                if (item == null) return;
                if (Array.isArray(item)) {
                    for (var i = 0; i < item.length; i++) pushNormalized(item[i]);
                    return;
                }
                if (typeof item === 'object' && Array.isArray(item.actividades)) {
                    pushNormalized(item.actividades);
                    return;
                }
                var text = extractTextValue(item);
                if (text) result.push(text);
            }
            pushNormalized(input);
            return result;
        }

        function getRoot() { return document.getElementById('app-dashboard-root'); }
        function setState(s) { var r = getRoot(); if (r) { r.className = 'dashboard-root dashboard-state-' + s; } }

        function showToast(msg) {
            var el = document.getElementById('dashboardToast');
            if (!el) return;
            el.textContent = msg;
            el.classList.add('show');
            setTimeout(function() { el.classList.remove('show'); }, 2500);
        }

        function startLoadingUI() {
            var stepEl = document.getElementById('loadingStep');
            if (stepEl) stepEl.textContent = LOADING_STEPS[0];
            stepIndex = 0;
            stepInterval = setInterval(function() {
                stepIndex = (stepIndex + 1) % LOADING_STEPS.length;
                if (stepEl) stepEl.textContent = LOADING_STEPS[stepIndex];
            }, 2200);
        }
        function stopLoadingUI() {
            if (stepInterval) { clearInterval(stepInterval); stepInterval = null; }
        }

        function resolvePlanShape(plan) {
            if (plan == null) return null;
            var current = plan;
            if (typeof current === 'string') {
                try { current = JSON.parse(current); } catch (e) { return null; }
            }
            if (!current || typeof current !== 'object') return null;
            if (typeof current.payload === 'string') {
                try { current.payload = JSON.parse(current.payload); } catch (e) {}
            }
            if (current.payload && typeof current.payload === 'object') {
                current = current.payload;
            }
            return current && typeof current === 'object' ? current : null;
        }

        function getNested(obj, path) {
            if (!obj || typeof obj !== 'object') return undefined;
            var parts = path.split('.');
            var cur = obj;
            for (var i = 0; i < parts.length; i++) {
                if (!cur || typeof cur !== 'object' || !(parts[i] in cur)) return undefined;
                cur = cur[parts[i]];
            }
            return cur;
        }

        function pickActivities(plan, section) {
            var candidates = [
                section + '.actividades',
                'payload.' + section + '.actividades',
                section,
                'payload.' + section,
                section + '_actividades',
                'payload.' + section + '_actividades'
            ];
            for (var i = 0; i < candidates.length; i++) {
                var value = getNested(plan, candidates[i]);
                var normalized = normalizeActivities(value);
                if (normalized.length) return normalized;
            }
            return [];
        }

        function showCanvasLoadingMessage(text) {
            var canvas = document.getElementById('dashboardCanvas');
            if (!canvas) return;
            canvas.innerHTML = '' +
                '<div class="dashboard-workspace-placeholder text-center py-5">' +
                '<i class="fas fa-spinner fa-spin fa-2x text-primary opacity-75 mb-3"></i>' +
                '<p class="fw-bold mb-1">' + escaparHtml(text || 'Cargando planificación...') + '</p>' +
                '<p class="small text-muted mb-0">Estamos preparando tu canvas editable.</p>' +
                '</div>';
        }

        function buildCanvas(plan) {
            console.log('Datos recibidos:', plan);
            var normalizedPlan = resolvePlanShape(plan);
            if (!normalizedPlan || typeof normalizedPlan !== 'object') return '';
            var tema = extractTextValue(normalizedPlan.tema || getNested(normalizedPlan, 'payload.tema')) || 'Plan de clase';
            var objetivo = extractTextValue(normalizedPlan.objetivo || getNested(normalizedPlan, 'payload.objetivo')) || '';
            var inicio = pickActivities(normalizedPlan, 'inicio');
            var desarrollo = pickActivities(normalizedPlan, 'desarrollo');
            var cierre = pickActivities(normalizedPlan, 'cierre');
            if (!inicio.length || !desarrollo.length || !cierre.length) {
                var fallbackWrappedPlan = (plan && typeof plan === 'object' && plan.payload) ? resolvePlanShape(plan.payload) : null;
                if (fallbackWrappedPlan) {
                    if (!inicio.length) inicio = pickActivities(fallbackWrappedPlan, 'inicio');
                    if (!desarrollo.length) desarrollo = pickActivities(fallbackWrappedPlan, 'desarrollo');
                    if (!cierre.length) cierre = pickActivities(fallbackWrappedPlan, 'cierre');
                }
            }
            if (!inicio.length && !desarrollo.length && !cierre.length) {
                console.warn('Advertencia: El plan no contiene secciones de inicio/desarrollo/cierre');
            }
            var recursos = normalizeActivities(normalizedPlan.recursos != null ? normalizedPlan.recursos : getNested(normalizedPlan, 'payload.recursos'));

            function listItems(arr) {
                var html = '<ul class="dashboard-canvas-list">';
                for (var i = 0; i < arr.length; i++) {
                    html += '<li><span class="text-primary me-2">•</span><span contenteditable="true">' + escaparHtml(arr[i]) + '</span></li>';
                }
                html += '</ul>';
                return html;
            }
            function sectionCard(phase, icon, title, arr) {
                return '<div class="dashboard-canvas-card ' + phase + ' dashboard-canvas-card-improve" data-phase="' + phase + '">' +
                    '<h2>' + icon + ' ' + title + '</h2>' +
                    '<div class="dashboard-canvas-card-body">' + listItems(arr) + '</div>' +
                    '<button type="button" class="dashboard-improve-btn">✨ Mejorar</button>' +
                    '<div class="dashboard-improve-inline">' +
                    '<input type="text" class="dashboard-improve-input" placeholder="Ej: hazlo más lúdico, usa materiales reciclados..." maxlength="500">' +
                    '<button type="button" class="btn btn-sm btn-primary btn-improve-send">Enviar a la IA</button>' +
                    '</div></div>';
            }

            var html = '';
            html += '<div class="dashboard-canvas-card"><h2>📌 Tema</h2><div contenteditable="true">' + escaparHtml(tema) + '</div></div>';
            html += '<div class="dashboard-canvas-card"><h2>🎯 Objetivo de aprendizaje</h2><div contenteditable="true">' + escaparHtml(objetivo) + '</div></div>';
            html += sectionCard('inicio', '🟢', 'Inicio', inicio);
            html += sectionCard('desarrollo', '🔵', 'Desarrollo', desarrollo);
            html += sectionCard('cierre', '🟣', 'Cierre', cierre);
            if (recursos.length) {
                html += '<div class="dashboard-canvas-card recursos"><h2>📎 Recursos</h2><div contenteditable="true">' + escaparHtml(recursos.join(', ')) + '</div></div>';
            }
            return html;
        }

        function getSectionContent(card) {
            var ul = card.querySelector('.dashboard-canvas-list');
            if (!ul) return '';
            var items = ul.querySelectorAll('li span[contenteditable="true"]');
            var parts = [];
            for (var i = 0; i < items.length; i++) {
                var t = items[i].textContent || items[i].innerText || '';
                if (t.trim()) parts.push(t.trim());
            }
            return parts.join('\n');
        }

        function setSectionContent(card, actividades) {
            var ul = card.querySelector('.dashboard-canvas-list');
            if (!ul) return;
            var safeActividades = normalizeActivities(actividades);
            ul.innerHTML = '';
            for (var i = 0; i < safeActividades.length; i++) {
                var li = document.createElement('li');
                li.innerHTML = '<span class="text-primary me-2">•</span><span contenteditable="true">' + escaparHtml(safeActividades[i]) + '</span>';
                ul.appendChild(li);
            }
        }

        function attachImproveHandlers(canvasEl) {
            if (!canvasEl) return;
            var cards = canvasEl.querySelectorAll('.dashboard-canvas-card-improve');
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '{{ csrf_token() }}';
            var url = '{{ route("ai.improve_section") }}';
            for (var c = 0; c < cards.length; c++) {
                (function(card) {
                    var phase = card.getAttribute('data-phase');
                    var btn = card.querySelector('.dashboard-improve-btn');
                    var inline = card.querySelector('.dashboard-improve-inline');
                    var input = card.querySelector('.dashboard-improve-input');
                    var sendBtn = card.querySelector('.btn-improve-send');
                    if (!btn || !inline || !input || !sendBtn) return;
                    btn.addEventListener('click', function() {
                        btn.style.display = 'none';
                        inline.classList.add('show');
                        input.value = '';
                        input.focus();
                    });
                    function hideInline() {
                        inline.classList.remove('show');
                        btn.style.display = '';
                    }
                    sendBtn.addEventListener('click', function() {
                        var instruction = input.value.trim();
                        if (!instruction) return;
                        sendBtn.disabled = true;
                        sendBtn.textContent = 'Esperando IA...';
                        var content = getSectionContent(card);
                        fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                            body: JSON.stringify({ phase: phase, content: content, instruction: instruction })
                        }).then(function(r) { return r.json(); }).then(function(data) {
                            if (data && data.success && data.actividades) {
                                setSectionContent(card, data.actividades);
                                showToast('Sección actualizada');
                            } else {
                                showToast((data && data.error) ? data.error : 'Error al mejorar');
                            }
                        }).catch(function(err) {
                            showToast(err && err.message ? err.message : 'Error de conexión');
                        }).finally(function() {
                            sendBtn.disabled = false;
                            sendBtn.textContent = 'Enviar a la IA';
                            hideInline();
                        });
                    });
                })(cards[c]);
            }
        }

        function getPlanFromCanvas() {
            var canvas = document.getElementById('dashboardCanvas');
            if (!canvas) return null;
            var cards = canvas.querySelectorAll('.dashboard-canvas-card');
            var plan = { tema: '', objetivo: '', inicio: { actividades: [] }, desarrollo: { actividades: [] }, cierre: { actividades: [] }, recursos: [] };
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var h2 = card.querySelector('h2');
                var title = h2 ? (h2.textContent || '').trim() : '';
                var ul = card.querySelector('.dashboard-canvas-list');
                var divEditable = card.querySelector('div[contenteditable="true"]');
                var text = divEditable ? (divEditable.textContent || '').trim() : '';
                if (ul) {
                    var items = ul.querySelectorAll('li span[contenteditable="true"]');
                    var arr = [];
                    for (var j = 0; j < items.length; j++) {
                        var t = (items[j].textContent || items[j].innerText || '').trim();
                        if (t) arr.push(t);
                    }
                    if (title.indexOf('Inicio') !== -1) plan.inicio.actividades = arr;
                    else if (title.indexOf('Desarrollo') !== -1) plan.desarrollo.actividades = arr;
                    else if (title.indexOf('Cierre') !== -1) plan.cierre.actividades = arr;
                } else {
                    if (title.indexOf('Tema') !== -1) plan.tema = text;
                    else if (title.indexOf('Objetivo') !== -1) plan.objetivo = text;
                    else if (title.indexOf('Recursos') !== -1) plan.recursos = text ? text.split(/[,;]/).map(function(s) { return s.trim(); }).filter(Boolean) : [];
                }
            }
            return plan;
        }

        function planProFetch(url, body, resultElId, renderFn) {
            var resultEl = document.getElementById(resultElId);
            if (!resultEl) return;
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '{{ csrf_token() }}';
            var plan = getPlanFromCanvas();
            if (!plan || (!plan.tema && !plan.objetivo && plan.inicio.actividades.length === 0)) {
                showToast('Genera primero una planificación arriba.');
                return;
            }
            resultEl.innerHTML = '<p class="small text-muted mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Generando...</p>';
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify(body)
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res.success && res.data) {
                    resultEl.innerHTML = renderFn(res.data);
                } else {
                    resultEl.innerHTML = '<p class="small text-danger mb-0">' + escaparHtml(res.error || 'Error al generar') + '</p>';
                }
            }).catch(function(err) {
                resultEl.innerHTML = '<p class="small text-danger mb-0">' + escaparHtml(err && err.message ? err.message : 'Error de conexión') + '</p>';
            });
        }

        function renderNee(data) {
            var d = data || {};
            var inicio = normalizeActivities(d.inicio && d.inicio.actividades != null ? d.inicio.actividades : d.inicio);
            var desarrollo = normalizeActivities(d.desarrollo && d.desarrollo.actividades != null ? d.desarrollo.actividades : d.desarrollo);
            var cierre = normalizeActivities(d.cierre && d.cierre.actividades != null ? d.cierre.actividades : d.cierre);
            function list(arr) {
                if (!arr.length) return '';
                var h = '<ul>';
                for (var i = 0; i < arr.length; i++) h += '<li>' + escaparHtml(arr[i]) + '</li>';
                return h + '</ul>';
            }
            return '<div class="small"><strong>Inicio</strong>' + list(inicio) + '<strong>Desarrollo</strong>' + list(desarrollo) + '<strong>Cierre</strong>' + list(cierre) + '</div>';
        }
        function renderCalendario(data) {
            var d = data || {};
            var h = '';
            if (d.duracion_inicio != null) h += '<p class="plan-pro-duracion mb-1">Inicio: ' + d.duracion_inicio + ' min</p>';
            if (d.duracion_desarrollo != null) h += '<p class="plan-pro-duracion mb-1">Desarrollo: ' + d.duracion_desarrollo + ' min</p>';
            if (d.duracion_cierre != null) h += '<p class="plan-pro-duracion mb-1">Cierre: ' + d.duracion_cierre + ' min</p>';
            if (d.recordatorios && d.recordatorios.length) {
                h += '<p class="mb-1 mt-2"><strong>Recordatorios</strong></p><ul>';
                for (var i = 0; i < d.recordatorios.length; i++) h += '<li>' + escaparHtml(d.recordatorios[i]) + '</li>';
                h += '</ul>';
            }
            if (d.alerta_agenda) h += '<div class="plan-pro-alerta">' + escaparHtml(d.alerta_agenda) + '</div>';
            return h || '<p class="text-muted small">Sin datos</p>';
        }
        function renderMateriales(data) {
            var d = data || {};
            var basicos = d.basicos && Array.isArray(d.basicos) ? d.basicos : [];
            var especificos = d.especificos && Array.isArray(d.especificos) ? d.especificos : [];
            var digitales = d.digitales && Array.isArray(d.digitales) ? d.digitales : [];
            function list(arr) {
                if (!arr.length) return '';
                var h = '<ul>';
                for (var i = 0; i < arr.length; i++) h += '<li>' + escaparHtml(arr[i]) + '</li>';
                return h + '</ul>';
            }
            return '<div class="small"><strong>Materiales básicos</strong>' + list(basicos) + '<strong>Específicos</strong>' + list(especificos) + '<strong>Recursos digitales</strong>' + list(digitales) + '</div>';
        }

        function runGenerate() {
            var temaEl = document.getElementById('temaClase');
            var tema = temaEl ? temaEl.value.trim() : '';
            if (!tema) {
                alert('Escribe un tema para generar la planificación.');
                return;
            }

            setState('loading');
            startLoadingUI();

            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '{{ csrf_token() }}';
            var url = '{{ route("ai.generate") }}';

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ prompt: tema })
            }).then(function(response) {
                var ct = response.headers.get('content-type') || '';
                if (ct.indexOf('application/json') === -1) {
                    return { success: false, error: 'El servidor no respondió correctamente.' };
                }
                return response.json();
            }).then(function(data) {
                stopLoadingUI();
                if (data && data.success && data.data) {
                    var canvas = document.getElementById('dashboardCanvas');
                    if (canvas) {
                        canvas.innerHTML = buildCanvas(data.data);
                        canvas.classList.add('animate__animated', 'animate__fadeIn');
                        attachImproveHandlers(canvas);
                    }
                    setState('editor');
                } else {
                    setState('form');
                    var res = document.getElementById('resultadoIA');
                    if (res) {
                        res.innerHTML = '<div class="alert alert-danger mb-0"><strong>Error</strong><br>' + escaparHtml((data && data.error) ? data.error : 'Error al generar.') + '</div>';
                    }
                }
            }).catch(function(err) {
                stopLoadingUI();
                setState('form');
                var res = document.getElementById('resultadoIA');
                if (res) res.innerHTML = '<div class="alert alert-danger mb-0"><strong>Error</strong><br>' + escaparHtml(err && err.message ? err.message : 'Error de conexión') + '</div>';
            });
        }

        function onReady() {
            var btn = document.getElementById('btnGenerar');
            if (btn) btn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); runGenerate(); });

            var root = document.getElementById('app-dashboard-root');
            if (root) {
                var initialPlanRaw = root.getAttribute('data-initial-plan');
                if (initialPlanRaw && initialPlanRaw !== 'null') {
                    try {
                        if (!window.initialPlan) {
                            window.initialPlan = JSON.parse(initialPlanRaw);
                        }
                    } catch (e) {
                        console.error('No se pudo parsear initialPlan', e);
                    }
                }

                var loadedPlan = resolvePlanShape(window.initialPlan);
                if (loadedPlan) {
                    setState('editor');
                    showCanvasLoadingMessage('Cargando planificación...');
                    setTimeout(function() {
                        var initialCanvas = document.getElementById('dashboardCanvas');
                        if (!initialCanvas) return;
                        var html = buildCanvas(loadedPlan);
                        if (!html) {
                            showCanvasLoadingMessage('No se pudo cargar la planificación');
                            return;
                        }
                        initialCanvas.innerHTML = html;
                        initialCanvas.classList.add('animate__animated', 'animate__fadeIn');
                        attachImproveHandlers(initialCanvas);
                    }, 0);
                }

                root.addEventListener('click', function(e) {
                    var t = e.target.closest('[data-tema]');
                    if (t && t.dataset.tema) {
                        var ta = document.getElementById('temaClase');
                        if (ta) { ta.value = t.dataset.tema; ta.focus(); }
                    }
                    var nav = e.target.closest('.dashboard-nav-item[data-placeholder]');
                    if (nav) {
                        e.preventDefault();
                        showToast('Próximamente');
                    }
                    var toolbar = e.target.closest('.dashboard-toolbar-btn');
                    if (toolbar) {
                        e.preventDefault();
                        showToast('Próximamente');
                    }
                });
            }

            var btnNueva = document.getElementById('btnNuevaPlanificacion');
            if (btnNueva) {
                btnNueva.addEventListener('click', function() {
                    setState('form');
                    var res = document.getElementById('resultadoIA');
                if (res) {
                    var root = document.getElementById('app-dashboard-root');
                    var ob = {};
                    try { ob = root && root.getAttribute('data-onboarding') ? JSON.parse(root.getAttribute('data-onboarding')) : {}; } catch (e) {}
                    var nombre = ob.nombre || 'Docente';
                    var diaHoy = ob.dia_hoy || 'un gran día';
                    var materia = ob.materia_principal || 'hoy';
                    res.innerHTML = '<div class="dashboard-workspace-placeholder dashboard-ia-esperando"><i class="fas fa-brain fa-3x text-primary opacity-25 mb-3"></i><p class="fw-bold text-dark mb-1">Hola ' + escaparHtml(nombre) + ', hoy es ' + escaparHtml(diaHoy) + '.</p><p class="text-muted mb-2">¿Listo para planificar tu clase de ' + escaparHtml(materia) + '?</p><p class="small text-muted">Escribe un tema y haz clic en Generar.</p></div>';
                }
                });
            }

            var btnGuardarPlan = document.getElementById('btnGuardarPlan');
            if (btnGuardarPlan) {
                btnGuardarPlan.addEventListener('click', function() {
                    var planData = getPlanFromCanvas();
                    if (!planData || (!planData.tema && !planData.objetivo && (!planData.inicio || planData.inicio.actividades.length === 0))) {
                        showToast('No hay planificación para guardar todavía.');
                        return;
                    }
                    var tokenEl = document.querySelector('meta[name="csrf-token"]');
                    var token = tokenEl ? tokenEl.getAttribute('content') : '{{ csrf_token() }}';
                    var urlSave = '{{ route("planning.save") }}';
                    btnGuardarPlan.disabled = true;
                    btnGuardarPlan.innerHTML = '💾 Guardando...';
                    console.log('Datos que voy a enviar a la DB:', planData);

                    fetch(urlSave, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ plan: planData })
                    }).then(function(r) {
                        return r.json();
                    }).then(function(data) {
                        if (data && data.success) {
                            showToast('Planificación guardada en historial');
                        } else {
                            showToast((data && data.error) ? data.error : 'No se pudo guardar');
                        }
                    }).catch(function(err) {
                        showToast(err && err.message ? err.message : 'Error de conexión al guardar');
                    }).finally(function() {
                        btnGuardarPlan.disabled = false;
                        btnGuardarPlan.innerHTML = '💾 Guardar en mi historial';
                    });
                });
            }

            var urlNee = '{{ route("ai.plan_pro.nee") }}';
            var urlCalendario = '{{ route("ai.plan_pro.calendario") }}';
            var urlMateriales = '{{ route("ai.plan_pro.materiales") }}';
            var btnNee = document.getElementById('btnPlanProNee');
            if (btnNee) {
                btnNee.addEventListener('click', function() {
                    var condition = document.getElementById('planProNeeCondition');
                    planProFetch(urlNee, { plan: getPlanFromCanvas(), condition: condition ? condition.value : 'TDAH' }, 'planProNeeResult', renderNee);
                });
            }
            var btnCal = document.getElementById('btnPlanProCalendario');
            if (btnCal) {
                btnCal.addEventListener('click', function() {
                    planProFetch(urlCalendario, { plan: getPlanFromCanvas() }, 'planProCalendarioResult', renderCalendario);
                });
            }
            var btnMat = document.getElementById('btnPlanProMateriales');
            if (btnMat) {
                btnMat.addEventListener('click', function() {
                    planProFetch(urlMateriales, { plan: getPlanFromCanvas() }, 'planProMaterialesResult', renderMateriales);
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', onReady);
        } else {
            onReady();
        }
    })();
    </script>
    @endpush
</x-app-layout>
--}}