<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'course_title' => $this->course_title,
            'course_slug' => $this->course?->slug, // null if the course was deleted
            'instructor_name' => $this->instructor_name,
            'issued_at' => $this->issued_at->toIso8601String(),
            'issued_at_human' => $this->issued_at->format('F j, Y'),
            'pdf_ready' => $this->pdf_path !== null,
            'verification_url' => $this->verificationUrl(),
        ];
    }
}
