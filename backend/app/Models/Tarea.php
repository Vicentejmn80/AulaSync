<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'actividad_id',
        'colegio_id',
        'titulo',
        'descripcion',
        'fecha_entrega',
        'puntos',
        'calificacion',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date:Y-m-d',
            'puntos' => 'integer',
            'calificacion' => 'float',
        ];
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'actividad_id');
    }
}
