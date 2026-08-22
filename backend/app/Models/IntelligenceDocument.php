<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceDocument extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FORWARDED = 'forwarded_to_director';

    public const STATUS_FAILED = 'failed';

    public const KIND_PLANIFICACION = 'planificacion';

    public const KIND_LISTA_ALUMNOS = 'lista_alumnos';

    public const KIND_NOTAS = 'notas';

    public const KIND_ASISTENCIA = 'asistencia';

    public const KIND_EVALUACION = 'evaluacion';

    public const KIND_INFORME = 'informe';

    public const KIND_OTRO = 'otro';

    protected $fillable = [
        'teacher_id',
        'course_id',
        'colegio_id',
        'original_name',
        'disk_path',
        'mime_type',
        'size_bytes',
        'kind',
        'status',
        'confidence',
        'extraction',
        'review',
        'error',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'extraction' => 'array',
            'review' => 'array',
            'confidence' => 'float',
            'applied_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public static function kindLabels(): array
    {
        return [
            self::KIND_PLANIFICACION => 'Planificación',
            self::KIND_LISTA_ALUMNOS => 'Lista de alumnos',
            self::KIND_NOTAS => 'Notas / calificaciones',
            self::KIND_ASISTENCIA => 'Asistencia',
            self::KIND_EVALUACION => 'Evaluación',
            self::KIND_INFORME => 'Informe',
            self::KIND_OTRO => 'Otro documento',
        ];
    }

    public function kindLabel(): string
    {
        return self::kindLabels()[$this->kind] ?? 'Documento';
    }
}
