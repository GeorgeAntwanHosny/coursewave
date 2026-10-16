<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundAttempt extends Model
{
    // Append-only audit log: rows are only ever inserted.
    public $timestamps = false;

    protected $fillable = [
        'refund_id', 'attempt_number', 'operation', 'outcome', 'status_before', 'status_after',
        'provider', 'idempotency_key', 'amount_cents', 'provider_refund_id', 'http_status',
        'provider_request_id', 'error_code', 'error_message', 'response', 'duration_ms',
    ];

    protected function casts(): array
    {
        return ['response' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('refund_attempts is append-only.'));
        static::deleting(fn () => throw new \LogicException('refund_attempts is append-only.'));
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }
}
