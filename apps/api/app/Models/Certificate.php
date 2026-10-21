<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'student_id', 'course_id', 'certificate_number', 'student_name', 'course_title',
        'instructor_name', 'issued_at', 'pdf_path', 'pdf_generated_at',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'pdf_generated_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function verificationUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/verify/' . $this->certificate_number;
    }

    /** Accepts lowercase, spaces, missing hyphens: "cw 7k3m 9qwd xt4p" → "CW-7K3M-9QWD-XT4P". */
    public static function normalizeNumber(string $raw): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));

        if (strlen($clean) === 14 && str_starts_with($clean, 'CW')) {
            return 'CW-' . substr($clean, 2, 4) . '-' . substr($clean, 6, 4) . '-' . substr($clean, 10, 4);
        }

        return $clean;
    }
}
