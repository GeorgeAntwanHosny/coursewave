<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'plan_id', 'status', 'provider', 'provider_checkout_id', 'provider_payment_id',
        'total_amount_cents', 'term_days', 'starts_at', 'ends_at', 'canceled_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'canceled_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function recognitionSlices(): HasMany
    {
        return $this->hasMany(RevenueRecognitionSlice::class);
    }
}
