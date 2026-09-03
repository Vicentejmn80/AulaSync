<?php

require __DIR__.'/../backend/vendor/autoload.php';

$app = require __DIR__.'/../backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Activity;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$password = Hash::make('password');

function upsertUser(array $attrs): User
{
    global $password;
    return User::updateOrCreate(
        ['email' => $attrs['email']],
        array_merge($attrs, [
            'password' => $password,
            'onboarding_completed' => true,
            'email_verified_at' => now(),
        ])
    );
}

$colegioA = Colegio::updateOrCreate(
    ['invite_code' => 'E2E-SCH-A'],
    ['name' => 'Colegio E2E Alfa', 'codes_pin' => Colegio::hashPinFromInvite('E2E-SCH-A')]
);

$colegioB = Colegio::updateOrCreate(
    ['invite_code' => 'E2E-SCH-B'],
    ['name' => 'Colegio E2E Beta', 'codes_pin' => Colegio::hashPinFromInvite('E2E-SCH-B')]
);

$director = upsertUser([
    'name' => 'Director E2E',
    'email' => 'director.e2e@aulasync.test',
    'role' => 'director',
    'colegio_id' => $colegioA->id,
]);
$colegioA->director_user_id = $director->id;
$colegioA->save();

$teacherA = upsertUser([
    'name' => 'Profesor E2E Alfa',
    'email' => 'docente.e2e@aulasync.test',
    'role' => 'profesor',
    'colegio_id' => $colegioA->id,
]);

$parentA = upsertUser([
    'name' => 'Representante E2E Alfa',
    'email' => 'familia.e2e@aulasync.test',
    'role' => 'representante',
    'colegio_id' => $colegioA->id,
]);

$teacherB = upsertUser([
    'name' => 'Profesor E2E Beta',
    'email' => 'docente.b.e2e@aulasync.test',
    'role' => 'profesor',
    'colegio_id' => $colegioB->id,
]);

$parentB = upsertUser([
    'name' => 'Representante E2E Beta',
    'email' => 'familia.b.e2e@aulasync.test',
    'role' => 'representante',
    'colegio_id' => $colegioB->id,
]);

$studentA = Student::updateOrCreate(
    ['colegio_id' => $colegioA->id, 'name' => 'Alumno E2E Alfa'],
    ['teacher_id' => $teacherA->id, 'grade' => '3ro', 'section' => 'A']
);
$parentA->representedStudents()->syncWithoutDetaching([$studentA->id => ['relationship' => 'padre']]);

$studentB = Student::updateOrCreate(
    ['colegio_id' => $colegioB->id, 'name' => 'Alumno E2E Beta'],
    ['teacher_id' => $teacherB->id, 'grade' => '4to', 'section' => 'B']
);
$parentB->representedStudents()->syncWithoutDetaching([$studentB->id => ['relationship' => 'madre']]);

$courseA = Course::updateOrCreate(
    ['invite_code' => 'E2E-MAT-A'],
    [
        'colegio_id' => $colegioA->id,
        'teacher_id' => $teacherA->id,
        'subject_name' => 'Matemática',
        'grade' => '3ro',
        'section' => 'A',
        'school_year' => '2026-2027',
    ]
);
$studentA->courses()->syncWithoutDetaching([$courseA->id => [
    'nota_actual' => 16,
    'promedio_acumulado' => 15.5,
]]);

$courseB = Course::updateOrCreate(
    ['invite_code' => 'E2E-LEN-B'],
    [
        'colegio_id' => $colegioB->id,
        'teacher_id' => $teacherB->id,
        'subject_name' => 'Lenguaje',
        'grade' => '4to',
        'section' => 'B',
        'school_year' => '2026-2027',
    ]
);
$studentB->courses()->syncWithoutDetaching([$courseB->id]);

Activity::updateOrCreate(
    ['teacher_id' => $teacherA->id, 'title' => 'Guía de fracciones'],
    [
        'course_id' => $courseA->id,
        'colegio_id' => $colegioA->id,
        'description' => 'Resolver las páginas 12 a 14.',
        'due_date' => now()->toDateString(),
        'type' => Activity::TYPE_TAREA,
        'is_homework' => true,
        'max_score' => 20,
        'weight_percentage' => 20,
    ]
);

Activity::updateOrCreate(
    ['teacher_id' => $teacherB->id, 'title' => 'Ensayo Beta'],
    [
        'course_id' => $courseB->id,
        'colegio_id' => $colegioB->id,
        'description' => 'Tarea exclusiva del colegio Beta.',
        'due_date' => now()->toDateString(),
        'type' => Activity::TYPE_TAREA,
        'is_homework' => true,
        'max_score' => 20,
        'weight_percentage' => 10,
    ]
);

$out = [
    'director' => 'director.e2e@aulasync.test',
    'teacher' => 'docente.e2e@aulasync.test',
    'parent' => 'familia.e2e@aulasync.test',
    'teacherB' => 'docente.b.e2e@aulasync.test',
    'parentB' => 'familia.b.e2e@aulasync.test',
    'password' => 'password',
    'seededTask' => 'Guía de fracciones',
    'foreignTask' => 'Ensayo Beta',
    'childA' => 'Alumno E2E Alfa',
    'childB' => 'Alumno E2E Beta',
    'courseAId' => $courseA->id,
];
file_put_contents(__DIR__.'/fixtures.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
fwrite(STDOUT, "e2e seed ok courseA={$courseA->id}\n");
