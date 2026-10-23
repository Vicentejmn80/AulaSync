<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienciaLead extends Model
{
    protected $fillable = [
        'spin_id',
        'tablet_id',
        'rol',
        'nombre',
        'apellido',
        'email',
        'colegio',
        'cargo',
        'telefono',
        'problema',
        'premio_id',
        'premio_nombre',
        'premio_condicion',
        'codigo',
        'puntaje',
        'consentimiento',
        'email_status',
        'telegram_status',
        'webhook_status',
    ];
}
