<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'billing_interval' => $this->billing_interval,
            'term_days' => $this->term_days,
            'price_cents' => $this->price_cents,
        ];
    }
}
