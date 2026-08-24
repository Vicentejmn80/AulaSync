<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $fillable = [
        'name',
        'last_name',
        'school_name',
        'role',
        'email',
        'phone',
        'school_size',
        'estado_region',
        'status',
    ];
}
