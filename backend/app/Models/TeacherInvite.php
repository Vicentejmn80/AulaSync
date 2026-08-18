<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherInvite extends Model
{
    protected $fillable = [
        'colegio_id',
        'created_by',
        'name',
        'email',
        'invite_code',
        'course_ids',
        'subject_name',
        'grade',
        'section',
        'claimed_by',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'course_ids' => 'array',
            'claimed_at' => 'datetime',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_invite_id');
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null || $this->claimed_by !== null;
    }

    public function claimFor(User $user): void
    {
        if ($this->isClaimed() && (int) $this->claimed_by !== (int) $user->id) {
            return;
        }

        $user->update([
            'role' => 'profesor',
            'colegio_id' => $this->colegio_id,
        ]);

        $assignedIds = collect($this->course_ids ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $linked = Course::query()
            ->where('colegio_id', $this->colegio_id)
            ->where(function ($query) use ($assignedIds) {
                $query->where('teacher_invite_id', $this->id);
                if ($assignedIds->isNotEmpty()) {
                    $query->orWhereIn('id', $assignedIds->all());
                }
            })
            ->get();

        foreach ($linked as $course) {
            $course->teacher_id = $user->id;
            $course->teacher_invite_id = $this->id;
            $course->save();
        }

        if ($this->subject_name && $this->grade) {
            $exists = Course::query()
                ->where('colegio_id', $this->colegio_id)
                ->where(function ($query) use ($user) {
                    $query->where('teacher_id', $user->id)
                        ->orWhere('teacher_invite_id', $this->id);
                })
                ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($this->subject_name)])
                ->whereRaw('LOWER(COALESCE(grade, ?)) = ?', ['', mb_strtolower($this->grade)])
                ->when(
                    $this->section,
                    fn ($q) => $q->whereRaw('LOWER(COALESCE(section, ?)) = ?', ['', mb_strtolower($this->section)])
                )
                ->exists();

            if (! $exists) {
                Course::create([
                    'teacher_id' => $user->id,
                    'teacher_invite_id' => $this->id,
                    'colegio_id' => $this->colegio_id,
                    'subject_name' => $this->subject_name,
                    'grade' => $this->grade,
                    'section' => $this->section,
                    'school_year' => date('Y').'-'.(date('Y') + 1),
                    'invite_code' => \App\Helpers\InviteCodeHelper::generateCourseCode(
                        $this->subject_name,
                        $this->grade,
                        $this->section
                    ),
                ]);
            }
        }

        $finalIds = Course::query()
            ->where('colegio_id', $this->colegio_id)
            ->where(function ($query) use ($user, $assignedIds) {
                $query->where('teacher_invite_id', $this->id)
                    ->orWhere('teacher_id', $user->id);
                if ($assignedIds->isNotEmpty()) {
                    $query->orWhereIn('id', $assignedIds->all());
                }
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if (! empty($finalIds)) {
            Course::whereIn('id', $finalIds)->update([
                'teacher_id' => $user->id,
                'teacher_invite_id' => $this->id,
            ]);
        }

        $this->forceFill([
            'claimed_by' => $user->id,
            'claimed_at' => now(),
            'course_ids' => ! empty($finalIds) ? $finalIds : ($this->course_ids ?: null),
        ])->save();
    }
}
