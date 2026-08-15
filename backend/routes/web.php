<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CodesRevealController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\Teacher\GradesController;
use App\Http\Controllers\Teacher\CoursesController;
use App\Http\Controllers\Teacher\ActivitiesController;
use App\Http\Controllers\Teacher\TareaController;
use App\Http\Controllers\Teacher\ManualPlanningController;
use App\Http\Controllers\Director\DashboardController as DirectorDashboardController;
use App\Http\Controllers\Director\PlanificacionesController as DirectorPlanificacionesController;
use App\Http\Controllers\Director\ReportCardController as DirectorReportCardController;
use App\Http\Controllers\Director\StudentController as DirectorStudentController;
use App\Http\Controllers\Director\ActivityFeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AICommandHandlerController;
use App\Http\Controllers\Teacher\HubController;
use App\Http\Controllers\Teacher\EvaluationController;
use App\Http\Controllers\Teacher\CommunicationController;
use App\Http\Controllers\Teacher\AssessmentStrategyController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\StudentController as TeacherStudentController;
use App\Http\Controllers\Director\AttendanceDashboardController;
use App\Http\Controllers\RepresentanteController;
use App\Http\Controllers\SmartPlannerController;
use Illuminate\Http\Request;

// --- RUTAS PÚBLICAS ---
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::post('/solicitar-demo', [App\Http\Controllers\DemoRequestController::class, 'store'])
    ->name('demo.request');

Route::get('/e/{token}', [EvaluationController::class, 'take'])->name('evaluations.take');
Route::post('/e/{token}', [EvaluationController::class, 'submitTake'])->name('evaluations.take.submit');

Route::view('/privacidad', 'legal.privacidad')->name('legal.privacidad');
Route::view('/terminos', 'legal.terminos')->name('legal.terminos');

// Rutas de autenticación (Breeze)
require __DIR__.'/auth.php';

// --- RUTAS PROTEGIDAS (Solo usuarios logueados) ---
Route::middleware(['auth'])->group(function () {
    
    // A. EXCEPCIÓN: Rutas de Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        // RUTA TEMPORAL: Para ver el diseño sin loguearse
    Route::get('/debug-onboarding', function () {
        $preselectedRole = '';
        return view('onboarding.wizard', compact('preselectedRole'));
    })->name('debug.onboarding');
    Route::post('/api/validate-school-code', [OnboardingController::class, 'validateSchoolCode'])
        ->name('api.validate-school-code');
    Route::post('/api/validate-family-code', [App\Http\Controllers\FamilyCodeController::class, 'validateFamilyCode'])
        ->name('api.validate-family-code');
    Route::post('/onboarding/save', [OnboardingController::class, 'save'])->name('onboarding.save');
    Route::get('/onboarding/director-success', [OnboardingController::class, 'directorSuccess'])
        ->name('onboarding.director_success');
    Route::post('/onboarding/demo', [OnboardingController::class, 'joinAsDemo'])
        ->name('onboarding.demo');
    
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
                Route::get('/profesores', [DirectorDashboardController::class, 'profesores'])
                    ->name('profesores');

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
                Route::get('/report-card/{student}', [DirectorReportCardController::class, 'preview'])
                    ->name('report-card');
                Route::get('/report-card/{student}/pdf', [DirectorReportCardController::class, 'pdf'])
                    ->name('report-card.pdf');

                Route::get('/attendance', [AttendanceDashboardController::class, 'index'])
                    ->name('attendance');

                // Feedback y co-edición del director
                Route::post('/activities/{id}/feedback', [ActivityFeedbackController::class, 'storeFeedback'])
                    ->name('activities.feedback');
                Route::put('/activities/{id}/update', [ActivityFeedbackController::class, 'update'])
                    ->name('activities.update');
                Route::get('/planificaciones/{id}/activities', [ActivityFeedbackController::class, 'planActivities'])
                    ->name('planificaciones.activities');
                Route::put('/planificaciones/{id}/sessions', [ActivityFeedbackController::class, 'updatePlanificacionSession'])
                    ->name('planificaciones.sessions.update');
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
            Route::get('/teacher/api/courses/{course}/students/{student}/grades', [HubController::class, 'apiCourseStudentGrades'])->name('teacher.api.course.student.grades');
            Route::get('/teacher/api/calendar', [HubController::class, 'apiCalendar'])->name('teacher.api.calendar');
            Route::get('/teacher/api/activities/{activity}', [HubController::class, 'apiActivity'])->name('teacher.api.activity');

            // Asistente de IA
            Route::post('/ai/command', [AICommandHandlerController::class, 'handle'])->name('ai.command');

            // Gestión Académica — Cursos
            Route::prefix('teacher/courses')->name('teacher.courses.')->group(function () {
                Route::get('/', [CoursesController::class, 'index'])->name('index');
                Route::post('/', [CoursesController::class, 'store'])->name('store');
                Route::delete('/{course}', [CoursesController::class, 'destroy'])->name('destroy');
                Route::post('/{course}/import-students', [CoursesController::class, 'importStudents'])->name('import_students');
                Route::delete('/{course}/students/{student}', [CoursesController::class, 'removeStudent']) ->name('remove_student');
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
                Route::get('/{evaluation}/print', [EvaluationController::class, 'print'])->name('print');
                Route::post('/{evaluation}/regenerate-question', [EvaluationController::class, 'regenerateQuestion'])->name('regenerate');
                Route::post('/regenerate-draft-question', [EvaluationController::class, 'regenerateDraftQuestion'])->name('regenerate_draft');
                Route::post('/attempts/{attempt}/grade-ai', [EvaluationController::class, 'gradeOpen'])->name('grade_ai');
            });

            Route::prefix('teacher/communication')->name('teacher.communication.')->group(function () {
                Route::get('/', [CommunicationController::class, 'index'])->name('index');
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
            Route::post('/teacher/api/lesson-template', function (Illuminate\Http\Request $request) {
                $data = $request->validate(['lesson_template' => 'required|in:clasica,directa,constructivista']);
                $settings = \App\Models\UserSettings::firstOrCreate(
                    ['user_id' => \Auth::id()],
                    []
                );
                $settings->update(['lesson_template' => $data['lesson_template']]);
                return response()->json(['success' => true, 'lesson_template' => $data['lesson_template']]);
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
                Route::get('/{student}', [\App\Http\Controllers\Teacher\ReportCardController::class, 'preview'])->name('preview');
                Route::get('/{student}/pdf', [\App\Http\Controllers\Teacher\ReportCardController::class, 'pdf'])->name('pdf');
            });

        }); // end role.teacher

        // Perfil de Usuario
        Route::get('/profile', function () {
            return view('profile');
        })->name('profile');

        // Notificaciones (docentes y directores)
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    });

    // Ruta para procesar el texto mágico
    Route::post('/smart-planner/parse', [SmartPlannerController::class, 'parseText'])->name('smart.parse');

    // Ruta de emergencia para cerrar sesión
    Route::get('/logout-manual', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    });
});