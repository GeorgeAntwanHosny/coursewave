<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutBatch extends Model
{
    public const IN_FLIGHT = ['claimed', 'pending', 'submitted', 'uncertain', 'reconciling'];

    protected $fillable = [
        'instructor_id', 'provider', 'provider_account_id', 'period_start', 'period_end',
        'amount_cents', 'status', 'gateway_idempotency_key', 'provider_transfer_id',
        'failure_reason', 'attempts', 'uncertain_count', 'claimed_at', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'claimed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function attemptLog(): HasMany
    {
        return $this->hasMany(PayoutBatchAttempt::class, 'payout_batch_id')->orderBy('attempt_number');
    }
}
