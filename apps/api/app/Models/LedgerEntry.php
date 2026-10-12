<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    // created_at is the deterministic economic date (partition key), set
    // explicitly — never by Eloquent. recorded_at is true insert time.
    public $timestamps = false;

    protected $fillable = [
        'instructor_id', 'entry_type', 'amount_cents', 'subscription_id',
        'recognition_slice_id', 'payout_batch_id', 'idempotency_key', 'created_at', 'recorded_at',
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

    public function payoutBatch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }
}
