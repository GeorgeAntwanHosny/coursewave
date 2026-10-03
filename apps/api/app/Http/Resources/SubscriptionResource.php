<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'provider' => $this->provider,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'total_amount_cents' => $this->total_amount_cents,
            'term_days' => $this->term_days,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'starts_at_human' => $this->starts_at?->format('F j, Y'),
            'ends_at_human' => $this->ends_at?->format('F j, Y'),
        ];
    }
}
