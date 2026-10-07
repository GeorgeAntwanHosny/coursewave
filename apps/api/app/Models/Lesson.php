<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'title', 'type', 'video_url', 'video_duration_seconds', 'body', 'order'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function resourceLinks(): HasMany
    {
        return $this->hasMany(ResourceLink::class);
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function consumptionEvents(): HasMany
    {
        return $this->hasMany(ConsumptionEvent::class);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
