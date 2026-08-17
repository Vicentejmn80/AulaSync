<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    protected $fillable = [
        'colegio_id',
        'name',
        'start_date',
        'end_date',
        'report_card_due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date'           => 'date',
            'end_date'             => 'date',
            'report_card_due_date' => 'date',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function allPublished(): bool
    {
        $total     = $this->reportCards()->count();
        $published = $this->reportCards()->where('status', 'published')->count();

        return $total > 0 && $total === $published;
    }
}
