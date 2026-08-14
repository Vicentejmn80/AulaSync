<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $fillable = [
        'name',
        'school_name',
        'role',
        'email',
        'phone',
        'school_size',
        'status',
    ];
}
