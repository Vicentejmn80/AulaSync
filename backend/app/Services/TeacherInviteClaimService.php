<?php

namespace App\Services;

use App\Models\Course;
use App\Models\TeacherInvite;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TeacherInviteClaimService
{
    public function __construct(private StudentEnrollmentService $enrollment) {}

    /**
     * Vincula al docente con invitaciones DOC- pendientes (por código o email)
     * y asigna los cursos/alumnos que el director preparó.
     */
    public function claimForUser(User $user, ?TeacherInvite $invite = null): int
    {
        if ($user->role !== 'profesor') {
            return 0;
        }

        $claimed = 0;
        $invites = collect();

        if ($invite) {
            $invites->push($invite);
        }

        if ($user->colegio_id) {
            $pendingByIdentity = TeacherInvite::query()
                ->where('colegio_id', $user->colegio_id)
                ->whereNull('claimed_by')
                ->whereNull('claimed_at')
                ->whereNull('revoked_at')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->where(function ($query) use ($user) {
                    if ($user->email) {
                        $query->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
                    }
                    if ($user->name) {
                        $query->orWhereRaw('LOWER(name) = ?', [mb_strtolower(trim($user->name))]);
                    }
                })
                ->get();

            $invites = $invites->merge($pendingByIdentity);

            // Docente sin cursos + cursos huérfanos ligados a invitaciones pendientes
            if (Course::where('teacher_id', $user->id)->doesntExist()) {
                $orphanInviteIds = Course::query()
                    ->where('colegio_id', $user->colegio_id)
                    ->whereNull('teacher_id')
                    ->whereNotNull('teacher_invite_id')
                    ->pluck('teacher_invite_id')
                    ->unique()
                    ->filter();

                if ($orphanInviteIds->isNotEmpty()) {
                    $orphans = TeacherInvite::query()
                        ->whereIn('id', $orphanInviteIds)
                        ->whereNull('claimed_by')
                        ->whereNull('claimed_at')
                        ->whereNull('revoked_at')
                        ->where(function ($query) {
                            $query->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        })
                        ->get();

                    foreach ($orphans as $orphan) {
                        $emailMatch = $orphan->email && $user->email
                            && mb_strtolower($orphan->email) === mb_strtolower($user->email);
                        $nameMatch = $orphan->name
                            && mb_strtolower(trim($orphan->name)) === mb_strtolower(trim($user->name));

                        // Una sola invitación huérfana en el colegio → vincularla al docente sin cursos
                        if ($emailMatch || $nameMatch || $orphans->count() === 1) {
                            $invites->push($orphan);
                        }
                    }
                }
            }
        }

        foreach ($invites->unique('id') as $item) {
            try {
                if (! $item->isActive()) {
                    continue;
                }
                if ($item->isClaimed() && (int) $item->claimed_by !== (int) $user->id) {
                    continue;
                }
                $item->claimFor($user);
                $this->enrollment->syncTeacherCourses($user->fresh());
                $claimed++;
                Log::info('Invitación docente reclamada', [
                    'user_id' => $user->id,
                    'invite_id' => $item->id,
                    'invite_code' => $item->invite_code,
                ]);
            } catch (\Throwable $e) {
                Log::error('No se pudo reclamar invitación docente', [
                    'user_id' => $user->id,
                    'invite_id' => $item->id,
                    'invite_code' => $item->invite_code,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $claimed;
    }
}
