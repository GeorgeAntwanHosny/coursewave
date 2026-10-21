<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'valid' => true,
            'certificate_number' => $this->certificate_number,
            'student_name' => $this->student_name,
            'course_title' => $this->course_title,
            'instructor_name' => $this->instructor_name,
            'issued_at_human' => $this->issued_at->format('F j, Y'),
        ];
    }
}
