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
}
