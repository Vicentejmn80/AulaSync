<?php

namespace Tests\Unit\Support;

use App\Support\LessonTemplate;
use App\Support\PedagogicalGenerationPrompt;
use Tests\TestCase;

class PedagogicalGenerationPromptTest extends TestCase
{
    public function test_rules_ban_cliche_phrases_and_require_methodology(): void
    {
        $rules = PedagogicalGenerationPrompt::rules(LessonTemplate::CLASSIC);

        $this->assertStringContainsString('pregunta disparadora', $rules);
        $this->assertStringContainsString('se explicitan saberes previos', $rules);
        $this->assertStringContainsString('exposición ordenada para el cuaderno', $rules);
        $this->assertStringContainsString('PROHIBIDO', $rules);
        $this->assertStringContainsString('methodology=clasica', $rules);
        $this->assertStringContainsString('Enganche activo', $rules);
        $this->assertStringContainsString('mímica', $rules);
    }

    public function test_five_e_and_project_methodology_blocks_are_distinct(): void
    {
        $fiveE = PedagogicalGenerationPrompt::methodologyBlock(LessonTemplate::CONSTRUCTIVIST);
        $project = PedagogicalGenerationPrompt::methodologyBlock(LessonTemplate::PROJECT);

        $this->assertStringContainsString('Modelo 5E', $fiveE);
        $this->assertStringContainsString('Engage', $fiveE);
        $this->assertStringContainsString('ABR/ABP', $project);
        $this->assertStringContainsString('Planteamiento del Reto', $project);
        $this->assertStringNotContainsString('Modelo 5E', $project);
    }

    public function test_session_roles_follow_intro_practice_consolidation(): void
    {
        $this->assertSame('introduccion', PedagogicalGenerationPrompt::sessionRole(0, 4));
        $this->assertSame('practica', PedagogicalGenerationPrompt::sessionRole(1, 4));
        $this->assertSame('consolidacion', PedagogicalGenerationPrompt::sessionRole(3, 4));
    }

    public function test_fallback_lessons_are_unique_and_avoid_cliches(): void
    {
        $first = PedagogicalGenerationPrompt::fallbackLessonMarkdown('fracciones', LessonTemplate::CLASSIC, 0, 3);
        $middle = PedagogicalGenerationPrompt::fallbackLessonMarkdown('fracciones', LessonTemplate::CLASSIC, 1, 3);
        $last = PedagogicalGenerationPrompt::fallbackLessonMarkdown('fracciones', LessonTemplate::CLASSIC, 2, 3);

        foreach ([$first, $middle, $last] as $lesson) {
            $this->assertStringNotContainsStringIgnoringCase('pregunta disparadora', $lesson);
            $this->assertStringNotContainsStringIgnoringCase('se explicitan saberes previos', $lesson);
            $this->assertStringNotContainsStringIgnoringCase('exposición ordenada para el cuaderno', $lesson);
            $this->assertTrue(LessonTemplate::hasRequiredHeaders($lesson, LessonTemplate::CLASSIC));
            $this->assertStringContainsString('docente', mb_strtolower($lesson));
        }

        $this->assertNotSame($first, $middle);
        $this->assertNotSame($middle, $last);
        $this->assertMatchesRegularExpression('/curiosidad|enigma|asombro|hielo|mímica|misterio/iu', $first);
        $this->assertStringContainsString('3 minutos', $middle);
        $this->assertMatchesRegularExpression('/fijación|síntesis|producción|producto/iu', $last);
    }

    public function test_fallback_respects_five_e_headers(): void
    {
        $lesson = PedagogicalGenerationPrompt::fallbackLessonMarkdown('fotosíntesis', LessonTemplate::CONSTRUCTIVIST, 0, 1);

        $this->assertTrue(LessonTemplate::hasRequiredHeaders($lesson, LessonTemplate::CONSTRUCTIVIST));
        $this->assertStringContainsString('**ACTIVACIÓN**', $lesson);
        $this->assertStringContainsString('**EXPLORACIÓN**', $lesson);
    }
}
