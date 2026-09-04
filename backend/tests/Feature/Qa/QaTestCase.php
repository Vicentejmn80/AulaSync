<?php

namespace Tests\Feature\Qa;

use App\Models\User;
use App\Support\Qa\QaSchool;
use App\Support\Qa\QaSchoolEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class QaTestCase extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    protected array $manifest = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = app(QaSchoolEnvironment::class)->reset();
    }

    protected function director(): User
    {
        return User::query()->where('email', QaSchool::directorEmail())->firstOrFail();
    }

    protected function teacher(int $index = 1): User
    {
        return User::query()->where('email', QaSchool::teacherEmail($index))->firstOrFail();
    }

    protected function parent(int $index = 1): User
    {
        return User::query()->where('email', QaSchool::parentEmail($index))->firstOrFail();
    }

    protected function otherParent(): User
    {
        return User::query()->where('email', QaSchool::otherParentEmail())->firstOrFail();
    }

    protected function otherTeacher(): User
    {
        return User::query()->where('email', QaSchool::otherTeacherEmail())->firstOrFail();
    }

    protected function loginAs(User $user)
    {
        return $this->actingAs($user);
    }

    protected function httpLogin(string $email)
    {
        return $this->post('/login', [
            'email' => $email,
            'password' => QaSchool::PASSWORD,
        ]);
    }
}
