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

    public function test_range_from_2do_grado_a_6to_grado_includes_middle_grades(): void
    {
        $text = 'Quiero que creas al profesor Carlos Gutiérrez, que va a ser el profesor de biología de 2do grado a 6to grado.';

        $this->assertSame(
            ['2do', '3ro', '4to', '5to', '6to'],
            GradeLabel::expandRangeFromText($text)
        );
        $this->assertSame(
            ['2do', '3ro', '4to', '5to', '6to'],
            GradeLabel::preferExpandedRange(['2do', '6to'], $text)
        );
    }

    public function test_does_not_treat_loose_and_as_a_range(): void
    {
        $this->assertSame([], GradeLabel::expandRangeFromText('Crea Matemática de 2do y 6to'));
        $this->assertSame(['2do', '6to'], GradeLabel::preferExpandedRange(['2do', '6to'], 'Crea Matemática de 2do y 6to'));
    }
}
