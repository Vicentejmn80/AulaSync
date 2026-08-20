<?php

namespace Tests\Unit;

use App\Services\PersonNameSanitizer;
use PHPUnit\Framework\TestCase;

class PersonNameSanitizerTest extends TestCase
{
    public function test_strips_course_and_grade_suffixes(): void
    {
        $sanitizer = new PersonNameSanitizer();

        $this->assertSame('andres perez', $sanitizer->clean('andres perez en el curso de primer grado de ingles'));
        $this->assertSame('Andrés Pérez', $sanitizer->clean('Andrés Pérez de 1ro'));
        $this->assertSame('andres perez', $sanitizer->clean('andres perez y asignalo al curso de ingles'));
        $this->assertSame('María López', $sanitizer->clean('alumna María López en 1ro'));
        $this->assertNull($sanitizer->clean('en el'));
    }

    public function test_cleans_colloquial_teacher_prompts(): void
    {
        $sanitizer = new PersonNameSanitizer();

        $this->assertSame('juan carlos', $sanitizer->cleanTeacher('de matematica llamado juan carlos'));
        $this->assertSame('juan carlos', $sanitizer->cleanTeacher('profesor de matematica llamado juan carlos'));
        $this->assertSame('juan carlos', $sanitizer->cleanTeacher('docente de matemática llamado juan carlos'));
        $this->assertSame('Juan Carlos', $sanitizer->displayName('profesor de matematica llamado juan carlos'));
        $this->assertSame('Juan Carlos', $sanitizer->displayName('de matemática llamado juan carlos'));
    }

    public function test_strips_filler_phrases_from_teacher_names(): void
    {
        $sanitizer = new PersonNameSanitizer();

        $this->assertSame('mariano', $sanitizer->cleanTeacher('mariano tambien que te dije'));
        $this->assertSame('Mariano', $sanitizer->displayName('profesor mariano tambien que te dije'));
        $this->assertSame('Mariano García', $sanitizer->displayName('mariano garcía el que te mencioné antes'));
        $this->assertSame('Laureano Márquez', $sanitizer->displayName('alumno laureano márquez en 2do'));
    }

    public function test_rejects_section_and_course_context_as_names(): void
    {
        $sanitizer = new PersonNameSanitizer();

        $this->assertNull($sanitizer->clean('en la seccion'));
        $this->assertNull($sanitizer->clean('En La Seccion'));
        $this->assertNull($sanitizer->clean('para el'));
        $this->assertNull($sanitizer->clean('siguientes alumnos'));
        $this->assertSame('carlos duarte', $sanitizer->clean('carlos duarte'));
    }
}
