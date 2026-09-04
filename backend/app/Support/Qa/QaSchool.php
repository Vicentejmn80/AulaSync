<?php

namespace App\Support\Qa;

/**
 * Catálogo fijo del colegio de prueba. Solo testing — no es producto.
 */
final class QaSchool
{
    public const SCHOOL_NAME = 'AulaSync QA School';

    public const SCHOOL_CODE = 'QA-AULA';

    public const OTHER_SCHOOL_NAME = 'AulaSync QA Other School';

    public const OTHER_SCHOOL_CODE = 'QA-OTRO';

    public const EMAIL_DOMAIN = 'qa.aulasync.test';

    public const PASSWORD = 'QaSchool!2026';

    public const TEACHER_COUNT = 5;

    public const PARENT_COUNT = 20;

    public const STUDENT_COUNT = 40;

    public const GRADES = ['1ro', '2do', '3ro', '4to', '5to'];

    public const SECTION = 'A';

    public const SCHOOL_YEAR = '2026-2027';

    /**
     * @return list<array{index:int,name:string,subjects:list<string>,grades:list<string>}>
     */
    public static function teacherBlueprints(): array
    {
        return [
            ['index' => 1, 'name' => 'Docente QA 01', 'subjects' => ['Matemática'], 'grades' => ['1ro', '2do']],
            ['index' => 2, 'name' => 'Docente QA 02', 'subjects' => ['Lenguaje'], 'grades' => ['1ro', '2do']],
            ['index' => 3, 'name' => 'Docente QA 03', 'subjects' => ['Ciencias'], 'grades' => ['3ro', '4to']],
            ['index' => 4, 'name' => 'Docente QA 04', 'subjects' => ['Historia'], 'grades' => ['3ro', '4to']],
            ['index' => 5, 'name' => 'Docente QA 05', 'subjects' => ['Inglés', 'Matemática'], 'grades' => ['5to']],
        ];
    }

    public static function pad(int $n): string
    {
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    public static function directorEmail(): string
    {
        return 'director.qa@'.self::EMAIL_DOMAIN;
    }

    public static function teacherEmail(int $index): string
    {
        return 'docente.qa.'.self::pad($index).'@'.self::EMAIL_DOMAIN;
    }

    public static function parentEmail(int $index): string
    {
        return 'representante.qa.'.self::pad($index).'@'.self::EMAIL_DOMAIN;
    }

    public static function teacherName(int $index): string
    {
        return 'Docente QA '.self::pad($index);
    }

    public static function parentName(int $index): string
    {
        return 'Representante QA '.self::pad($index);
    }

    public static function studentName(int $index): string
    {
        return 'Alumno QA '.self::pad($index);
    }

    public static function familyCode(int $parentIndex): string
    {
        return 'FAM-QA-'.self::pad($parentIndex);
    }

    public static function otherDirectorEmail(): string
    {
        return 'director.qa.other@'.self::EMAIL_DOMAIN;
    }

    public static function otherTeacherEmail(): string
    {
        return 'docente.qa.other@'.self::EMAIL_DOMAIN;
    }

    public static function otherParentEmail(): string
    {
        return 'representante.qa.other@'.self::EMAIL_DOMAIN;
    }

    public static function studentGrade(int $studentIndex): string
    {
        $bucket = (int) ceil($studentIndex / 8) - 1;

        return self::GRADES[$bucket] ?? '5to';
    }
}
