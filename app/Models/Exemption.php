<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exemption extends Model
{
    protected $fillable = [
        'enrollment_subject_id',
        'exemption_amount',
        'reason',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exemption_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function enrollmentSubject(): BelongsTo
    {
        return $this->belongsTo(EnrollmentSubject::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
