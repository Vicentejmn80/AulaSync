<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;

class PersonNameMatcher
{
    /**
     * Resuelve un profesor activo dentro de un único colegio.
     */
    public function resolveTeacher(int $colegioId, string $name): PersonNameMatch
    {
        $candidates = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->get(['id', 'name'])
            ->map(fn (User $u) => [
                'model' => $u,
                'type' => 'teacher',
                'id' => $u->id,
                'name' => $u->name,
                'code' => null,
            ])
            ->all();

        return $this->match($name, $candidates, 'profesor');
    }

    /**
     * Resuelve un alumno dentro de un único colegio.
     */
    public function resolveStudent(int $colegioId, string $name): PersonNameMatch
    {
        $candidates = Student::query()
            ->where('colegio_id', $colegioId)
            ->get(['id', 'name', 'family_code'])
            ->map(fn (Student $s) => [
                'model' => $s,
                'type' => 'student',
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->family_code,
            ])
            ->all();

        return $this->match($name, $candidates, 'alumno');
    }

    /**
     * Resuelve un profesor registrado o una invitación DOC- pendiente dentro de un colegio.
     */
    public function resolveTeacherOrInvite(int $colegioId, string $name): PersonNameMatch
    {
        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->get(['id', 'name'])
            ->map(fn (User $u) => [
                'model' => $u,
                'type' => 'teacher',
                'id' => $u->id,
                'name' => $u->name,
                'code' => null,
            ]);

        $invites = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get(['id', 'name', 'invite_code'])
            ->map(fn (TeacherInvite $i) => [
                'model' => $i,
                'type' => 'invite',
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->invite_code,
            ]);

        return $this->match($name, array_merge($teachers->all(), $invites->all()), 'profesor o invitación');
    }

    /**
     * Resuelve una invitación DOC- por nombre dentro de un colegio.
     */
    public function resolveInvite(int $colegioId, string $name): PersonNameMatch
    {
        $candidates = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get(['id', 'name', 'invite_code'])
            ->map(fn (TeacherInvite $i) => [
                'model' => $i,
                'type' => 'invite',
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->invite_code,
            ])
            ->all();

        return $this->match($name, $candidates, 'invitación DOC-');
    }

    /**
     * @param  array<int,array{model:object,type:string,id:int,name:string,code:?string}>  $candidates
     */
    private function match(string $name, array $candidates, string $label): PersonNameMatch
    {
        $needle = $this->normalize($name);

        if ($needle === '') {
            return new PersonNameMatch('none', message: "Indica el nombre del {$label}.");
        }

        $exact = [];
        $prefix = [];
        $word = [];
        $contains = [];

        foreach ($candidates as $candidate) {
            $norm = $this->normalize($candidate['name']);

            if ($norm === $needle) {
                $exact[] = $candidate;

                continue;
            }

            if (str_starts_with($norm, $needle.' ')) {
                $prefix[] = $candidate;

                continue;
            }

            if ($this->containsWord($norm, $needle)) {
                $word[] = $candidate;

                continue;
            }

            if (str_contains($norm, $needle)) {
                $contains[] = $candidate;
            }
        }

        foreach (['exact' => $exact, 'prefix' => $prefix, 'word' => $word, 'contains' => $contains] as $tier => $matches) {
            if ($matches === []) {
                continue;
            }

            if (count($matches) === 1) {
                $winner = $matches[0];

                return new PersonNameMatch(
                    'unique',
                    $winner['model'],
                    $this->label($winner),
                    [$this->candidatePayload($winner)],
                );
            }

            return new PersonNameMatch(
                'ambiguous',
                message: $this->ambiguousMessage($name, $matches, $label),
                candidates: array_map(fn ($m) => $this->candidatePayload($m), $matches),
            );
        }

        return new PersonNameMatch(
            'none',
            message: "No encontré a \"{$name}\" en este colegio.",
        );
    }

    /**
     * @param  array{model:object,type:string,id:int,name:string,code:?string}  $candidate
     */
    private function candidatePayload(array $candidate): array
    {
        return [
            'id' => $candidate['id'],
            'type' => $candidate['type'],
            'name' => $candidate['name'],
            'code' => $candidate['code'],
        ];
    }

    /**
     * @param  array{model:object,type:string,id:int,name:string,code:?string}  $candidate
     */
    private function label(array $candidate): string
    {
        if ($candidate['type'] === 'invite' && ! empty($candidate['code'])) {
            return "{$candidate['name']} ({$candidate['code']}, pendiente)";
        }

        return $candidate['name'];
    }

    /**
     * @param  array<int,array{model:object,type:string,id:int,name:string,code:?string}>  $matches
     */
    private function ambiguousMessage(string $name, array $matches, string $label): string
    {
        $lines = collect($matches)
            ->map(function ($m) {
                $line = "- {$m['name']}";
                if ($m['type'] === 'invite' && ! empty($m['code'])) {
                    $line .= " (código {$m['code']})";
                } elseif ($m['type'] === 'student' && ! empty($m['code'])) {
                    $line .= " (código familiar {$m['code']})";
                }

                return $line;
            })
            ->implode("\n");

        $suggestion = $label === 'alumno'
            ? 'Indica el nombre completo o el código familiar NV-.'
            : 'Indica el nombre completo o el código DOC-.';

        return "Encontré varias coincidencias para \"{$name}\":\n{$lines}\n{$suggestion}";
    }

    private function containsWord(string $normalizedName, string $normalizedNeedle): bool
    {
        return $normalizedName === $normalizedNeedle
            || str_starts_with($normalizedName, $normalizedNeedle.' ')
            || str_ends_with($normalizedName, ' '.$normalizedNeedle)
            || str_contains($normalizedName, ' '.$normalizedNeedle.' ');
    }

    private function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $value = preg_replace('/[%_]/u', '', $value) ?? $value;

        return trim($value);
    }
}
