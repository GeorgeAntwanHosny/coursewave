<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'amount_human' => '$' . number_format($this->amount_cents / 100, 2),
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }
}
