<?php

use App\Models\User;
use App\Services\TeacherInviteClaimService;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(TeacherInviteClaimService::class);

$teachers = User::where('role', 'profesor')->whereNotNull('colegio_id')->get();
foreach ($teachers as $teacher) {
    $n = $service->claimForUser($teacher);
    $courses = \App\Models\Course::where('teacher_id', $teacher->id)->count();
    echo "user={$teacher->id} {$teacher->email} claimed={$n} courses={$courses}\n";
}
