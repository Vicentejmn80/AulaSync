<?php

namespace Tests\Unit;

use App\Support\GradeLabel;
use PHPUnit\Framework\TestCase;

class GradeLabelTest extends TestCase
{
    public function test_third_grade_aliases_become_3ro(): void
    {
        foreach (['3er grado', 'tercer grado', 'tercero', '3ro', '3°'] as $alias) {
            $this->assertSame(3, GradeLabel::number($alias), $alias);
            $this->assertSame('3ro', GradeLabel::canonical($alias), $alias);
            $this->assertSame('3', GradeLabel::key($alias), $alias);
        }
    }
}
