<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorBalance extends Model
{
    protected $primaryKey = 'instructor_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['instructor_id', 'owed_cents', 'paid_cents'];

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function getOutstandingCentsAttribute(): int
    {
        return $this->owed_cents + $this->paid_cents;
    }
}
