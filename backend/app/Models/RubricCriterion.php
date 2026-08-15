<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    protected $fillable = [
        'rubric_id',
        'sort_order',
        'name',
        'weight_percentage',
        'descriptors',
    ];

    protected function casts(): array
    {
        return [
            'weight_percentage' => 'float',
            'descriptors' => 'array',
        ];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }
}
