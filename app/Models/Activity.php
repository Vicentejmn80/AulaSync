<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    public const TYPE_CLASE = 'clase';
    public const TYPE_ACTIVIDAD = 'actividad';
    public const TYPE_TAREA = 'tarea';

    protected $fillable = [
        'teacher_id',
        'course_id',
        'plan_block_id',
        'title',
        'description',
        'max_score',
        'weight_percentage',
        'due_date',
        'type',
        'is_homework',
        'nee_type',
        'nee_adaptation',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'max_score' => 'integer',
            'weight_percentage' => 'float',
            'is_homework' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $activity): void {
            $typeMeta = self::normalizeType(
                (string) ($activity->type ?? ''),
                filter_var($activity->is_homework ?? false, FILTER_VALIDATE_BOOLEAN)
            );

            $activity->type = $typeMeta['type'];
            $activity->is_homework = $typeMeta['is_homework'];
        });
    }

    public function setTypeAttribute($value): void
    {
        $typeMeta = self::normalizeType(
            (string) $value,
            filter_var($this->attributes['is_homework'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        $this->attributes['type'] = $typeMeta['type'];
        $this->attributes['is_homework'] = $typeMeta['is_homework'] ? 1 : 0;
    }

    public function setIsHomeworkAttribute($value): void
    {
        $incomingIsHomework = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $typeMeta = self::normalizeType(
            (string) ($this->attributes['type'] ?? ''),
            $incomingIsHomework
        );

        $this->attributes['type'] = $typeMeta['type'];
        $this->attributes['is_homework'] = $typeMeta['is_homework'] ? 1 : 0;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'actividad_id');
    }

    /**
     * Normaliza tipos libres (incluyendo salida de IA) a valores válidos de BD.
     * "tarea" se persiste como tipo propio y siempre implica is_homework=true.
     *
     * @return array{type:string,is_homework:bool,semantic_type:string}
     */
    public static function normalizeType(?string $rawType, bool $isHomework = false): array
    {
        $value = mb_strtolower(trim((string) $rawType));

        if (in_array($value, ['tarea', 'homework', 'assignment', 'asignacion'], true)) {
            return [
                'type' => self::TYPE_TAREA,
                'is_homework' => true,
                'semantic_type' => self::TYPE_TAREA,
            ];
        }

        if (in_array($value, ['clase', 'class', 'lesson', 'leccion'], true)) {
            return [
                'type' => self::TYPE_CLASE,
                'is_homework' => $isHomework,
                'semantic_type' => self::TYPE_CLASE,
            ];
        }

        if (in_array($value, ['actividad', 'activity', 'evaluacion', 'evaluation', 'quiz', 'practica'], true)) {
            return [
                'type' => $isHomework ? self::TYPE_TAREA : self::TYPE_ACTIVIDAD,
                'is_homework' => $isHomework,
                'semantic_type' => $isHomework ? self::TYPE_TAREA : self::TYPE_ACTIVIDAD,
            ];
        }

        if ($isHomework) {
            return [
                'type' => self::TYPE_TAREA,
                'is_homework' => true,
                'semantic_type' => self::TYPE_TAREA,
            ];
        }

        return [
            'type' => self::TYPE_ACTIVIDAD,
            'is_homework' => false,
            'semantic_type' => self::TYPE_ACTIVIDAD,
        ];
    }
}
