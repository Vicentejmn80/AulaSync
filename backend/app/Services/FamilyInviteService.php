<?php

namespace App\Services;

use App\Helpers\InviteCodeHelper;
use App\Models\Colegio;
use App\Models\FamilyInvite;
use App\Models\Student;
use App\Models\User;
use App\Observers\StudentObserver;
use App\Support\DatabaseBoolean;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FamilyInviteService
{
    public const MAX_GUARDIANS = 3;

    public function ensureForStudent(Student $student, ?User $actor = null): FamilyInvite
    {
        if (! $student->family_code) {
            $student->family_code = StudentObserver::generateFamilyCode();
            $student->save();
        }

        $existing = FamilyInvite::query()
            ->where('colegio_id', $student->colegio_id)
            ->where('family_code', $student->family_code)
            ->whereNull('revoked_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return FamilyInvite::create([
            'colegio_id' => $student->colegio_id,
            'created_by' => $actor?->id,
            'family_code' => $student->family_code,
            'invite_code' => InviteCodeHelper::generateFamilyInvite(),
        ]);
    }

    /**
     * @return Collection<int, Student>
     */
    public function students(FamilyInvite $invite): Collection
    {
        return Student::query()
            ->where('colegio_id', $invite->colegio_id)
            ->where('family_code', $invite->family_code)
            ->orderBy('name')
            ->get();
    }

    public function serialize(FamilyInvite $invite, ?Student $focus = null): array
    {
        $invite->loadMissing('colegio');
        $students = $this->students($invite);
        $guardians = $this->guardians($invite);

        return [
            'id' => $invite->id,
            'kind' => 'family',
            'invite_code' => $invite->invite_code,
            'invitation_link' => $invite->registrationUrl(),
            'family_code' => $invite->family_code,
            'student_id' => $focus?->id ?? $students->first()?->id,
            'name' => $focus?->name ?? $students->pluck('name')->filter()->implode(', '),
            'school' => $invite->colegio?->name,
            'school_code' => $invite->colegio?->invite_code,
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
            ])->values()->all(),
            'guardians' => $guardians->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values()->all(),
            'status' => $guardians->isEmpty() ? 'pendiente' : 'conectado',
        ];
    }

    public function resolve(string $schoolCode, string $inviteCode): ?FamilyInvite
    {
        $schoolCode = InviteCodeHelper::normalize($schoolCode);
        $inviteCode = InviteCodeHelper::normalize($inviteCode);
        if ($inviteCode === '') {
            return null;
        }

        $query = FamilyInvite::query()
            ->with('colegio')
            ->where('invite_code', $inviteCode)
            ->whereNull('revoked_at');

        if ($schoolCode !== '') {
            $colegio = Colegio::query()->where('invite_code', $schoolCode)->first();
            if (! $colegio) {
                return null;
            }
            $query->where('colegio_id', $colegio->id);
        }

        $invite = $query->first();

        return $invite && $invite->isActive() ? $invite : null;
    }

    public function accept(FamilyInvite $invite, string $name, string $email, string $password): User
    {
        if (! $invite->isActive()) {
            throw ValidationException::withMessages([
                'code' => 'Esta invitación ya no es válida. Pide un enlace nuevo al colegio.',
            ]);
        }

        $email = mb_strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Indica un correo válido para crear la cuenta.',
            ]);
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing) {
            if ($existing->role !== 'representante') {
                throw ValidationException::withMessages([
                    'email' => 'Ese correo ya pertenece a otra cuenta. Inicia sesión o usa otro correo.',
                ]);
            }
            if ($existing->colegio_id && (int) $existing->colegio_id !== (int) $invite->colegio_id) {
                throw ValidationException::withMessages([
                    'email' => 'Ese correo ya está vinculado a otro colegio.',
                ]);
            }

            $this->attachUser($existing, $invite);

            return $existing->fresh();
        }

        $this->assertGuardianCapacity($invite);

        return DB::transaction(function () use ($invite, $name, $email, $password) {
            $user = new User();
            $user->forceFill([
                'name' => trim($name) !== '' ? trim($name) : 'Representante',
                'email' => $email,
                'password' => $password,
                'role' => 'representante',
                'colegio_id' => $invite->colegio_id,
                'family_code' => $invite->family_code,
            ])->save();

            DB::table('users')->where('id', $user->id)->update([
                'onboarding_completed' => DatabaseBoolean::bind(true),
            ]);
            $user->refresh();

            $this->attachUser($user, $invite);

            return $user->fresh();
        });
    }

    public function attachUser(User $user, FamilyInvite $invite): int
    {
        if ($user->role !== 'representante') {
            throw ValidationException::withMessages([
                'code' => 'Esta invitación es solo para representantes.',
            ]);
        }

        $this->assertGuardianCapacity($invite, $user);

        $linked = 0;
        foreach ($this->students($invite) as $student) {
            $already = $user->representedStudents()->where('students.id', $student->id)->exists();
            $user->representedStudents()->syncWithoutDetaching([
                $student->id => ['relationship' => 'representante'],
            ]);
            if (! $already) {
                $linked++;
            }
        }

        $user->forceFill([
            'colegio_id' => $invite->colegio_id,
            'family_code' => $invite->family_code,
            'role' => 'representante',
        ])->save();

        if (! $user->onboarding_completed) {
            DB::table('users')->where('id', $user->id)->update([
                'onboarding_completed' => DatabaseBoolean::bind(true),
            ]);
        }

        return $linked;
    }

    /**
     * @return Collection<int, User>
     */
    public function guardians(FamilyInvite $invite): Collection
    {
        $studentIds = $this->students($invite)->pluck('id');
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('role', 'representante')
            ->whereHas('representedStudents', fn ($q) => $q->whereIn('students.id', $studentIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function assertGuardianCapacity(FamilyInvite $invite, ?User $ignore = null): void
    {
        $count = $this->guardians($invite)
            ->when($ignore, fn ($set) => $set->reject(fn (User $user) => (int) $user->id === (int) $ignore->id))
            ->count();

        if ($count >= self::MAX_GUARDIANS) {
            throw ValidationException::withMessages([
                'code' => 'Esta familia ya tiene el máximo de representantes conectados.',
            ]);
        }
    }
}
