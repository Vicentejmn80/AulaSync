<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\DirectorAIInterpreterService;
use App\Services\DirectorDataAgentService;
use App\Services\DirectorUnifiedAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sprint 1 (Reingeniería del agente de IA): un solo catálogo de herramientas
 * y un solo método de ejecución para lectura y escritura.
 */
class DirectorUnifiedAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_catalog_merges_read_and_write_tools_without_duplicates(): void
    {
        $agent = app(DirectorUnifiedAgentService::class);

        $names = collect($agent->toolDefinitions())
            ->map(fn (array $def) => $def['function']['name'])
            ->values();

        // No duplicate tool names: a single catalog, not two merged separately.
        $this->assertSame($names->unique()->count(), $names->count());

        // Read tools (previously only in DirectorDataAgentService) are present.
        $this->assertTrue($names->contains('get_students'));
        $this->assertTrue($names->contains('get_teachers'));
        $this->assertTrue($names->contains('get_teacher_invite_code'));
        $this->assertTrue($names->contains('get_course_performance'));

        // Write tools (previously only in DirectorAIInterpreterService) are present.
        $this->assertTrue($names->contains('create_students_batch'));
        $this->assertTrue($names->contains('create_teacher'));
        $this->assertTrue($names->contains('delete_student'));

        // Previously-missing tools completed by the unification (diagnosed gaps).
        $this->assertTrue($names->contains('get_section_counts'));
        $this->assertTrue($names->contains('manage_invite_code'));

        // Every name in the catalog is classified as exactly read xor write.
        foreach ($names as $name) {
            $isRead = DirectorUnifiedAgentService::isReadTool($name);
            $isWrite = DirectorUnifiedAgentService::isWriteTool($name);
            $this->assertNotSame($isRead, $isWrite, "Tool '{$name}' should be classified as read XOR write.");
        }
    }

    public function test_interpreter_tool_definitions_delegate_to_unified_catalog(): void
    {
        $unified = app(DirectorUnifiedAgentService::class)->toolDefinitions();

        // Interpreter no longer builds its own duplicated array; it exposes
        // the exact same catalog via reflection into the private method.
        $interpreter = app(DirectorAIInterpreterService::class);
        $method = new \ReflectionMethod($interpreter, 'toolDefinitions');
        $method->setAccessible(true);
        $fromInterpreter = $method->invoke($interpreter);

        $this->assertSame(
            collect($unified)->map(fn ($d) => $d['function']['name'])->sort()->values()->all(),
            collect($fromInterpreter)->map(fn ($d) => $d['function']['name'])->sort()->values()->all(),
        );
    }

    public function test_unified_agent_reads_and_writes(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ana Ruiz',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-ANARUIZ',
        ]);

        $agent = app(DirectorUnifiedAgentService::class);

        // READ: get_students dispatches to DirectorDataAgentService.
        $readResult = $agent->execute($director, 'get_students', ['grade' => '4to', 'section' => 'A']);
        $this->assertStringContainsString('Ana Ruiz', (string) $readResult['message']);

        // WRITE: create_students_batch dispatches to DirectorActionService
        // and actually persists the student.
        $writeResult = $agent->execute($director, 'create_students_batch', [
            'names' => ['Georgina Vázquez'],
            'grade' => '3ro',
            'section' => 'A',
        ]);
        $this->assertCount(1, $writeResult['created']);
        $this->assertDatabaseHas('students', [
            'colegio_id' => $colegio->id,
            'name' => 'Georgina Vázquez',
            'grade' => '3ro',
        ]);

        // query_academic still goes through the legacy callback.
        $legacyCalledWith = null;
        $legacyResult = $agent->execute($director, 'query_academic', ['query_type' => 'school_stats'], function (array $args) use (&$legacyCalledWith) {
            $legacyCalledWith = $args;

            return ['message' => 'legacy ok', 'data' => []];
        });
        $this->assertSame('school_stats', $legacyCalledWith['query_type']);
        $this->assertSame('legacy ok', $legacyResult['message']);
    }

    public function test_unified_agent_manage_invite_code_tool_is_wired(): void
    {
        [$director, $colegio] = $this->directorContext();
        TeacherInvite::create([
            'colegio_id' => $colegio->id,
            'created_by' => $director->id,
            'name' => 'Manuel Vázquez',
            'invite_code' => 'DOC-6AXWC',
            'expires_at' => now()->addDays(30),
        ]);

        $agent = app(DirectorUnifiedAgentService::class);

        $this->assertTrue(DirectorUnifiedAgentService::isWriteTool('manage_invite_code'));

        $result = $agent->execute($director, 'manage_invite_code', [
            'operation' => 'query',
            'teacher_name' => 'Manuel Vázquez',
        ]);

        $this->assertSame('DOC-6AXWC', $result['invite']->invite_code);
    }

    public function test_unified_agent_get_teacher_invite_code_read_tool_is_wired(): void
    {
        [$director, $colegio] = $this->directorContext();
        User::factory()->create([
            'name' => 'Manuel Vázquez',
            'email' => 'manuel@example.com',
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        $agent = app(DirectorUnifiedAgentService::class);
        $this->assertTrue(DirectorUnifiedAgentService::isReadTool('get_teacher_invite_code'));
        $this->assertFalse(DirectorUnifiedAgentService::isWriteTool('get_teacher_invite_code'));

        $result = $agent->execute($director, 'get_teacher_invite_code', [
            'teacher_name' => 'Manuel Vázquez',
        ]);

        $this->assertSame(true, $result['data']['exists']);
        $this->assertSame('Manuel Vázquez', $result['data']['teacher_name']);
        $this->assertMatchesRegularExpression('/^DOC-[A-Z0-9]{4,8}$/', (string) $result['data']['invite_code']);
    }

    public function test_unified_agent_handles_intelligent_query_with_context_and_analysis(): void
    {
        [$director, $colegio] = $this->directorContext();
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $teacher->id,
            'name' => 'Ana Ruiz',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'FAM-ANARUIZ',
        ]);

        $agent = app(DirectorUnifiedAgentService::class);
        $result = $agent->handleIntelligentQuery(
            $director,
            '¿Cómo está el colegio?',
            [],
            null,
            fn (array $args) => ['message' => 'legacy', 'data' => $args],
            [],
        );

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertIsArray($result['tools'] ?? null);
        $this->assertNotEmpty($result['tools']);
        $this->assertIsArray($result['analysis'] ?? null);
        $this->assertIsArray($result['intelligent_query'] ?? null);
        $this->assertIsArray($result['intelligent_query']['context'] ?? null);
        $this->assertArrayHasKey('academic_year', $result['intelligent_query']['context']);
        $this->assertArrayHasKey('school_data', $result['intelligent_query']['context']);
    }

    public function test_unified_agent_rejects_unknown_tool(): void
    {
        [$director] = $this->directorContext();
        $agent = app(DirectorUnifiedAgentService::class);

        $this->expectException(ValidationException::class);
        $agent->execute($director, 'not_a_real_tool', []);
    }

    /**
     * @return array{0:User,1:Colegio}
     */
    private function directorContext(string $name = 'Colegio Unificado', string $code = 'COC-UNI01'): array
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

        return [$director->fresh(), $colegio];
    }
}
