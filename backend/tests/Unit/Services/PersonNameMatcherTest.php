<?php

namespace Tests\Unit\Services;

use App\Models\Colegio;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\PersonNameMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonNameMatcherTest extends TestCase
{
    use RefreshDatabase;

    private PersonNameMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(PersonNameMatcher::class);
    }

    public function test_finds_dirty_name_by_prefix_when_unique(): void
    {
        $colegio = $this->createColegio();
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $colegio->director_user_id,
            'name' => 'Mariano Tambien Que Te Dije',
            'grade' => '3ro',
            'family_code' => 'NV-MAR-01',
        ]);

        $match = $this->matcher->resolveStudent($colegio->id, 'Mariano');

        $this->assertTrue($match->isUnique());
        $this->assertSame($student->id, $match->model->id);
    }

    public function test_two_marianos_are_ambiguous(): void
    {
        $colegio = $this->createColegio();
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $colegio->director_user_id,
            'name' => 'Mariano Pérez',
            'grade' => '3ro',
            'family_code' => 'NV-MAR-02',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $colegio->director_user_id,
            'name' => 'Mariano Gómez',
            'grade' => '4to',
            'family_code' => 'NV-MAR-03',
        ]);

        $match = $this->matcher->resolveStudent($colegio->id, 'Mariano');

        $this->assertTrue($match->isAmbiguous());
        $this->assertCount(2, $match->candidates);
        $this->assertStringContainsString('varias coincidencias', $match->message);
    }

    public function test_resolution_is_strictly_scoped_to_colegio(): void
    {
        $colegioA = $this->createColegio('Colegio A', 'COC-A');
        $colegioB = $this->createColegio('Colegio B', 'COC-B');

        $studentA = Student::create([
            'colegio_id' => $colegioA->id,
            'teacher_id' => $colegioA->director_user_id,
            'name' => 'Mariano Único',
            'grade' => '3ro',
            'family_code' => 'NV-A',
        ]);
        Student::create([
            'colegio_id' => $colegioB->id,
            'teacher_id' => $colegioB->director_user_id,
            'name' => 'Mariano Único',
            'grade' => '3ro',
            'family_code' => 'NV-B',
        ]);

        $matchA = $this->matcher->resolveStudent($colegioA->id, 'Mariano');
        $matchB = $this->matcher->resolveStudent($colegioB->id, 'Mariano');

        $this->assertTrue($matchA->isUnique());
        $this->assertSame($studentA->id, $matchA->model->id);
        $this->assertTrue($matchB->isUnique());
        $this->assertNotSame($studentA->id, $matchB->model->id);
    }

    public function test_wildcards_are_treated_literally_and_stripped(): void
    {
        $colegio = $this->createColegio();
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $colegio->director_user_id,
            'name' => 'Mariano Pérez',
            'grade' => '3ro',
            'family_code' => 'NV-MAR-04',
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $colegio->director_user_id,
            'name' => 'Mario Núñez',
            'grade' => '3ro',
            'family_code' => 'NV-MARIO-01',
        ]);

        // % and _ are stripped from the needle, so the search becomes an exact-ish match.
        $match = $this->matcher->resolveStudent($colegio->id, '%Mar_iano Pérez%');
        $this->assertTrue($match->isUnique());
        $this->assertSame('Mariano Pérez', $match->model->name);

        // A wildcard-like needle should not expand and match unrelated records.
        $this->assertTrue($this->matcher->resolveStudent($colegio->id, '_ari')->isAmbiguous());
    }

    public function test_accent_folding_allows_exact_match(): void
    {
        $colegio = $this->createColegio();
        $student = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $colegio->director_user_id,
            'name' => 'José María Núñez',
            'grade' => '3ro',
            'family_code' => 'NV-ACC-01',
        ]);

        $match = $this->matcher->resolveStudent($colegio->id, 'jose maria nunez');

        $this->assertTrue($match->isUnique());
        $this->assertSame($student->id, $match->model->id);
    }

    public function test_ambiguous_candidates_do_not_include_emails(): void
    {
        $colegio = $this->createColegio();
        User::factory()->create([
            'name' => 'Carlos Pérez',
            'email' => 'carlos.perez@example.com',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        User::factory()->create([
            'name' => 'Carlos Gómez',
            'email' => 'carlos.gomez@example.com',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $match = $this->matcher->resolveTeacher($colegio->id, 'Carlos');

        $this->assertTrue($match->isAmbiguous());
        foreach ($match->candidates as $candidate) {
            $this->assertArrayNotHasKey('email', $candidate);
        }
    }

    public function test_invite_match_returns_code_in_candidate(): void
    {
        $colegio = $this->createColegio();
        TeacherInvite::create([
            'colegio_id' => $colegio->id,
            'created_by' => $colegio->director_user_id,
            'name' => 'Laura Martínez',
            'invite_code' => 'DOC-LAUR',
        ]);

        $match = $this->matcher->resolveTeacherOrInvite($colegio->id, 'Laura');

        $this->assertTrue($match->isUnique());
        $this->assertStringContainsString('DOC-LAUR', $match->label);
    }

    private function createColegio(string $name = 'Colegio Central', string $code = 'COC-1001'): Colegio
    {
        $director = User::factory()->create([
            'role' => 'director',
            'onboarding_completed' => true,
        ]);
        $colegio = Colegio::create([
            'name' => $name,
            'invite_code' => $code,
            'codes_pin' => Colegio::hashPinFromInvite($code),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);

        return $colegio->fresh();
    }
}
