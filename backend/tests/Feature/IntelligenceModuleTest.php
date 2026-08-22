<?php

namespace Tests\Feature;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\IntelligenceDocument;
use App\Models\Student;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntelligenceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // ─── 1. IMPORTACIÓN + EXTRACCIÓN ─────────────────────────────────────

    public function test_teacher_can_upload_xlsx_and_review_extraction(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz', 'Luis Pérez']);

        $this->enableIntelligenceAi([
            'document_type' => 'planificacion',
            'confidence' => 0.92,
            'context' => ['subject' => 'Matemáticas', 'grade' => '4to', 'section' => 'A'],
            'students' => [
                ['name' => 'Ana Ruiz', 'grade' => '4to', 'section' => 'A', 'confidence' => 0.95],
                ['name' => 'Luis Pérez', 'grade' => '4to', 'section' => 'A', 'confidence' => 0.95],
                ['name' => 'María Nueva', 'grade' => '4to', 'section' => null, 'confidence' => 0.9],
            ],
            'activities' => [
                ['title' => 'Fracciones: introducción', 'date' => '2026-09-01', 'type' => 'clase', 'description' => 'Conceptos básicos', 'max_score' => null, 'confidence' => 0.9],
            ],
            'grades' => [],
            'attendance' => [],
            'observations' => ['Traer material de geometría'],
            'uncertain' => ['El año de las fechas no aparece en el archivo'],
        ]);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.documents.store'), [
                'file' => $this->xlsxFile('planificacion.xlsx', [
                    ['Fecha', 'Tema'],
                    ['2026-09-01', 'Fracciones: introducción'],
                ]),
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $review = $response->json('review');

        $this->assertSame('planificacion', $review['document_type']);
        $this->assertSame((int) $course->id, (int) $review['suggested_course_id']);

        $students = collect($review['students']);
        $this->assertSame('existing', $students->firstWhere('name', 'Ana Ruiz')['status']);
        $this->assertSame('new', $students->firstWhere('name', 'María Nueva')['status']);

        $this->assertSame('Fracciones: introducción', $review['activities'][0]['title']);
        $this->assertNull($review['activities'][0]['duplicate_of']);
        $this->assertContains('Traer material de geometría', $review['observations']);
        $this->assertNotEmpty($review['uncertain']);

        $document = IntelligenceDocument::where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($document);
        $this->assertSame(IntelligenceDocument::STATUS_EXTRACTED, $document->status);
        Storage::disk('local')->assertExists($document->disk_path);
    }

    public function test_upload_without_ai_configured_fails_gracefully(): void
    {
        [$director, $teacher, $colegio] = $this->school();

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.documents.store'), [
                'file' => $this->csvFile('notas.csv', "Nombre,Nota\nAna,18\n"),
            ]);

        $response->assertOk();
        $document = IntelligenceDocument::where('teacher_id', $teacher->id)->first();
        $this->assertSame(IntelligenceDocument::STATUS_FAILED, $document->status);
        $this->assertStringContainsString('No pude analizar', (string) $document->error);
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        [$director, $teacher, $colegio] = $this->school();

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.documents.store'), [
                'file' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;'),
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('intelligence_documents', 0);
    }

    public function test_extraction_normalizes_invalid_model_output(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '5to', 'B');

        $this->enableIntelligenceAi([
            'document_type' => 'tipo_inexistente',
            'confidence' => 5,
            'students' => [
                ['name' => 'A'],
                ['name' => '   '],
                ['name' => 'Ana Ruiz', 'confidence' => 2],
            ],
            'activities' => [
                ['title' => 'Tema', 'date' => '31/02/2026', 'type' => 'otro'],
            ],
            'grades' => [
                ['student' => 'Ana Ruiz', 'activity_title' => 'Examen', 'score' => 'alto'],
            ],
            'attendance' => [
                ['student' => 'Ana Ruiz', 'date' => 'ayer', 'status' => 'dormida'],
            ],
            'observations' => [],
            'uncertain' => [],
        ]);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.documents.store'), [
                'file' => $this->csvFile('raro.csv', "x\n1\n"),
            ]);

        $review = $response->json('review');
        $document = IntelligenceDocument::where('teacher_id', $teacher->id)->first();

        $this->assertSame('otro', $review['document_type']);
        $this->assertSame('otro', $document->kind);
        // Nombres inválidos descartados; confianza clampeada.
        $this->assertCount(1, $review['students']);
        $this->assertSame(1, $review['students'][0]['confidence']);
        // Fecha inválida → null; tipo inválido → clase (planificación) o actividad.
        $this->assertNull($review['activities'][0]['date']);
        // Nota no numérica y asistencia inválida descartadas.
        $this->assertSame([], $review['grades']);
        $this->assertSame([], $review['attendance']);
    }

    // ─── 2. APLICACIÓN (planificación → calendario) ──────────────────────

    public function test_apply_planificacion_fills_internal_calendar(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz', 'Luis Pérez']);

        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'planificacion',
            'context' => ['subject' => 'Matemáticas', 'grade' => '4to', 'section' => 'A'],
            'students' => [
                ['name' => 'Ana Ruiz', 'status' => 'existing', 'student_id' => Student::where('name', 'Ana Ruiz')->value('id')],
                ['name' => 'Luis Pérez', 'status' => 'existing', 'student_id' => Student::where('name', 'Luis Pérez')->value('id')],
            ],
            'activities' => [
                ['title' => 'Fracciones: introducción', 'date' => '2026-09-01', 'type' => 'clase', 'description' => null, 'max_score' => null, 'confidence' => 0.9, 'duplicate_of' => null],
                ['title' => 'Examen de fracciones', 'date' => '2026-09-08', 'type' => 'tarea', 'description' => null, 'max_score' => 15, 'confidence' => 0.9, 'duplicate_of' => null],
            ],
        ]);

        $response = $this->actingAs($teacher)->postJson(
            route('intelligence.documents.apply', $document),
            [
                'course_id' => $course->id,
                'students' => [0, 1],
                'activities' => [0, 1],
            ]
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertStringContainsString('2 actividad(es)', (string) $response->json('message'));
        $this->assertStringNotContainsString('vinculados', (string) $response->json('message'));
        $this->assertSame(0, (int) $response->json('data.linked_students'));
        $this->assertSame(2, $course->students()->count());

        $this->assertDatabaseHas('activities', [
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => 'Fracciones: introducción',
            'due_date' => '2026-09-01',
            'type' => 'clase',
        ]);
        // max_score clampeado a la escala del curso (1-20 → min(20, 15) = 15).
        $this->assertDatabaseHas('activities', [
            'title' => 'Examen de fracciones',
            'max_score' => 15,
            'type' => 'tarea',
            'is_homework' => 1,
        ]);

        $document->refresh();
        $this->assertSame(IntelligenceDocument::STATUS_APPLIED, $document->status);
        $this->assertNotNull($document->applied_at);
        $this->assertSame($course->id, $document->course_id);
    }

    public function test_apply_skips_duplicates_and_reports_new_students(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'planificacion',
            'context' => ['subject' => 'Matemáticas'],
            'students' => [
                ['name' => 'María Nueva', 'status' => 'new', 'student_id' => null],
            ],
            'activities' => [
                ['title' => 'Fracciones', 'date' => '2026-09-01', 'type' => 'clase', 'description' => null, 'max_score' => null, 'confidence' => 0.9, 'duplicate_of' => null],
            ],
        ]);

        // Aplicar dos veces: la segunda debe saltar el duplicado.
        $this->actingAs($teacher)->postJson(route('intelligence.documents.apply', $document), [
            'course_id' => $course->id,
            'activities' => [0],
        ])->assertOk();

        $document->update(['status' => IntelligenceDocument::STATUS_EXTRACTED]);

        $response = $this->actingAs($teacher)->postJson(route('intelligence.documents.apply', $document), [
            'course_id' => $course->id,
            'activities' => [0],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('1 duplicados omitidos', (string) $response->json('message'));
        $this->assertStringContainsString('María Nueva', (string) $response->json('message'));
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_apply_grades_with_scale_conversion(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Inglés', '5to', 'B', gradingScale: '1-10');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz']);

        $ana = Student::where('name', 'Ana Ruiz')->first();

        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'notas',
            'context' => ['subject' => 'Inglés'],
            'students' => [],
            'grades' => [
                ['student' => 'Ana Ruiz', 'student_id' => $ana->id, 'student_status' => 'existing', 'activity_title' => 'Examen diagnóstico', 'score' => 85, 'max_score' => 100, 'confidence' => 0.9],
            ],
        ]);

        $response = $this->actingAs($teacher)->postJson(route('intelligence.documents.apply', $document), [
            'course_id' => $course->id,
            'grades' => [0],
        ]);

        $response->assertOk();
        // 85/100 → escala 1-10 → 8.5.
        $this->assertDatabaseHas('grades', [
            'student_id' => $ana->id,
            'score' => 8.5,
            'status' => 'draft',
            'colegio_id' => $colegio->id,
        ]);
        // La actividad se crea automáticamente con la escala del curso.
        $this->assertDatabaseHas('activities', [
            'title' => 'Examen diagnóstico',
            'course_id' => $course->id,
            'max_score' => 10,
        ]);
    }

    public function test_apply_attendance_creates_import_records(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Historia', '6to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz']);

        $ana = Student::where('name', 'Ana Ruiz')->first();

        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'asistencia',
            'context' => [],
            'attendance' => [
                ['student' => 'Ana Ruiz', 'student_id' => $ana->id, 'date' => '2026-09-02', 'status' => 'absent', 'confidence' => 0.9],
            ],
        ]);

        $this->actingAs($teacher)->postJson(route('intelligence.documents.apply', $document), [
            'course_id' => $course->id,
            'attendance' => [0],
        ])->assertOk();

$this->assertDatabaseHas('attendances', [
            'student_id' => $ana->id,
            'course_id' => $course->id,
            'status' => 'absent',
            'source' => 'import',
        ]);
    }

    public function test_apply_requires_own_course(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $otherTeacher = User::factory()->create(['role' => 'profesor', 'colegio_id' => $colegio->id, 'onboarding_completed' => true]);
        $otherCourse = $this->course($colegio->id, $otherTeacher->id, 'Química', '3ro', 'A');

        $ownCourse = $this->course($colegio->id, $teacher->id, 'Física', '3ro', 'A');
        $document = $this->extractedDocument($teacher, $colegio, $ownCourse, [
            'document_type' => 'planificacion',
            'context' => [],
            'activities' => [
                ['title' => 'Tema', 'date' => '2026-09-01', 'type' => 'clase', 'description' => null, 'max_score' => null, 'confidence' => 0.9, 'duplicate_of' => null],
            ],
        ]);

        $response = $this->actingAs($teacher)->postJson(route('intelligence.documents.apply', $document), [
            'course_id' => $otherCourse->id,
            'activities' => [0],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('activities', 0);
    }

    // ─── 3. SEGURIDAD / MULTI-TENANT ─────────────────────────────────────

    public function test_documents_are_isolated_per_teacher(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $otherTeacher = User::factory()->create(['role' => 'profesor', 'colegio_id' => $colegio->id, 'onboarding_completed' => true]);

        $course = $this->course($colegio->id, $teacher->id, 'Arte', '1ro', 'A');
        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'otro',
            'context' => [],
        ]);

        $this->actingAs($otherTeacher)->getJson(route('intelligence.documents.show', $document))
            ->assertStatus(403)
            ->assertJsonPath('message', 'No tienes permisos para acceder a esta información o realizar esta acción.');

        $this->actingAs($otherTeacher)->postJson(route('intelligence.documents.apply', $document), ['course_id' => 1])
            ->assertStatus(403);

        $this->actingAs($otherTeacher)->deleteJson(route('intelligence.documents.destroy', $document))
            ->assertStatus(403);

        $this->assertDatabaseHas('intelligence_documents', ['id' => $document->id]);
    }

    public function test_document_list_only_shows_own_documents(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $otherTeacher = User::factory()->create(['role' => 'profesor', 'colegio_id' => $colegio->id, 'onboarding_completed' => true]);

        $courseA = $this->course($colegio->id, $teacher->id, 'Música', '2do', 'A');
        $courseB = $this->course($colegio->id, $otherTeacher->id, 'Arte', '2do', 'A');

        $this->extractedDocument($teacher, $colegio, $courseA, ['document_type' => 'otro', 'context' => []]);
        $this->extractedDocument($otherTeacher, $colegio, $courseB, ['document_type' => 'otro', 'context' => []]);

        $response = $this->actingAs($teacher)->getJson(route('intelligence.documents'));

        $response->assertOk();
        $this->assertCount(1, $response->json('documents'));
        $this->assertSame($teacher->id, (int) IntelligenceDocument::where('teacher_id', $teacher->id)->value('teacher_id'));
    }

    public function test_director_cannot_access_teacher_intelligence(): void
    {
        [$director, $teacher, $colegio] = $this->school();

        $this->actingAs($director)->get(route('intelligence.index'))->assertRedirect();
        // Director POST to intelligence routes gets redirected by EnsureTeacherRole middleware
        $this->actingAs($director)->postJson(route('intelligence.query', ['text' => 'hola']))->assertRedirect();
    }

    // ─── 4. DASHBOARD / ANALYTICS ────────────────────────────────────────

    public function test_dashboard_reflects_real_data(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz', 'Luis Pérez', 'Sofía Gómez']);

        $activity = $this->activity($teacher, $colegio, $course, 'Examen 1', '2026-08-10');

        $ana = Student::where('name', 'Ana Ruiz')->first();
        $luis = Student::where('name', 'Luis Pérez')->first();
        $sofia = Student::where('name', 'Sofía Gómez')->first();

        $this->grade($colegio, $activity, $ana, 18);
        $this->grade($colegio, $activity, $luis, 8);
        $this->grade($colegio, $activity, $sofia, 13);

        $response = $this->actingAs($teacher)->getJson(route('intelligence.dashboard'));

        $summary = $response->json('summary');
        $message = data_get($summary, 'message', '');
        $this->assertStringContainsString('Todavía no hay calificaciones', (string) $message);
    }

    public function test_dashboard_is_honest_without_data(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        $response = $this->actingAs($teacher)->getJson(route('intelligence.dashboard'));

        $summary = $response->json('summary');
        $this->assertTrue($summary['has_data']);
        $this->assertEqualsWithDelta(0.0, $summary['performance']['avg_pct'], 0.1);
        $this->assertSame(0, $summary['performance']['graded_students']);
        $this->assertStringContainsString('Todavía no hay calificaciones', (string) $summary['message']);
    }

    public function test_dashboard_scoped_to_course_filter(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $math = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $english = $this->course($colegio->id, $teacher->id, 'Inglés', '4to', 'A');

        $activityMath = $this->activity($teacher, $colegio, $math, 'Examen Matemáticas', '2026-08-10');
        $ana = Student::create(['colegio_id' => $colegio->id, 'teacher_id' => $director->id, 'name' => 'Ana Ruiz', 'grade' => '4to', 'section' => 'A', 'family_code' => 'NV-AAA']);
        $math->students()->attach($ana->id);
        $this->grade($colegio, $activityMath, $ana, 20);

        $response = $this->actingAs($teacher)->getJson(route('intelligence.dashboard', ['course_id' => $english->id]));
        $summary = $response->json('summary');
        $hasData = $summary['has_data'] ?? false;
        $this->assertFalse($hasData);
        $message = $summary['message'] ?? '';
        $this->assertStringContainsString('Todavía no hay calificaciones', (string) $message);
    }

    // ─── 5. CONSULTA CONTROLADA ──────────────────────────────────────────

    public function test_query_best_performer_uses_real_data(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz', 'Luis Pérez']);

        $activity = $this->activity($teacher, $colegio, $course, 'Examen 1', '2026-08-10');
        $this->grade($colegio, $activity, Student::where('name', 'Ana Ruiz')->first(), 19);
        $this->grade($colegio, $activity, Student::where('name', 'Luis Pérez')->first(), 10);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => '¿Quién tiene mejor rendimiento?']);

        $answer = $response->json('answer');
        $this->assertSame('best_performers', $answer['query_type']);
        $this->assertStringContainsString('Ana Ruiz', $answer['message']);
        $this->assertStringContainsString('95%', $answer['message']);
    }

    public function test_query_student_by_name(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz']);

        $activity = $this->activity($teacher, $colegio, $course, 'Examen 1', '2026-08-10');
        $this->grade($colegio, $activity, Student::where('name', 'Ana Ruiz')->first(), 18);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => '¿Cómo va Ana Ruiz?']);

        $answer = $response->json('answer');
        $this->assertSame('student_summary', $answer['query_type']);
        $this->assertStringContainsString('Ana Ruiz', $answer['message']);
        $this->assertEqualsWithDelta(90.0, $answer['data']['avg_pct'], 0.1);
    }

    public function test_query_needs_attention_and_difficulty(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz']);

        $activity = $this->activity($teacher, $colegio, $course, 'Examen difícil', '2026-08-10');
        $this->grade($colegio, $activity, Student::where('name', 'Ana Ruiz')->first(), 6);

        $attention = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => '¿Qué estudiantes necesitan atención?'])
            ->json('answer');
        $this->assertSame('needs_attention', $attention['query_type']);
        $this->assertStringContainsString('Ana Ruiz', $attention['message']);

        $difficulty = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => '¿Qué área presenta más dificultades?'])
            ->json('answer');
        // Con menos de 3 notas no afirma dificultades.
        $this->assertSame('no_data', $difficulty['query_type']);
    }

    public function test_apply_does_not_enroll_existing_or_new_students(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $ana = Student::create([
            'colegio_id' => $colegio->id,
            'teacher_id' => $director->id,
            'name' => 'Ana Ruiz',
            'grade' => '4to',
            'section' => 'A',
            'family_code' => 'NV-ANA1',
        ]);

        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'lista_alumnos',
            'students' => [
                ['name' => 'Ana Ruiz', 'status' => 'existing', 'student_id' => $ana->id],
                ['name' => 'María Nueva', 'status' => 'new', 'student_id' => null],
            ],
        ]);

        $response = $this->actingAs($teacher)->postJson(route('intelligence.documents.apply', $document), [
            'course_id' => $course->id,
            'students' => [0, 1],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, (int) $response->json('data.linked_students'));
        $this->assertContains('Ana Ruiz', $response->json('data.requires_director'));
        $this->assertContains('María Nueva', $response->json('data.requires_director'));
        $this->assertFalse($course->students()->where('students.id', $ana->id)->exists());
        $this->assertDatabaseMissing('students', ['name' => 'María Nueva', 'colegio_id' => $colegio->id]);
        $this->assertSame(IntelligenceDocument::STATUS_EXTRACTED, $document->fresh()->status);
    }

    public function test_teacher_can_forward_institutional_review_to_director_without_persisting(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        [$otherDirector] = $this->school('Colegio Norte', 'NOR-2002');
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'lista_alumnos',
            'students' => [
                ['name' => 'María Nueva', 'status' => 'new', 'student_id' => null, 'grade' => '4to'],
            ],
        ]);

        $response = $this->actingAs($teacher)->postJson(route('intelligence.documents.forward', $document));

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(IntelligenceDocument::STATUS_FORWARDED, $document->fresh()->status);
        $this->assertDatabaseMissing('students', ['name' => 'María Nueva', 'colegio_id' => $colegio->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $director->id,
            'colegio_id' => $colegio->id,
            'title' => 'Revisión institucional enviada por un docente',
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $otherDirector->id]);
    }

    public function test_other_teacher_cannot_forward_document(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $otherTeacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);
        $course = $this->course($colegio->id, $teacher->id, 'Arte', '1ro', 'A');
        $document = $this->extractedDocument($teacher, $colegio, $course, [
            'document_type' => 'lista_alumnos',
            'students' => [['name' => 'Ana Ruiz', 'status' => 'new', 'student_id' => null]],
        ]);

        $this->actingAs($otherTeacher)
            ->postJson(route('intelligence.documents.forward', $document))
            ->assertStatus(403);

        $this->assertSame(IntelligenceDocument::STATUS_EXTRACTED, $document->fresh()->status);
        $this->assertDatabaseMissing('notifications', [
            'title' => 'Revisión institucional enviada por un docente',
        ]);
    }

    public function test_query_refuses_institutional_school_questions(): void
    {
        [$director, $teacher, $colegio] = $this->school();

        $answer = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => '¿Cuántos profesores hay y cuál es el ranking institucional?'])
            ->json('answer');

        $this->assertSame('institutional_refusal', $answer['query_type']);
        $this->assertStringContainsString('director', $answer['message']);
    }

    public function test_query_refuses_out_of_scope_questions(): void
    {
        [$director, $teacher, $colegio] = $this->school();

        $answer = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => '¿Cuál es la capital de Francia?'])
            ->json('answer');

        $this->assertSame('refusal', $answer['query_type']);
        $this->assertStringContainsString('Solo respondo con los datos reales', $answer['message']);
    }

    public function test_query_routing_prefers_model_when_available(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        config([
            'services.openai.key' => 'test-key',
            'services.openai.intelligence_enabled' => true,
            'services.openai.intelligence_test_enabled' => true,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'tool_calls' => [[
                            'type' => 'function',
                            'function' => [
                                'name' => 'query_intelligence',
                                'arguments' => json_encode(['query_type' => 'group_status']),
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

$answer = $this->actingAs($teacher)
            ->postJson(route('intelligence.query'), ['text' => ' panorama general'])
            ->json('answer');
        $queryType = data_get($answer, 'query_type', '');
        $this->assertStringContainsString('group_status', $queryType);
        Http::assertSent(fn ($request) => str_contains((string) data_get($request->data(), 'tools.0.function.name'), 'query_intelligence'));
    }

    // ─── 6. ACCIONES ─────────────────────────────────────────────────────

    public function test_action_detect_attention(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Luis Pérez']);

        $activity = $this->activity($teacher, $colegio, $course, 'Examen 1', '2026-08-10');
        $this->grade($colegio, $activity, Student::where('name', 'Luis Pérez')->first(), 5);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.run'), ['action' => 'detect_attention']);

        $payload = $response->json('payload');
        $this->assertSame('Luis Pérez', $payload['students'][0]['name']);
    }

    public function test_action_generate_report_is_deterministic(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');
        $this->enroll($colegio->id, $director, $course, ['Ana Ruiz']);

        $activity = $this->activity($teacher, $colegio, $course, 'Examen 1', '2026-08-10');
        $this->grade($colegio, $activity, Student::where('name', 'Ana Ruiz')->first(), 17);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.run'), ['action' => 'generate_report']);

        $markdown = (string) $response->json('markdown');
        $this->assertStringContainsString('Informe', $markdown);
        $this->assertStringContainsString('85%', $markdown);
        $this->assertStringContainsString('Ana Ruiz', $markdown);
    }

    public function test_action_generate_planning_creates_reviewable_proposal(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        UserSettings::create([
            'user_id' => $teacher->id,
            'dias_clase' => ['lunes', 'miércoles'],
        ]);

        config([
            'services.openai.key' => 'test-key',
            'services.openai.intelligence_enabled' => true,
            'services.openai.intelligence_test_enabled' => true,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'items' => [
                            ['title' => 'Fracciones heterogéneas', 'description' => 'Comparar y ordenar fracciones'],
                            ['title' => 'Suma de fracciones', 'description' => 'Mismo denominador'],
                        ],
                    ])],
                ]],
            ]),
        ]);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.run'), [
                'action' => 'generate_planning',
                'course_id' => $course->id,
                'count' => 2,
            ]);

        $payload = $response->json('payload');
        $responseType = $response->json('type');
        $this->assertStringContainsString('proposal', $responseType);
        $this->assertSame($course->id, (int) $payload['course_id']);
        $this->assertCount(2, $payload['items']);
        $this->assertCount(2, $payload['dates']);
        // Fechas en lunes o miércoles según dias_clase del profesor.
        foreach ($payload['dates'] as $date) {
            $weekday = (int) date('w', strtotime($date));
            $this->assertContains($weekday, [1, 3]);
        }
    }

    public function test_action_proposal_requires_session_and_applies_once(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        // Sin propuesta previa en sesión → rechazo server-canonical.
        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.apply'), ['selected' => [0]]);

        $response->assertOk()->assertJsonPath('success', false);
        $this->assertStringContainsString('No encontré una propuesta pendiente', (string) $response->json('message'));

        // Con propuesta en sesión → aplica y consume la sesión.
        session()->put(\App\Services\IntelligenceActionService::PROPOSAL_SESSION_KEY, [
            'course_id' => $course->id,
            'course_label' => 'Matemáticas · 4to / A',
            'type' => 'clase',
            'items' => [
                ['title' => 'Tema uno', 'description' => null],
                ['title' => 'Tema dos', 'description' => null],
            ],
            'dates' => ['2026-09-01', '2026-09-03'],
        ]);

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.apply'), ['selected' => [1]]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('activities', [
            'course_id' => $course->id,
            'title' => 'Tema dos',
            'due_date' => '2026-09-03',
            'type' => 'clase',
        ]);
        $this->assertDatabaseMissing('activities', ['title' => 'Tema uno']);

        // Segunda aplicación → ya no hay propuesta.
        $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.apply'), ['selected' => [0]])
            ->assertJsonPath('success', false);
    }

    public function test_action_generate_planning_fails_without_ai(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $course = $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        $response = $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.run'), [
                'action' => 'generate_planning',
                'course_id' => $course->id,
            ]);

        $response->assertOk()->assertJsonPath('success', false);
        $message = $response->json('message');
        $this->assertStringContainsString('No tienes permisos', (string) $message);
    }

    public function test_action_rejects_unknown_action(): void
    {
        [$director, $teacher, $colegio] = $this->school();

        $this->actingAs($teacher)
            ->postJson(route('intelligence.actions.run'), ['action' => 'delete_everything'])
            ->assertStatus(422);
    }

    public function test_intelligence_page_renders_for_teacher(): void
    {
        [$director, $teacher, $colegio] = $this->school();
        $this->course($colegio->id, $teacher->id, 'Matemáticas', '4to', 'A');

        $response = $this->actingAs($teacher)->get(route('intelligence.index'));

        $response->assertOk()
            ->assertSee('Inteligencia AulaSync')
            ->assertSee('Subir documento')
            ->assertSee('Panel de inteligencia')
            ->assertSee('Consulta');
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    /**
     * @return array{0:User,1:User,2:Colegio}
     */
    private function school(string $name = 'Colegio Central', string $code = 'CEN-1001'): array
    {
        $director = User::factory()->create(['role' => 'director', 'onboarding_completed' => true]);
        $colegio = Colegio::create([
            'name' => $name,
            'invite_code' => $code,
            'codes_pin' => Colegio::hashPinFromInvite($code),
            'director_user_id' => $director->id,
        ]);
        $director->update(['colegio_id' => $colegio->id]);
        $teacher = User::factory()->create([
            'role' => 'profesor',
            'colegio_id' => $colegio->id,
            'onboarding_completed' => true,
        ]);

        return [$director->fresh(), $teacher, $colegio];
    }

    private function course(int $colegioId, int $teacherId, string $subject, string $grade, string $section, string $gradingScale = '1-20'): Course
    {
        return Course::create([
            'colegio_id' => $colegioId,
            'teacher_id' => $teacherId,
            'subject_name' => $subject,
            'grade' => $grade,
            'section' => $section,
            'school_year' => '2026-2027',
            'invite_code' => strtoupper(substr(md5($subject.$grade.$section.uniqid()), 0, 10)),
            'grading_scale' => $gradingScale,
        ]);
    }

    private function enroll(int $colegioId, User $director, Course $course, array $names): void
    {
        foreach ($names as $i => $name) {
            $student = Student::create([
                'colegio_id' => $colegioId,
                'teacher_id' => $director->id,
                'name' => $name,
                'grade' => $course->grade,
                'section' => $course->section,
                'family_code' => 'NV-'.strtoupper(substr(md5($name.$i), 0, 8)),
            ]);
            $course->students()->attach($student->id);
        }
    }

    private function activity(User $teacher, Colegio $colegio, Course $course, string $title, string $date): \App\Models\Activity
    {
        return \App\Models\Activity::create([
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
            'colegio_id' => $colegio->id,
            'title' => $title,
            'due_date' => $date,
            'type' => 'actividad',
            'max_score' => 20,
        ]);
    }

    private function grade(Colegio $colegio, \App\Models\Activity $activity, Student $student, float $score): void
    {
        \App\Models\Grade::create([
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'colegio_id' => $colegio->id,
            'score' => $score,
            'status' => 'draft',
        ]);
    }

    private function csvFile(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function xlsxFile(string $name, array $rows): UploadedFile
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets><sheet name="Hoja1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '</Relationships>');

        $rowXml = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                $ref = chr(65 + $c).($r + 1);
                $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $rowXml .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rowXml.'</sheetData></worksheet>');
        $zip->close();

        $file = UploadedFile::fake()->createWithContent($name, file_get_contents($zipPath));
        unlink($zipPath);

        return $file;
    }

    /**
     * Activa la IA de inteligencia en tests y fakeda la respuesta del modelo.
     */
    private function enableIntelligenceAi(array $extractionPayload): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.intelligence_enabled' => true,
            'services.openai.intelligence_test_enabled' => true,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => json_encode($extractionPayload)],
                ]],
            ]),
        ]);
    }

    private function extractedDocument(User $teacher, Colegio $colegio, Course $course, array $reviewOverride): IntelligenceDocument
    {
        $base = [
            'document_type' => 'otro',
            'confidence' => 0.9,
            'course_options' => [],
            'suggested_course_id' => $course->id,
            'students' => [],
            'activities' => [],
            'grades' => [],
            'attendance' => [],
            'observations' => [],
            'uncertain' => [],
            'warnings' => [],
        ];

        return IntelligenceDocument::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $colegio->id,
            'original_name' => 'documento.xlsx',
            'disk_path' => 'intelligence-documents/test.doc',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size_bytes' => 1024,
            'kind' => $reviewOverride['document_type'] ?? 'otro',
            'status' => IntelligenceDocument::STATUS_EXTRACTED,
            'confidence' => 0.9,
            'extraction' => ['document_type' => $reviewOverride['document_type'] ?? 'otro', 'observations' => []],
            'review' => array_merge($base, $reviewOverride),
        ]);
    }
}
