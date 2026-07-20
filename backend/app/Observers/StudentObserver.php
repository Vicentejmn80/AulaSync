<?php

namespace App\Observers;

use App\Models\Student;
use Illuminate\Support\Str;

class StudentObserver
{
    public function creating(Student $student): void
    {
        if (empty($student->family_code)) {
            $student->family_code = self::generateFamilyCode();
        }
    }

    public static function generateFamilyCode(): string
    {
        do {
            $code = 'NV-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(2));
        } while (Student::where('family_code', $code)->exists());

        return $code;
    }
}
