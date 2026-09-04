<?php

namespace Tests\Feature\Qa;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Colegio;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Support\Qa\QaSchool;

class QaSchoolSeederTest extends QaTestCase
{
    public function test_qa_school_has_expected_counts(): void
    {
        $school = Colegio::query()->where('invite_code', QaSchool::SCHOOL_CODE)->firstOrFail();
        $this->assertSame(QaSchool::SCHOOL_NAME, $school->name);

        $this->assertSame(1, User::query()->where('colegio_id', $school->id)->where('role', 'director')->count());
        $this->assertSame(QaSchool::TEACHER_COUNT, User::query()->where('colegio_id', $school->id)->where('role', 'profesor')->count());
        $this->assertSame(QaSchool::PARENT_COUNT, User::query()->where('colegio_id', $school->id)->where('role', 'representante')->count());
        $this->assertSame(QaSchool::STUDENT_COUNT, Student::query()->where('colegio_id', $school->id)->count());
        $this->assertGreaterThanOrEqual(10, Course::query()->where('colegio_id', $school->id)->count());
        $this->assertGreaterThan(0, Activity::query()->where('colegio_id', $school->id)->count());
        $this->assertGreaterThan(0, Evaluation::query()->where('colegio_id', $school->id)->count());
        $this->assertGreaterThan(0, Attendance::query()->where('colegio_id', $school->id)->count());
        $this->assertGreaterThan(0, Grade::query()->where('colegio_id', $school->id)->count());

        $this->assertNotNull(
            Colegio::query()->where('invite_code', QaSchool::OTHER_SCHOOL_CODE)->first()
        );
    }

    public function test_reset_is_idempotent(): void
    {
        app(\App\Support\Qa\QaSchoolEnvironment::class)->reset();
        $this->assertSame(
            QaSchool::STUDENT_COUNT,
            Student::query()->where('name', 'like', 'Alumno QA %')->where('name', 'not like', '%Other%')->count()
        );
        $this->assertSame(1, Colegio::query()->where('invite_code', QaSchool::SCHOOL_CODE)->count());
    }
}
