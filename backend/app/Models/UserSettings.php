<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    protected $table = 'user_settings';

    protected $attributes = [
        'lesson_template' => 'clasica',
    ];

    protected $fillable = [
        'user_id',
        'materias',
        'materias_asignadas',
        'dias_clase',
        'nivel_educativo',
        'cursos_grados',
        'estilo_pedagogico',
        'nombre_institucion',
        'modelo_pedagogico',
        'tono',
        'clases_semana',
        'duracion_clase_min',
        'preferencias',
        'lesson_template',
    ];

    protected function casts(): array
    {
        return [
            'materias' => 'array',
            'materias_asignadas' => 'array',
            'dias_clase' => 'array',
            'cursos_grados' => 'array',
            'preferencias' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(function (UserSettings $settings) {
            if (blank($settings->lesson_template)) {
                $settings->lesson_template = \App\Support\LessonTemplate::CLASSIC;
            }
        });
    }

    public function getMateriasListAttribute(): string
    {
        if (! is_array($this->materias) || empty($this->materias)) {
            return '';
        }
        $labels = [
            'matematicas' => 'Matemáticas', 'ciencias' => 'Ciencias', 'lenguaje' => 'Lenguaje',
            'historia' => 'Historia', 'ingles' => 'Inglés', 'arte' => 'Arte', 'musica' => 'Música',
            'educacion_fisica' => 'Educación Física', 'tecnologia' => 'Tecnología', 'filosofia' => 'Filosofía',
        ];
        return implode(', ', array_map(fn ($k) => $labels[$k] ?? $k, $this->materias));
    }

    public function getDiaHoyAttribute(): string
    {
        $enToEs = [
            'Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado',
        ];
        return $enToEs[date('l')] ?? 'hoy';
    }
}
