<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AttendanceAlertService
{
    public function notifyAbsence(Attendance $attendance): array
    {
        if (! Schema::hasTable('notifications')) {
            return ['sent' => 0, 'parents' => []];
        }

        $attendance->loadMissing(['student', 'course', 'reason']);
        $student = $attendance->student;
        if (! $student) {
            return ['sent' => 0, 'parents' => []];
        }

        $parents = $this->parentsFor($student);
        if ($parents->isEmpty()) {
            return ['sent' => 0, 'parents' => []];
        }

        $statusLabel = $attendance->status === Attendance::STATUS_TARDY ? 'llegó tarde' : 'está ausente';
        $courseLabel = trim(($attendance->course?->subject_name ?? 'clase').' '.($attendance->course?->grade ?? ''));
        $dateLabel = optional($attendance->attended_on)->format('d/m/Y') ?: now()->format('d/m/Y');
        $reason = $attendance->reason?->label;

        $sent = [];
        foreach ($parents as $parent) {
            Notification::create([
                'user_id' => $parent->id,
                'colegio_id' => $attendance->colegio_id,
                'title' => 'Alerta de asistencia',
                'message' => "{$student->name} {$statusLabel} en {$courseLabel} ({$dateLabel})"
                    .($reason ? ". Motivo: {$reason}" : '')
                    .($attendance->note ? ". Nota: {$attendance->note}" : ''),
                'link' => route('representante.dashboard'),
            ]);
            $sent[] = $parent->name;
        }

        Notification::create([
            'user_id' => $attendance->teacher_id,
            'colegio_id' => $attendance->colegio_id,
            'title' => 'Notificación de ausencia enviada',
            'message' => 'Notificación de ausencia enviada a '.implode(', ', $sent).' sobre '.$student->name.'.',
            'link' => route('teacher.attendance.index'),
        ]);

        $attendance->update(['notified_at' => now()]);

        return ['sent' => count($sent), 'parents' => $sent];
    }

    public function notifyParentRequest(Student $student, User $parent, string $kind, string $range): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $kindLabel = $kind === 'tardy' ? 'retraso' : 'ausencia';
        $teachers = User::query()
            ->where('role', 'profesor')
            ->where('colegio_id', $student->colegio_id)
            ->whereIn('id', $student->courses()->pluck('teacher_id')->filter()->unique())
            ->get(['id', 'colegio_id']);

        foreach ($teachers as $teacher) {
            Notification::create([
                'user_id' => $teacher->id,
                'colegio_id' => $student->colegio_id,
                'title' => 'Familia reportó '.$kindLabel,
                'message' => "{$parent->name} reportó {$kindLabel} de {$student->name} ({$range}).",
                'link' => route('teacher.attendance.index'),
            ]);
        }
    }

    public function parentsFor(Student $student): Collection
    {
        $fromHousehold = collect();
        if ($student->family_code) {
            $fromHousehold = User::query()
                ->where('role', 'representante')
                ->where('family_code', $student->family_code)
                ->when($student->colegio_id, fn ($q) => $q->where('colegio_id', $student->colegio_id))
                ->get(['id', 'name', 'email', 'family_code', 'colegio_id']);
        }

        $fromPivot = collect();
        if (Schema::hasTable('guardian_student')) {
            $fromPivot = $student->guardians()
                ->where('users.role', 'representante')
                ->get(['users.id', 'users.name', 'users.email', 'users.family_code', 'users.colegio_id']);
        }

        return $fromHousehold->concat($fromPivot)->unique('id')->values();
    }
}
