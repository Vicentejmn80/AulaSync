<?php

namespace App\Services;

use App\Models\Colegio;
use App\Models\Invitation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function issue(array $payload, ?User $actor = null): Invitation
    {
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $role = Invitation::normalizeRole((string) ($payload['role'] ?? Invitation::ROLE_DIRECTOR));
        $colegioId = isset($payload['colegio_id']) ? (int) $payload['colegio_id'] : null;
        $studentId = isset($payload['student_id']) ? (int) $payload['student_id'] : null;

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

        Invitation::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('role', $role)
            ->whereNull('accepted_at')
            ->when($colegioId, fn ($q) => $q->where('colegio_id', $colegioId))
            ->update(['expires_at' => now()->subMinute()]);

        $invitation = Invitation::create([
            'email' => $email,
            'role' => $role,
            'colegio_id' => $colegioId ?: null,
            'student_id' => $studentId ?: null,
            'invited_by' => $actor?->id,
            'token' => Invitation::makeToken(),
            'expires_at' => now()->addHours(48),
        ]);

        $this->trySendMail($invitation);

        return $invitation;
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

        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::create([
                'name' => trim($name),
                'email' => $invitation->email,
                'password' => Hash::make($password),
                'role' => $invitation->role,
                'colegio_id' => $invitation->colegio_id,
                'onboarding_completed' => true,
            ]);

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

            $invitation->update(['accepted_at' => now()]);

            if ($user->role === 'profesor') {
                app(TeacherInviteClaimService::class)->claimForUser($user->fresh());
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
            Mail::raw(
                "Te invitaron a AulaSync como {$invitation->roleLabel()}.\n\n".
                "Abre este enlace para crear tu cuenta (vence en 48 horas):\n".
                $invitation->acceptUrl(),
                function ($message) use ($invitation) {
                    $message->to($invitation->email)
                        ->subject('Invitación a AulaSync');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('invitation.mail_failed', [
                'email' => $invitation->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
