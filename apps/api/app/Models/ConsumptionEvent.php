<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionEvent extends Model
{

    protected $fillable = [
        'subscription_id', 'student_id', 'instructor_id', 'course_id', 'lesson_id',
        'lesson_type', 'event_date', 'engagement_seconds', 'completed',
    ];

    protected function casts(): array
    {
        return ['event_date' => 'date', 'completed' => 'boolean'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
