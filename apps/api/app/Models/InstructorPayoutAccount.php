<?php

namespace App\Models;

use App\Services\Payments\PayoutAccountRef;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorPayoutAccount extends Model
{
    protected $fillable = [
        'instructor_id', 'provider', 'provider_account_id', 'provider_account_version', 'payouts_enabled',
    ];

    protected function casts(): array
    {
        return ['payouts_enabled' => 'boolean'];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function ref(): PayoutAccountRef
    {
        return new PayoutAccountRef($this->provider_account_id, $this->provider_account_version ?: 'v1');
    }
}
