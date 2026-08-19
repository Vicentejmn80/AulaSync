<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;

class SchoolRosterContextService
{
    public function markdownForDirector(User $director): string
    {
        $colegioId = (int) $director->colegio_id;
        if ($colegioId < 1) {
            return "Este director aún no está vinculado a un colegio.\n";
        }

        $teachersCount = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->count();
        $pendingInvitesCount = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->count();
        $coursesCount = Course::query()->where('colegio_id', $colegioId)->count();
        $studentsCount = Student::query()->where('colegio_id', $colegioId)->count();

        $colegio = $director->colegio;
        $schoolName = $colegio?->name ?: 'Colegio (sin nombre)';

        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->with(['courses' => fn ($q) => $q->orderBy('subject_name')->orderBy('grade')])
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name', 'email', 'role']);

        $invites = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('revoked_at')
            ->with(['courses' => fn ($q) => $q->orderBy('subject_name')->orderBy('grade')])
            ->latest()
            ->limit(80)
            ->get();

        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->with(['teacher:id,name', 'pendingInvite:id,name,invite_code'])
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->limit(120)
            ->get(['id', 'subject_name', 'grade', 'section', 'teacher_id', 'teacher_invite_id', 'invite_code']);

        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->orderBy('grade')
            ->orderBy('name')
            ->limit(200)
            ->get(['name', 'grade', 'section', 'family_code']);

        $lines = [];
        $lines[] = '## Resumen del colegio (conteos exactos, tiempo real)';
        $lines[] = '- Nombre oficial: '.$schoolName;
        $lines[] = '- Total de alumnos: '.$studentsCount;
        $lines[] = '- Total de profesores activos: '.$teachersCount;
        $lines[] = '- Total de invitaciones DOC- pendientes: '.$pendingInvitesCount;
        $lines[] = '- Total de cursos: '.$coursesCount;
        $lines[] = '';
        $lines[] = '## Profesores activos';
        if ($teachers->isEmpty()) {
            $lines[] = '- Ninguno registrado todavía.';
        }
        foreach ($teachers as $teacher) {
            $subjects = $teacher->courses
                ->map(fn ($c) => trim($c->subject_name.' '.$c->grade.($c->section ? '/'.$c->section : '')))
                ->filter()
                ->implode(', ');
            $invite = $invites->first(fn ($row) => (int) $row->claimed_by === (int) $teacher->id);
            $lines[] = sprintf(
                '- %s | email: %s | materias: %s | código DOC-: %s | estado: activo',
                $teacher->name,
                $teacher->email ?: 'sin correo',
                $subjects !== '' ? $subjects : 'sin cursos',
                $invite?->invite_code ?: 'ya registrado (sin código pendiente)'
            );
        }

        $lines[] = '';
        $lines[] = '## Invitaciones DOC- (pendientes o vinculadas)';
        if ($invites->isEmpty()) {
            $lines[] = '- Ninguna invitación.';
        }
        foreach ($invites as $invite) {
            $subjects = $invite->courses
                ->map(fn ($c) => trim($c->subject_name.' '.$c->grade.($c->section ? '/'.$c->section : '')))
                ->filter()
                ->implode(', ');
            if ($subjects === '' && $invite->subject_name) {
                $subjects = trim($invite->subject_name.' '.($invite->grade ?? ''));
            }
            $status = $invite->isClaimed() ? 'vinculado / ya se registró' : 'pendiente de registro';
            $lines[] = sprintf(
                '- %s | email: %s | materias: %s | código DOC-: %s | estado: %s',
                $invite->name,
                $invite->email ?: 'sin correo',
                $subjects !== '' ? $subjects : 'sin materia',
                $invite->invite_code,
                $status
            );
        }

        $lines[] = '';
        $lines[] = '## Cursos y secciones activas';
        if ($courses->isEmpty()) {
            $lines[] = '- Ningún curso.';
        }
        foreach ($courses as $course) {
            $owner = $course->teacher?->name
                ?? ($course->pendingInvite ? $course->pendingInvite->name.' ('.$course->pendingInvite->invite_code.')' : 'sin docente');
            $lines[] = sprintf(
                '- %s · %s%s | docente: %s | código de curso: %s',
                $course->subject_name,
                $course->grade,
                $course->section ? ' / '.$course->section : '',
                $owner,
                $course->invite_code ?: '—'
            );
        }

        $lines[] = '';
        $lines[] = '## Alumnos';
        if ($students->isEmpty()) {
            $lines[] = '- Ningún alumno.';
        }
        foreach ($students as $student) {
            $lines[] = sprintf(
                '- %s | grado: %s | sección: %s | código representante (NV-): %s',
                $student->name,
                $student->grade ?: '—',
                $student->section ?: '—',
                $student->family_code ?: 'sin código'
            );
        }

        return implode("\n", $lines);
    }
}
