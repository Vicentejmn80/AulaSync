<?php

use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\AICommandHandlerController;
use App\Http\Controllers\Teacher\HubController;
use App\Http\Controllers\SmartPlannerController;
use Illuminate\Http\Request;

// --- RUTAS PÚBLICAS ---
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Rutas de autenticación (Breeze)
require __DIR__.'/auth.php';

// --- RUTAS PROTEGIDAS (Solo usuarios logueados) ---
Route::middleware(['auth'])->group(function () {
    
    // A. EXCEPCIÓN: Rutas de Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
        // RUTA TEMPORAL: Para ver el diseño sin loguearse
    Route::get('/debug-onboarding', function () {
        return view('onboarding.wizard');
    })->name('debug.onboarding');
    Route::post('/onboarding/save', [OnboardingController::class, 'save'])->name('onboarding.save');
    
    // B. RUTAS BLOQUEADAS HASTA COMPLETAR ONBOARDING
    Route::middleware(['onboarding.completed'])->group(function () {
        
        // Dashboard Principal
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Director — Centro de Mando
        Route::prefix('director')
            ->name('director.')
            ->middleware(['role.director'])
            ->group(function () {
                Route::get('/dashboard', [DirectorDashboardController::class, 'index'])
                    ->name('dashboard');
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
            Route::get('/teacher/api/calendar', [HubController::class, 'apiCalendar'])->name('teacher.api.calendar');

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
                Route::delete('/{activity}', [ActivitiesController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('teacher/tareas')->name('teacher.tareas.')->group(function () {
                Route::post('/generate', [TareaController::class, 'generate'])->name('generate');
                Route::post('/store', [TareaController::class, 'store'])->name('store');
                Route::patch('/{tarea}/grade', [TareaController::class, 'updateGrade'])->name('grade');
            });

            // --- PLANIFICADOR MANUAL (CORREGIDO) ---
            Route::prefix('teacher/planner')->name('teacher.planner.')->group(function () {
                // El {id?} permite que el Hub entre a /manual sin error
                Route::get('/manual/{id?}', [ManualPlanningController::class, 'show'])->name('manual'); 
                Route::post('/manual', [ManualPlanningController::class, 'store'])->name('store');
                
                // Ruta show explícita para compatibilidad con redirecciones
                Route::get('/show/{id}', [ManualPlanningController::class, 'show'])->name('show');
                
                Route::get('/manual/pdf/{manualPlanning?}', [ManualPlanningController::class, 'pdf'])->name('pdf');
            });

            // API: Crear estudiante
            Route::post('/teacher/api/students', function (Illuminate\Http\Request $request) {
                $name = $request->validate(['name' => 'required|string|max:255'])['name'];
                $student = \App\Models\Student::create([
                    'teacher_id' => \Auth::id(),
                    'name' => $name,
                    'grade' => \Auth::user()->grade ?? '1',
                    'section' => \Auth::user()->section ?? 'A',
                ]);
                return response()->json(['success' => true, 'student' => $student]);
            })->name('api.students.create');

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

        }); // end role.teacher

        // Perfil de Usuario
        Route::get('/profile', function () {
            return view('profile');
        })->name('profile');
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