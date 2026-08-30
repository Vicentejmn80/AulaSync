<?php

namespace Tests\Unit\Services;

use App\Services\DirectorIntentExtractorService;
use Tests\TestCase;

class DirectorIntentExtractorServiceTest extends TestCase
{
    private DirectorIntentExtractorService $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = app(DirectorIntentExtractorService::class);
    }

    public function test_create_teacher_and_enroll_students_in_one_phrase(): void
    {
        $text = 'Crea el profesor de matemáticas llamado Vicente José. '
            .'Él es el profesor de matemáticas desde 1ro hasta 6to grado. '
            .'Agrega a los alumnos Carlos Gutiérrez y Salvador Pérez a su materia de matemáticas, '
            .'que ambos son de 3ro.';

        $actions = $this->extractor->extractMultipleIntentions($text);

        $this->assertCount(2, $actions);

        $this->assertSame('create_teacher', $actions[0]['intent']);
        $this->assertSame('Vicente José', $actions[0]['data']['teacher_name']);
        $this->assertSame('Matemática', $actions[0]['data']['subject_name']);
        $this->assertSame(['1ro', '2do', '3ro', '4to', '5to', '6to'], $actions[0]['data']['grades']);

        $this->assertSame('enroll_students', $actions[1]['intent']);
        $this->assertSame(['Carlos Gutiérrez', 'Salvador Pérez'], $actions[1]['data']['names']);
        $this->assertSame('3ro', $actions[1]['data']['grade']);
        $this->assertSame('Matemática', $actions[1]['data']['subject_name']);
    }

    public function test_do_not_confuse_student_names_as_teachers(): void
    {
        $text = 'Crea el profesor de matemáticas llamado Vicente José. '
            .'Agrega a los alumnos Carlos Gutiérrez y Salvador Pérez a su materia de matemáticas, '
            .'que ambos son de 3ro.';

        $actions = $this->extractor->extractMultipleIntentions($text);

        $teacherNames = collect($actions)
            ->where('intent', 'create_teacher')
            ->pluck('data.teacher_name')
            ->all();

        $this->assertNotContains('Carlos Gutiérrez', $teacherNames);
        $this->assertNotContains('Salvador Pérez', $teacherNames);
        $this->assertContains('Vicente José', $teacherNames);

        $studentNames = collect($actions)
            ->where('intent', 'enroll_students')
            ->pluck('data.names')
            ->flatten()
            ->all();

        $this->assertContains('Carlos Gutiérrez', $studentNames);
        $this->assertContains('Salvador Pérez', $studentNames);
        $this->assertNotContains('Vicente José', $studentNames);
    }

    public function test_extracts_multiple_teachers_in_one_message(): void
    {
        $text = 'Crea a los siguientes profesores: Jorge Alarcón (inglés de 1ro a 6to), Miguel Zambrano (computación 1ro a 6to).';

        $actions = $this->extractor->extractMultipleIntentions($text);

        $this->assertCount(2, $actions);
        $this->assertSame('create_teacher', $actions[0]['intent']);
        $this->assertSame('Jorge Alarcón', $actions[0]['data']['teacher_name']);
        $this->assertSame('Inglés', $actions[0]['data']['subject_name']);
        $this->assertSame(['1ro', '2do', '3ro', '4to', '5to', '6to'], $actions[0]['data']['grades']);

        $this->assertSame('create_teacher', $actions[1]['intent']);
        $this->assertSame('Miguel Zambrano', $actions[1]['data']['teacher_name']);
        $this->assertSame('Computación', $actions[1]['data']['subject_name']);
    }

    public function test_extracts_create_students_batch_intent(): void
    {
        $text = 'Crea a los alumnos Carlos Duarte, Fermín López y Enrique Quesada en 2do de computación.';

        $actions = $this->extractor->extractMultipleIntentions($text);

        $this->assertCount(1, $actions);
        $this->assertSame('create_students_batch', $actions[0]['intent']);
        $this->assertSame(['Carlos Duarte', 'Fermín López', 'Enrique Quesada'], $actions[0]['data']['names']);
        $this->assertSame('2do', $actions[0]['data']['grade']);
        $this->assertSame('Computación', $actions[0]['data']['subject_name']);
    }

    public function test_returns_empty_array_for_irrelevant_text(): void
    {
        $actions = $this->extractor->extractMultipleIntentions('Hola, ¿cómo estás?');

        $this->assertSame([], $actions);
    }

    public function test_extracts_grade_variants_and_subjects(): void
    {
        $text = 'Crea al profesor Mariano García de Lenguaje desde 1ero hasta 6to.';

        $actions = $this->extractor->extractMultipleIntentions($text);

        $this->assertCount(1, $actions);
        $this->assertSame('create_teacher', $actions[0]['intent']);
        $this->assertSame('Mariano García', $actions[0]['data']['teacher_name']);
        $this->assertSame('Lenguaje', $actions[0]['data']['subject_name']);
        $this->assertSame(['1ro', '2do', '3ro', '4to', '5to', '6to'], $actions[0]['data']['grades']);
    }

    public function test_does_not_parse_grade_words_as_teacher_name_and_keeps_student_list(): void
    {
        $text = 'Crea al profesor Junior Vázquez como profesor de matemática de segundo grado y crea a los alumnos Vicente José y Valeria Navarro de segundo grado.';

        $actions = $this->extractor->extractMultipleIntentions($text);

        $this->assertCount(2, $actions);
        $this->assertSame('create_teacher', $actions[0]['intent']);
        $this->assertSame('Junior Vázquez', $actions[0]['data']['teacher_name']);
        $this->assertSame(['2do'], $actions[0]['data']['grades']);

        $this->assertSame('create_students_batch', $actions[1]['intent']);
        $this->assertSame(['Vicente José', 'Valeria Navarro'], $actions[1]['data']['names']);
        $this->assertSame('2do', $actions[1]['data']['grade']);
    }

    public function test_detects_enrollment_sync_command(): void
    {
        $actions = $this->extractor->extractMultipleIntentions('sincroniza las matrículas de todos los alumnos');

        $this->assertCount(1, $actions);
        $this->assertSame('sync_all_enrollments', $actions[0]['intent']);
    }

    public function test_expands_2do_grado_a_6to_grado_range_for_teacher(): void
    {
        $actions = $this->extractor->extractMultipleIntentions(
            'Quiero que creas al profesor Carlos Gutiérrez, que va a ser el profesor de biología de 2do grado a 6to grado.'
        );

        $this->assertCount(1, $actions);
        $this->assertSame('create_teacher', $actions[0]['intent']);
        $this->assertSame('Carlos Gutiérrez', $actions[0]['data']['teacher_name']);
        $this->assertSame('Biología', $actions[0]['data']['subject_name']);
        $this->assertSame(['2do', '3ro', '4to', '5to', '6to'], $actions[0]['data']['grades']);
    }

    public function test_generic_subject_words_are_not_extracted_as_subject_name(): void
    {
        $actions = $this->extractor->extractMultipleIntentions(
            'Crea a los alumnos Carlos Duarte y Ana Ruiz en 3ro de materia.'
        );

        $this->assertCount(1, $actions);
        $this->assertSame('create_students_batch', $actions[0]['intent']);
        $this->assertNull($actions[0]['data']['subject_name']);
    }
}
