<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueRecognitionSlice extends Model
{
    protected $fillable = ['subscription_id', 'slice_date', 'amount_cents', 'status', 'recognized_at'];

    protected function casts(): array
    {
        return ['slice_date' => 'date', 'recognized_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
