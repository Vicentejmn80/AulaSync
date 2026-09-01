<?php

namespace App\Helpers;

use App\Models\Colegio;
use App\Models\Course;
use App\Models\FamilyInvite;
use App\Models\TeacherInvite;
use Illuminate\Support\Str;

class InviteCodeHelper
{
    public static function normalize(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return preg_replace('/[^A-Z0-9\-]/', '', $normalized) ?? '';
    }

    public static function makePrefix(string $schoolName): string
    {
        $parts = preg_split('/\s+/', trim($schoolName)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = strtoupper(substr(Str::slug($schoolName, ''), 0, 3));
        }

        return str_pad(substr($initials, 0, 3), 3, 'X');
    }

    public static function generateUnique(string $schoolName): string
    {
        $prefix = self::makePrefix($schoolName);

        do {
            $candidate = $prefix . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Colegio::where('invite_code', $candidate)->exists());

        return $candidate;
    }

    public static function generateTeacherInvite(): string
    {
        do {
            $candidate = 'DOC-' . strtoupper(Str::random(5));
        } while (TeacherInvite::where('invite_code', $candidate)->exists());

        return $candidate;
    }

    public static function generateFamilyInvite(): string
    {
        do {
            $candidate = 'FAM-' . strtoupper(Str::random(5));
        } while (FamilyInvite::where('invite_code', $candidate)->exists());

        return $candidate;
    }

    public static function generateCourseCode(string $subject, ?string $grade = null, ?string $section = null): string
    {
        $subjectSlug = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::slug($subject, ''))) ?: 'CURSO', 0, 10));
        $gradeSlug = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $grade)) ?: '', 0, 4));
        $sectionSlug = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $section)) ?: '', 0, 2));

        $base = 'CURSO-' . $subjectSlug;
        if ($gradeSlug !== '') {
            $base .= '-' . $gradeSlug;
        }
        if ($sectionSlug !== '') {
            $base .= $sectionSlug;
        }

        $candidate = $base;
        $i = 2;
        while (Course::where('invite_code', $candidate)->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}
