<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    // created_at represents the economic/event date (deterministic, used as
    // the partition key), not Eloquent's usual "row touched" semantics —
    // timestamps are set explicitly by the recognition action, never by
    // Eloquent's own touch(). recorded_at is the true wall-clock audit field.
    public $timestamps = false;

    protected $fillable = [
        'instructor_id', 'entry_type', 'amount_cents', 'subscription_id',
        'recognition_slice_id', 'idempotency_key', 'created_at', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'recorded_at' => 'datetime'];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function recognitionSlice(): BelongsTo
    {
        return $this->belongsTo(RevenueRecognitionSlice::class, 'recognition_slice_id');
    }
}
