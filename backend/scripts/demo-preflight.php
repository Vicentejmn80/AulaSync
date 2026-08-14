<?php

/**
 * Preflight de demo Casa212.
 * Ejecutar desde backend/:
 *   php scripts/demo-preflight.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$fail = 0;
$warn = 0;

function ok(string $msg): void { echo "  [OK]   {$msg}\n"; }
function fail(string $msg): void { echo "  [FAIL] {$msg}\n"; }
function warn(string $msg): void { echo "  [WARN] {$msg}\n"; }

echo "=== Aulasync demo preflight ===\n\n";

echo "1) BOM UTF-8 en PHP (causa del error de namespace)\n";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base . '/app'));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $bytes = file_get_contents($file->getPathname(), false, null, 0, 3);
    if ($bytes === "\xEF\xBB\xBF") {
        fail('BOM en ' . str_replace($base . DIRECTORY_SEPARATOR, '', $file->getPathname()));
        $fail++;
    }
}
if ($fail === 0) {
    ok('Ningún archivo en app/ tiene BOM');
}

echo "\n2) Variables de entorno críticas\n";
$key = (string) env('OPENAI_API_KEY');
if ($key === '' || str_starts_with($key, 'your_')) {
    fail('OPENAI_API_KEY no configurada — el chatbot no podrá responder');
    $fail++;
} else {
    ok('OPENAI_API_KEY presente (' . strlen($key) . ' chars)');
}

$appKey = (string) env('APP_KEY');
if ($appKey === '') {
    fail('APP_KEY vacía — sesiones/login fallarán');
    $fail++;
} else {
    ok('APP_KEY presente');
}

$appUrl = (string) env('APP_URL');
ok('APP_URL=' . ($appUrl !== '' ? $appUrl : '(vacío)'));
if (env('APP_DEBUG') === true || env('APP_DEBUG') === 'true') {
    warn('APP_DEBUG=true — en demo con jueces conviene false para no mostrar stack traces');
    $warn++;
}

if (! filter_var(env('TELEGRAM_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
    warn('TELEGRAM_ENABLED=false — las notificaciones in-app sí funcionan; Telegram no');
    $warn++;
} else {
    ok('Telegram habilitado');
}

echo "\n3) Base de datos\n";
try {
    Illuminate\Support\Facades\DB::connection()->getPdo();
    ok('Conexión PostgreSQL/Supabase OK');
    $users = Illuminate\Support\Facades\DB::table('users')->count();
    $teachers = Illuminate\Support\Facades\DB::table('users')->where('role', 'profesor')->count();
    $directors = Illuminate\Support\Facades\DB::table('users')->where('role', 'director')->count();
    $courses = Illuminate\Support\Facades\DB::table('courses')->count();
    ok("Usuarios={$users} | docentes={$teachers} | directores={$directors} | cursos={$courses}");
    if ($teachers < 1) {
        fail('No hay usuario con role=profesor para la demo del chatbot');
        $fail++;
    }
    if ($directors < 1) {
        warn('No hay director — las notificaciones in-app no tendrán destinatario');
        $warn++;
    }
    if ($courses < 1) {
        warn('No hay cursos — pide al chatbot crear uno o crea uno en el hub antes de la demo');
        $warn++;
    }
} catch (Throwable $e) {
    fail('DB: ' . $e->getMessage());
    $fail++;
}

echo "\n4) Rutas críticas\n";
$routes = [
    'login' => 'GET /login',
    'ai.command' => 'POST /ai/command',
    'teacher.hub' => 'GET /teacher/hub',
    'teacher.api.calendar' => 'GET /teacher/api/calendar',
    'director.dashboard' => 'GET /director/dashboard',
    'director.planificaciones' => 'GET /director/planificaciones',
];
foreach ($routes as $name => $label) {
    try {
        $url = route($name, absolute: false);
        ok("{$label} → {$url}");
    } catch (Throwable $e) {
        fail("Ruta {$name} no registrada");
        $fail++;
    }
}

echo "\n5) Autoload de clases del chatbot\n";
foreach ([
    App\Http\Controllers\AICommandHandlerController::class,
    App\Http\Controllers\AIController::class,
    App\Http\Controllers\Director\DashboardController::class,
    App\Services\DirectorAlertService::class,
] as $class) {
    try {
        class_exists($class, true);
        ok($class);
    } catch (Throwable $e) {
        fail($class . ' — ' . $e->getMessage());
        $fail++;
    }
}

echo "\n=== Resultado: {$fail} fallos, {$warn} avisos ===\n";
exit($fail > 0 ? 1 : 0);
