<?php

namespace App\Services;

use App\Mail\TeacherInvitationMail;
use App\Models\Colegio;
use App\Models\Invitation;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Support\DatabaseBoolean;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function issue(array $payload, ?User $actor = null): Invitation
    {
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $name = trim((string) ($payload['name'] ?? ''));
        $role = Invitation::normalizeRole((string) ($payload['role'] ?? Invitation::ROLE_DIRECTOR));
        $colegioId = isset($payload['colegio_id']) ? (int) $payload['colegio_id'] : null;
        $studentId = isset($payload['student_id']) ? (int) $payload['student_id'] : null;
        $teacherInviteId = isset($payload['teacher_invite_id']) ? (int) $payload['teacher_invite_id'] : null;

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Indica un correo válido para la invitación.',
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese correo. Debe iniciar sesión en /login.',
            ]);
        }

        if ($colegioId && ! Colegio::query()->whereKey($colegioId)->exists()) {
            throw ValidationException::withMessages([
                'colegio_id' => 'El colegio de la invitación no existe.',
            ]);
        }

        if ($studentId && ! Student::query()->whereKey($studentId)->exists()) {
            throw ValidationException::withMessages([
                'student_id' => 'El alumno vinculado no existe.',
            ]);
        }

        if ($teacherInviteId && ! TeacherInvite::query()->whereKey($teacherInviteId)->exists()) {
            throw ValidationException::withMessages([
                'teacher_invite_id' => 'La invitación DOC- no existe.',
            ]);
        }

        Invitation::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', $role)
            ->whereNull('accepted_at')
            ->when($colegioId, fn ($q) => $q->where('colegio_id', $colegioId))
            ->update(['expires_at' => now()->subMinute()]);

        $expiresAt = now()->addHours(48);
        if (isset($payload['expires_in_days'])) {
            $expiresAt = now()->addDays(max(1, (int) $payload['expires_in_days']));
        } elseif ($role === Invitation::ROLE_DOCENTE) {
            $expiresAt = now()->addDays(7);
        }

        $invitation = Invitation::create([
            'email' => $email,
            'name' => $name !== '' ? $name : null,
            'role' => $role,
            'colegio_id' => $colegioId ?: null,
            'student_id' => $studentId ?: null,
            'teacher_invite_id' => $teacherInviteId ?: null,
            'invited_by' => $actor?->id,
            'token' => Invitation::makeToken(),
            'expires_at' => $expiresAt,
        ]);

        $this->notify($invitation);

        return $invitation;
    }

    public function resendForTeacherInvite(TeacherInvite $invite, ?User $actor = null): Invitation
    {
        if ($invite->isClaimed()) {
            throw ValidationException::withMessages([
                'invite' => 'Esta invitación ya fue usada. El profesor debe iniciar sesión.',
            ]);
        }

        $email = mb_strtolower(trim((string) ($invite->email ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Agrega un correo al profesor para enviar el enlace de activación.',
            ]);
        }

        $pending = Invitation::query()
            ->whereNull('accepted_at')
            ->where(function ($query) use ($invite, $email) {
                $query->where('teacher_invite_id', $invite->id)
                    ->orWhere(function ($inner) use ($invite, $email) {
                        $inner->where('role', Invitation::ROLE_DOCENTE)
                            ->where('colegio_id', $invite->colegio_id)
                            ->whereRaw('LOWER(email) = ?', [$email]);
                    });
            })
            ->latest('id')
            ->first();

        if ($pending && $pending->accepted_at === null) {
            $pending->forceFill([
                'email' => $email,
                'name' => $invite->display_name ?: $invite->name,
                'teacher_invite_id' => $invite->id,
                'colegio_id' => $invite->colegio_id,
            ]);
            if (! $pending->expires_at || $pending->expires_at->isPast()) {
                $pending->token = Invitation::makeToken();
                $pending->expires_at = now()->addDays(7);
            }
            $pending->save();
            $this->notify($pending->fresh(['colegio', 'teacherInvite']));

            return $pending->fresh(['colegio', 'teacherInvite']);
        }

        return $this->issue([
            'email' => $email,
            'name' => $invite->display_name ?: $invite->name,
            'role' => Invitation::ROLE_DOCENTE,
            'colegio_id' => $invite->colegio_id,
            'teacher_invite_id' => $invite->id,
            'expires_in_days' => 7,
        ], $actor);
    }

    public function notify(Invitation $invitation): void
    {
        $this->trySendMail($invitation);
    }

    public function accept(Invitation $invitation, string $name, string $password): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'Esta invitación ya no es válida.',
            ]);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con este correo. Inicia sesión en /login.',
            ]);
        }

        $resolvedName = trim($invitation->name ?: $name);

        return DB::transaction(function () use ($invitation, $resolvedName, $password) {
            $completed = $invitation->role !== Invitation::ROLE_DIRECTOR;

            $user = new User();
            $user->forceFill([
                'name' => $resolvedName,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
                'colegio_id' => $invitation->colegio_id,
            ])->save();

            DB::table('users')->where('id', $user->id)->update([
                'onboarding_completed' => DatabaseBoolean::bind($completed),
            ]);
            $user->refresh();

            if ($invitation->role === Invitation::ROLE_DIRECTOR && $invitation->colegio_id) {
                Colegio::query()
                    ->whereKey($invitation->colegio_id)
                    ->whereNull('director_user_id')
                    ->update(['director_user_id' => $user->id]);
            }

            if ($invitation->role === Invitation::ROLE_REPRESENTANTE && $invitation->student_id) {
                $user->representedStudents()->syncWithoutDetaching([
                    $invitation->student_id => ['relationship' => 'representante'],
                ]);
            }

            $invitation->update([
                'accepted_at' => now(),
                'token' => Invitation::makeToken(),
            ]);

            if ($user->role === 'profesor') {
                $specific = $invitation->teacher_invite_id
                    ? TeacherInvite::query()->find($invitation->teacher_invite_id)
                    : null;
                app(TeacherInviteClaimService::class)->claimForUser($user->fresh(), $specific);
            }

            return $user->fresh();
        });
    }

    public function dashboardUrl(User $user): string
    {
        return match ($user->role) {
            'director' => url('/director/dashboard'),
            'profesor' => url('/teacher/hub'),
            'representante' => url('/representante/dashboard'),
            default => url('/dashboard'),
        };
    }

    private function trySendMail(Invitation $invitation): void
    {
        try {
            if ($invitation->role === Invitation::ROLE_DOCENTE) {
                $invitation->loadMissing(['colegio', 'teacherInvite']);
                Mail::to($invitation->email)->send(new TeacherInvitationMail($invitation));

                return;
            }

            $hours = $invitation->expires_at?->isFuture()
                ? (int) now()->diffInHours($invitation->expires_at, false)
                : 48;

            Mail::raw(
                "Te invitaron a AulaSync como {$invitation->roleLabel()}.\n\n".
                "Abre este enlace para crear tu cuenta (vence en {$hours} horas):\n".
                $invitation->acceptUrl(),
                function ($message) use ($invitation) {
                    $message->to($invitation->email)
                        ->subject('Invitación a AulaSync');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Error enviando correo vía Resend: '.$e->getMessage(), [
                'email' => $invitation->email,
                'role' => $invitation->role,
            ]);
        }
    }
}
