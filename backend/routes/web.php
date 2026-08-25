<?php

use App\Http\Controllers\AICommandHandlerController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AiChatHistoryController;
use App\Http\Controllers\CodesRevealController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\Director\AcademicPeriodController as DirectorAcademicPeriodController;
use App\Http\Controllers\Director\ActivityFeedbackController;
use App\Http\Controllers\Director\AICommandController as DirectorAICommandController;
use App\Http\Controllers\Director\AttendanceDashboardController;
use App\Http\Controllers\Director\CourseController as DirectorCourseController;
use App\Http\Controllers\Director\DashboardController as DirectorDashboardController;
use App\Http\Controllers\Director\EvaluationPlanOverviewController;
use App\Http\Controllers\Director\PlanificacionesController as DirectorPlanificacionesController;
use App\Http\Controllers\Director\ReportCardController as DirectorReportCardController;
use App\Http\Controllers\Director\StaffController as DirectorStaffController;
use App\Http\Controllers\Director\StudentController as DirectorStudentController;
use App\Http\Controllers\FamilyCodeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepresentanteController;
use App\Http\Controllers\SmartPlannerController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\Teacher\ActivitiesController;
use App\Http\Controllers\Teacher\AssessmentStrategyController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\CommunicationController;
use App\Http\Controllers\Teacher\CoursesController;
use App\Http\Controllers\Teacher\EvaluationController;
use App\Http\Controllers\Teacher\GradesController;
use App\Http\Controllers\Teacher\HubController;
use App\Http\Controllers\Teacher\IntelligenceController;
use App\Http\Controllers\Teacher\ManualPlanningController;
use App\Http\Controllers\Teacher\ReportCardController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Teacher\TareaController;
use App\Models\Activity;
use App\Models\UserSettings;
use App\Support\LessonTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- RUTAS PÚBLICAS ---
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::post('/solicitar-demo', [DemoRequestController::class, 'store'])
    ->name('demo.request');

Route::get('/accept-invitation/{token}', [InvitationController::class, 'show'])
    ->name('invitations.show');
Route::post('/accept-invitation', [InvitationController::class, 'accept'])
    ->name('invitations.accept');

Route::get('/e/{token}', [EvaluationController::class, 'take'])->name('evaluations.take');
Route::post('/e/{token}', [EvaluationController::class, 'submitTake'])->name('evaluations.take.submit');

Route::view('/privacidad', 'legal.privacidad')->name('legal.privacidad');
Route::view('/terminos', 'legal.terminos')->name('legal.terminos');

// Rutas de autenticación (Breeze)
require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'save'])->name('onboarding.save');
    Route::post('/onboarding/demo', [OnboardingController::class, 'joinAsDemo'])->name('onboarding.demo');
    Route::get('/onboarding/director-success', [OnboardingController::class, 'directorSuccess'])->name('onboarding.director_success');
    Route::post('/onboarding/validate-school-code', [OnboardingController::class, 'validateSchoolCode'])->name('onboarding.validate_school_code');
    Route::post('/api/validate-school-code', [OnboardingController::class, 'validateSchoolCode'])->name('api.validate-school-code');
    Route::post('/api/validate-family-code', [FamilyCodeController::class, 'validateFamilyCode'])->name('api.validate-family-code');
});

// B. RUTAS BLOQUEADAS HASTA COMPLETAR ONBOARDING
    Route::middleware(['onboarding.completed'])->group(function () {

        // Dashboard Principal
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Códigos sensibles (colegio / familia) — revelación con PIN 20s
        Route::post('/api/codes/reveal', [CodesRevealController::class, 'reveal'])->name('codes.reveal');
        Route::post('/api/codes/pin', [CodesRevealController::class, 'updatePin'])->name('codes.pin.update');

        // Director — Centro de Mando
        Route::prefix('director')
            ->name('director.')
            ->middleware(['role.director'])
            ->group(function () {
                Route::get('/dashboard', [DirectorDashboardController::class, 'index'])
                    ->name('dashboard');
                Route::get('/profesores', [DirectorStaffController::class, 'index'])
                    ->name('profesores');
                Route::post('/profesores/invite', [DirectorStaffController::class, 'invite'])
                    ->name('profesores.invite');
                Route::post('/profesores/invite-link', [DirectorStaffController::class, 'inviteLink'])
                    ->name('profesores.invite-link');
                Route::delete('/profesores/{teacher}', [DirectorStaffController::class, 'destroyTeacher'])
                    ->name('profesores.destroy');
                Route::post('/profesores/bulk-destroy', [DirectorStaffController::class, 'bulkDestroyTeachers'])
                    ->name('profesores.bulk-destroy');
                Route::delete('/profesores/invite/{invite}', [DirectorStaffController::class, 'destroyInvite'])
                    ->name('profesores.invite.destroy');
                Route::get('/courses', [DirectorCourseController::class, 'index'])
                    ->name('courses');
                Route::post('/courses', [DirectorCourseController::class, 'store'])
                    ->name('courses.store');
                Route::delete('/courses/{course}', [DirectorCourseController::class, 'destroy'])
                    ->name('courses.destroy');
                Route::post('/courses/bulk-destroy', [DirectorCourseController::class, 'bulkDestroy'])
                    ->name('courses.bulk-destroy');
                Route::post('/courses/{course}/assign', [DirectorCourseController::class, 'assign'])
                    ->name('courses.assign');
                Route::post('/courses/{course}/enroll-roster', [DirectorCourseController::class, 'enrollByRoster'])
                    ->name('courses.enroll_roster');

                // Planificaciones feed + auditoría
                Route::get('/planificaciones', [DirectorPlanificacionesController::class, 'index'])
                    ->name('planificaciones');
                Route::get('/planificaciones/{id}/sessions', [DirectorPlanificacionesController::class, 'sessions'])
                    ->name('planificaciones.sessions');
                Route::post('/planificaciones/{id}/approve', [DirectorPlanificacionesController::class, 'approve'])
                    ->name('planificaciones.approve');
                Route::post('/planificaciones/{id}/reject', [DirectorPlanificacionesController::class, 'reject'])
                    ->name('planificaciones.reject');

                // Alumnos y boletas
                Route::get('/students', [DirectorStudentController::class, 'index'])
                    ->name('students');
                Route::post('/students', [DirectorStudentController::class, 'store'])
                    ->name('students.store');
                Route::get('/students/search', [DirectorStudentController::class, 'search'])
                    ->name('students.search');
                Route::delete('/students/{student}', [DirectorStudentController::class, 'destroy'])
                    ->name('students.destroy');
                Route::post('/students/bulk-destroy', [DirectorStudentController::class, 'bulkDestroy'])
                    ->name('students.bulk-destroy');
                Route::get('/boletines', [DirectorReportCardController::class, 'index'])
                    ->name('boletines');
                Route::get('/report-card/{student}', [DirectorReportCardController::class, 'preview'])
                    ->name('report-card');
                Route::get('/report-card/{student}/pdf', [DirectorReportCardController::class, 'pdf'])
                    ->name('report-card.pdf');

                Route::get('/attendance', [AttendanceDashboardController::class, 'index'])
                    ->name('attendance');

                Route::get('/evaluation-plans', [EvaluationPlanOverviewController::class, 'index'])
                    ->name('evaluation_plans');
                Route::get('/api/evaluation-plans', [EvaluationPlanOverviewController::class, 'api'])
                    ->name('api.evaluation_plans');

                // Períodos académicos y boletas inteligentes
                Route::get('/periodos', [DirectorAcademicPeriodController::class, 'index'])
                    ->name('periodos');
                Route::get('/api/periods', [DirectorAcademicPeriodController::class, 'apiPeriods'])
                    ->name('api.periods');
                Route::post('/api/periods', [DirectorAcademicPeriodController::class, 'storePeriod'])
                    ->name('api.periods.store');
                Route::put('/api/periods/{period}', [DirectorAcademicPeriodController::class, 'updatePeriod'])
                    ->name('api.periods.update');
                Route::get('/api/periods/{period}/grades-summary', [DirectorAcademicPeriodController::class, 'gradesSummary'])
                    ->name('api.periods.grades');
                Route::post('/api/periods/{period}/generate', [DirectorAcademicPeriodController::class, 'generate'])
                    ->name('api.periods.generate');
                Route::post('/api/periods/{period}/publish', [DirectorAcademicPeriodController::class, 'publish'])
                    ->name('api.periods.publish');
                Route::get('/api/periods/{period}/cards', [DirectorAcademicPeriodController::class, 'listCards'])
                    ->name('api.periods.cards');
                Route::get('/api/periods/{period}/export-pdf', [DirectorAcademicPeriodController::class, 'pdfBulk'])
                    ->name('api.periods.pdf.bulk');
                Route::get('/api/report-cards/{card}', [DirectorAcademicPeriodController::class, 'getCard'])
                    ->name('api.report-cards.show');
                Route::put('/api/report-cards/{card}', [DirectorAcademicPeriodController::class, 'updateCard'])
                    ->name('api.report-cards.update');
                Route::get('/api/report-cards/{card}/pdf', [DirectorAcademicPeriodController::class, 'pdfCard'])
                    ->name('api.report-cards.pdf');

                // Feedback y co-edición del director
                Route::post('/activities/{id}/feedback', [ActivityFeedbackController::class, 'storeFeedback'])
                    ->name('activities.feedback');
                Route::put('/activities/{id}/update', [ActivityFeedbackController::class, 'update'])
                    ->name('activities.update');
                Route::get('/planificaciones/{id}/activities', [ActivityFeedbackController::class, 'planActivities'])
                    ->name('planificaciones.activities');
                Route::put('/planificaciones/{id}/sessions', [ActivityFeedbackController::class, 'updatePlanificacionSession'])
                    ->name('planificaciones.sessions.update');

                // Asistente de IA operativo para directores (rate limited: 30 req/min por usuario)
                Route::post('/ai/command', [DirectorAICommandController::class, 'handle'])
                    ->middleware('throttle:30,1')
                    ->name('ai.command');
            });

        // Representante — Panel de seguimiento
        Route::prefix('representante')
            ->name('representante.')
            ->middleware(['role.representante'])
            ->group(function () {
                Route::get('/dashboard', [RepresentanteController::class, 'index'])
                    ->name('dashboard');
                Route::post('/ausencias', [RepresentanteController::class, 'storeAbsence'])
                    ->name('ausencias.store');

                Route::get('/api/estudiantes', [RepresentanteController::class, 'students'])->name('api.estudiantes');
                Route::get('/api/{estudiante}/resumen', [RepresentanteController::class, 'resumen'])->name('api.resumen');
                Route::get('/api/{estudiante}/calendario', [RepresentanteController::class, 'calendario'])->name('api.calendario');
                Route::get('/api/{estudiante}/materias', [RepresentanteController::class, 'materias'])->name('api.materias');
                Route::get('/api/{estudiante}/materia/{materia}', [RepresentanteController::class, 'materia'])->name('api.materia');
                Route::get('/api/anuncios', [RepresentanteController::class, 'anuncios'])->name('api.anuncios');
                Route::post('/api/anuncios/{anuncio}/leer', [RepresentanteController::class, 'leerAnuncio'])->name('api.anuncios.leer');
                Route::get('/api/mensajes', [RepresentanteController::class, 'mensajes'])->name('api.mensajes');
                Route::get('/api/mensajes/{thread}', [RepresentanteController::class, 'thread'])->name('api.mensajes.show');
                Route::post('/api/mensajes/{thread}', [RepresentanteController::class, 'sendMessage'])->name('api.mensajes.send');
                Route::post('/api/mensajes', [RepresentanteController::class, 'startMessage'])->name('api.mensajes.start');
                Route::post('/api/ausencia', [RepresentanteController::class, 'storeAbsenceJson'])->name('api.ausencia');
                Route::get('/api/notificaciones', [RepresentanteController::class, 'notifications'])->name('api.notificaciones');
                Route::post('/api/notificaciones/leer', [RepresentanteController::class, 'markNotificationsRead'])->name('api.notificaciones.leer');
                Route::post('/api/perfil', [RepresentanteController::class, 'updateProfile'])->name('api.perfil');
                Route::get('/api/{estudiante}/boletas-oficiales', [RepresentanteController::class, 'boletasOficiales'])->name('api.boletas');
                Route::get('/boletin/{estudiante}', [RepresentanteController::class, 'boletin'])->name('boletin');
                Route::get('/api/{estudiante}/boletin', [RepresentanteController::class, 'boletinPreview'])->name('api.boletin');
                Route::get('/constancia/{estudiante}', [RepresentanteController::class, 'constancia'])->name('constancia');
            });

        // Generador de IA y Herramientas
        Route::post('/generate-ai', [AIController::class, 'generate'])->name('ai.generate');
        Route::post('/improve-section', [AIController::class, 'improveSection'])->name('ai.improve_section');
        Route::post('/plan-pro/nee', [AIController::class, 'planProNEE'])->name('ai.plan_pro.nee');
        Route::post('/plan-pro/calendario', [AIController::class, 'planProCalendario'])->name('ai.plan_pro.calendario');
        Route::post('/plan-pro/materiales', [AIController::class, 'planProMateriales'])->name('ai.plan_pro.materiales');

        // Gestión de Planificaciones
        Route::post('/planning/save', [AIController::class, 'save'])->name('planning.save');
        Route::delete('/planificaciones/{id}', [AIController::class, 'destroy'])->name('planning.destroy');
        Route::get('/historial', [AIController::class, 'historial'])->name('historial');
        Route::get('/planning/history', [PlanningController::class, 'index'])->name('planning.index');

        // ── RUTAS EXCLUSIVAS DE DOCENTES ───────────────────────────────────────
        Route::middleware(['role.teacher'])->group(function () {

            // Hub del docente
            Route::get('/teacher/hub', [HubController::class, 'index'])->name('teacher.hub');
            Route::get('/teacher/api/stats', [HubController::class, 'apiStats'])->name('teacher.api.stats');
            Route::get('/teacher/api/courses', [HubController::class, 'apiCourses'])->name('teacher.api.courses');
            Route::get('/teacher/api/courses/{course}', [HubController::class, 'apiCourse'])->name('teacher.api.course');
            Route::patch('/teacher/api/courses/{course}/grading-scale', [HubController::class, 'updateGradingScale'])->name('teacher.api.course.grading_scale');
            Route::get('/teacher/api/courses/{course}/students/{student}/grades', [HubController::class, 'apiCourseStudentGrades'])->name('teacher.api.course.student.grades');
            Route::get('/teacher/api/calendar', [HubController::class, 'apiCalendar'])->name('teacher.api.calendar');
            Route::get('/teacher/api/activities/{activity}', [HubController::class, 'apiActivity'])->name('teacher.api.activity');

            // Asistente de IA
            Route::post('/ai/command', [AICommandHandlerController::class, 'handle'])->name('ai.command');

            // Inteligencia AulaSync — importación de documentos y análisis
            Route::prefix('intelligence')->name('intelligence.')->group(function () {
                Route::get('/', [IntelligenceController::class, 'index'])->name('index');
                Route::get('/api/documents', [IntelligenceController::class, 'documents'])->name('documents');
                Route::post('/documents', [IntelligenceController::class, 'store'])->name('documents.store');
                Route::get('/documents/{document}', [IntelligenceController::class, 'show'])->name('documents.show');
                Route::post('/documents/{document}/apply', [IntelligenceController::class, 'apply'])->name('documents.apply');
                Route::post('/documents/{document}/forward', [IntelligenceController::class, 'forwardToDirector'])->name('documents.forward');
                Route::delete('/documents/{document}', [IntelligenceController::class, 'destroy'])->name('documents.destroy');
                Route::get('/api/dashboard', [IntelligenceController::class, 'dashboard'])->name('dashboard');
                Route::post('/query', [IntelligenceController::class, 'query'])->name('query');
                Route::post('/actions', [IntelligenceController::class, 'runAction'])->name('actions.run');
                Route::post('/actions/apply', [IntelligenceController::class, 'applyAction'])->name('actions.apply');
            });

            // Gestión Académica — Cursos
            Route::prefix('teacher/courses')->name('teacher.courses.')->group(function () {
                Route::get('/', [CoursesController::class, 'index'])->name('index');
                Route::post('/', [CoursesController::class, 'store'])->name('store');
                Route::delete('/{course}', [CoursesController::class, 'destroy'])->name('destroy');
                Route::post('/{course}/import-students', [CoursesController::class, 'importStudents'])->name('import_students');
                Route::delete('/{course}/students/{student}', [CoursesController::class, 'removeStudent'])->name('remove_student');
            });

            // Gestión Académica — Actividades
            Route::prefix('teacher/activities')->name('teacher.activities.')->group(function () {
                Route::get('/', [ActivitiesController::class, 'index'])->name('index');
                Route::get('/create', [ActivitiesController::class, 'index'])->name('create');
                Route::post('/', [ActivitiesController::class, 'store'])->name('store');
                Route::post('/ai-description', [ActivitiesController::class, 'generateDescription'])->name('ai_description');
                Route::post('/{activity}/nee/generate', [ActivitiesController::class, 'generateNee'])->name('nee_generate');
                Route::post('/{activity}/nee/save', [ActivitiesController::class, 'saveNee'])->name('nee_save');
                Route::post('/{activity}/ai-edit', [ActivitiesController::class, 'editWithAI'])->name('ai_edit');
                Route::patch('/{activity}/phases', [ActivitiesController::class, 'updatePhases'])->name('phases');
                Route::patch('/{activity}/notes', [ActivitiesController::class, 'updateNotes'])->name('notes');
                Route::delete('/{activity}', [ActivitiesController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('teacher/tareas')->name('teacher.tareas.')->group(function () {
                Route::post('/generate', [TareaController::class, 'generate'])->name('generate');
                Route::post('/store', [TareaController::class, 'store'])->name('store');
                Route::patch('/{tarea}/grade', [TareaController::class, 'updateGrade'])->name('grade');
            });

            // --- PLANIFICADOR MANUAL (CORREGIDO) ---
            Route::prefix('teacher/evaluations')->name('teacher.evaluations.')->group(function () {
                Route::get('/', [EvaluationController::class, 'index'])->name('index');
                Route::post('/generate', [EvaluationController::class, 'generate'])->name('generate');
                Route::post('/', [EvaluationController::class, 'store'])->name('store');
                Route::patch('/{evaluation}', [EvaluationController::class, 'update'])->name('update');
                Route::delete('/{evaluation}', [EvaluationController::class, 'destroy'])->name('destroy');
                Route::post('/{evaluation}/duplicate', [EvaluationController::class, 'duplicate'])->name('duplicate');
                Route::get('/{evaluation}/roster', [EvaluationController::class, 'roster'])->name('roster');
                Route::post('/{evaluation}/grades', [EvaluationController::class, 'saveGrades'])->name('grades');
                Route::get('/{evaluation}/print', [EvaluationController::class, 'print'])->name('print');
                Route::post('/{evaluation}/regenerate-question', [EvaluationController::class, 'regenerateQuestion'])->name('regenerate');
                Route::post('/regenerate-draft-question', [EvaluationController::class, 'regenerateDraftQuestion'])->name('regenerate_draft');
                Route::post('/attempts/{attempt}/grade-ai', [EvaluationController::class, 'gradeOpen'])->name('grade_ai');
            });

            Route::prefix('teacher/communication')->name('teacher.communication.')->group(function () {
                Route::get('/', [CommunicationController::class, 'index'])->name('index');
                Route::get('/threads', [CommunicationController::class, 'threads'])->name('threads');
                Route::post('/announcements/generate', [CommunicationController::class, 'generateAnnouncement'])->name('announcements.generate');
                Route::post('/announcements', [CommunicationController::class, 'storeAnnouncement'])->name('announcements.store');
                Route::post('/announcements/{announcement}/demo-read', [CommunicationController::class, 'markReadDemo'])->name('announcements.demo_read');
                Route::post('/threads/{thread}/messages', [CommunicationController::class, 'sendMessage'])->name('messages.send');
                Route::post('/threads/{thread}/simulate-incoming', [CommunicationController::class, 'simulateIncoming'])->name('messages.simulate_incoming');
                Route::post('/threads/{thread}/quick-replies', [CommunicationController::class, 'suggestQuickReply'])->name('messages.quick_replies');
            });

            Route::prefix('teacher/attendance')->name('teacher.attendance.')->group(function () {
                Route::get('/', [AttendanceController::class, 'index'])->name('index');
                Route::get('/roster', [AttendanceController::class, 'roster'])->name('roster');
                Route::post('/', [AttendanceController::class, 'save'])->name('save');
                Route::get('/students/{student}', [AttendanceController::class, 'history'])->name('history');
            });

            Route::prefix('teacher/assessment')->name('teacher.assessment.')->group(function () {
                Route::get('/', [AssessmentStrategyController::class, 'index'])->name('index');
                Route::post('/plans/generate', [AssessmentStrategyController::class, 'generatePlan'])->name('plans.generate');
                Route::post('/plans', [AssessmentStrategyController::class, 'storePlan'])->name('plans.store');
                Route::post('/plans/analyze-overload', [AssessmentStrategyController::class, 'analyzeOverload'])->name('plans.overload');
                Route::post('/plans/{plan}/publish-calendar', [AssessmentStrategyController::class, 'publishPlanToCalendar'])->name('plans.publish_calendar');
                Route::delete('/plans/{plan}', [AssessmentStrategyController::class, 'destroyPlan'])->name('plans.destroy');
                Route::post('/attach-evaluation', [AssessmentStrategyController::class, 'attachEvaluation'])->name('attach_evaluation');
                Route::post('/rubrics/generate', [AssessmentStrategyController::class, 'generateRubric'])->name('rubrics.generate');
                Route::post('/rubrics', [AssessmentStrategyController::class, 'storeRubric'])->name('rubrics.store');
                Route::delete('/rubrics/{rubric}', [AssessmentStrategyController::class, 'destroyRubric'])->name('rubrics.destroy');
            });

            Route::prefix('teacher/planner')->name('teacher.planner.')->group(function () {
                // El {id?} permite que el Hub entre a /manual sin error
                Route::get('/manual/{id?}', [ManualPlanningController::class, 'show'])->name('manual');
                Route::post('/manual', [ManualPlanningController::class, 'store'])->name('store');

                // Ruta show explícita para compatibilidad con redirecciones
                Route::get('/show/{id}', [ManualPlanningController::class, 'show'])->name('show');

                Route::get('/manual/pdf/{manualPlanning?}', [ManualPlanningController::class, 'pdf'])->name('pdf');
            });

            // API: Guardar preferencia de plantilla de clase
            Route::post('/teacher/api/lesson-template', function (Request $request) {
                $data = $request->validate([
                    'lesson_template' => 'required|in:clasica,directa,constructivista,proyecto',
                    'activity_id' => 'nullable|integer',
                    'activity_ids' => 'nullable|array',
                    'activity_ids.*' => 'integer',
                    'planificacion_id' => 'nullable|integer',
                ]);
                $settings = UserSettings::firstOrCreate(
                    ['user_id' => Auth::id()],
                    ['lesson_template' => \App\Support\LessonTemplate::CLASSIC]
                );
                $settings->update(['lesson_template' => $data['lesson_template']]);

                $ids = collect($data['activity_ids'] ?? [])
                    ->when(! empty($data['activity_id']), fn ($c) => $c->push((int) $data['activity_id']))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                $query = Activity::where('teacher_id', Auth::id());
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids->all());
                } elseif (! empty($data['planificacion_id'])) {
                    $query->where('plan_block_id', (int) $data['planificacion_id']);
                } else {
                    $query = null;
                }

                $rewritten = 0;
                if ($query) {
                    foreach ($query->get() as $activity) {
                        $next = LessonTemplate::rewrite((string) $activity->description, $data['lesson_template']);
                        if ($next !== '' && $next !== (string) $activity->description) {
                            $activity->update(['description' => $next]);
                            $rewritten++;
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'lesson_template' => $data['lesson_template'],
                    'rewritten' => $rewritten,
                ]);
            })->name('teacher.api.lesson-template');

            // API: Alumnos / matrícula
            Route::get('/teacher/api/school-students', [TeacherStudentController::class, 'search'])->name('teacher.api.school-students');
            Route::post('/teacher/api/students', [TeacherStudentController::class, 'store'])->name('api.students.create');
            Route::post('/teacher/api/courses/{course}/enroll', [TeacherStudentController::class, 'enrollExisting'])->name('teacher.api.courses.enroll');

            // Gestión Académica — Notas
            Route::prefix('teacher/grades')->name('teacher.grades.')->group(function () {
                Route::get('/', [GradesController::class, 'index'])->name('index');
                Route::get('/{activity}', [GradesController::class, 'create'])->name('show');
                Route::get('/activity/{activity}/create', [GradesController::class, 'create'])->name('create');
                Route::get('/activity/{activity}/panel', [GradesController::class, 'panel'])->name('panel');
                Route::post('/activity/{activity}/quick-store', [GradesController::class, 'quickStore'])->name('quick_store');
                Route::post('/activity/{activity}/publish', [GradesController::class, 'publish'])->name('publish');
                Route::post('/activity/{activity}/store', [GradesController::class, 'store'])->name('store');
                Route::post('/activity/{activity}/ai-parse', [GradesController::class, 'parseWithAI'])->name('ai_parse');
            });

            // Boleta de calificaciones del docente
            Route::prefix('teacher/report-card')->name('teacher.report-card.')->group(function () {
                Route::get('/{student}', [ReportCardController::class, 'preview'])->name('preview');
                Route::get('/{student}/pdf', [ReportCardController::class, 'pdf'])->name('pdf');
            });

        }); // end role.teacher

        // Perfil de Usuario
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Notificaciones (docentes y directores)
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

        Route::get('/ai/chat-history', [AiChatHistoryController::class, 'show'])->name('ai.chat.history');
        Route::post('/ai/chat-history', [AiChatHistoryController::class, 'store'])->name('ai.chat.history.store');
        Route::delete('/ai/chat-history', [AiChatHistoryController::class, 'destroy'])->name('ai.chat.history.destroy');
    });

    // Ruta para procesar el texto mágico
    Route::post('/smart-planner/parse', [SmartPlannerController::class, 'parseText'])->name('smart.parse');

    // Ruta de emergencia para cerrar sesión
    Route::get('/logout-manual', function () {
        app(\App\Services\AiChatHistoryService::class)->forget(auth()->id());
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
});

    Route::middleware(['auth', 'role.super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index'])->name('index');
        Route::get('/usage', [SuperAdminController::class, 'usage'])->name('usage');
        Route::get('/intelligence', [SuperAdminController::class, 'intelligence'])->name('intelligence');
        Route::get('/schools', [SuperAdminController::class, 'schools'])->name('schools');
        Route::post('/schools', [SuperAdminController::class, 'storeSchool'])
            ->middleware('can:manage-system')
            ->name('schools.store');
        Route::get('/colegios/{colegio}', [SuperAdminController::class, 'school'])->name('colegios.show');
        Route::post('/colegios/{colegio}/invite-director', [SuperAdminController::class, 'inviteDirector'])
            ->middleware('can:manage-system')
            ->name('colegios.invite-director');
        Route::get('/health', [SuperAdminController::class, 'health'])->name('health');
        Route::get('/insights', [SuperAdminController::class, 'insights'])->name('insights');
        Route::get('/users', [SuperAdminController::class, 'users'])->name('users');
        Route::patch('/users/{user}', [SuperAdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [SuperAdminController::class, 'destroyUser'])
            ->middleware('can:manage-system')
            ->name('users.destroy');
        Route::post('/colegios/{colegio}/enter', [SuperAdminController::class, 'enterSchool'])->name('colegios.enter');
        Route::delete('/colegios/{colegio}/cursos/{course}', [SuperAdminController::class, 'destroyCourse'])
            ->middleware('can:manage-system')
            ->name('colegios.cursos.destroy');
        Route::delete('/colegios/{colegio}/profesores/{teacher}', [SuperAdminController::class, 'destroyTeacher'])
            ->middleware('can:manage-system')
            ->name('colegios.profesores.destroy');
        Route::delete('/colegios/{colegio}/alumnos/{student}', [SuperAdminController::class, 'destroyStudent'])
            ->middleware('can:manage-system')
            ->name('colegios.alumnos.destroy');
    });
