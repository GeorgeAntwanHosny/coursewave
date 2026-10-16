<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    protected $fillable = [
        'subscription_id', 'student_id', 'requested_by_id', 'provider', 'provider_payment_id',
        'amount_cents', 'reason', 'status', 'idempotency_key', 'provider_refund_id',
        'failure_reason', 'attempts', 'uncertain_count', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attemptLog(): HasMany
    {
        return $this->hasMany(RefundAttempt::class, 'refund_id')->orderBy('attempt_number');
    }
}
