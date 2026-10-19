<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'commentable_type', 'commentable_id', 'user_id', 'parent_id', 'body', 'edited_at',
    ];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    /** Query scope (convention #14): every read of a lesson's thread goes through this. */
    public function scopeForLesson(Builder $query, Lesson $lesson): Builder
    {
        return $query->where('commentable_type', $lesson->getMorphClass())
            ->where('commentable_id', $lesson->id);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
