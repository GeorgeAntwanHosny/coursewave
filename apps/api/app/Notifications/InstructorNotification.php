<?php

namespace App\Notifications;

use App\Filament\Instructor\Pages\CourseCertificates;
use App\Filament\Instructor\Pages\LessonComments;
use App\Filament\Instructor\Pages\PayoutAccount;
use App\Models\Certificate;
use App\Models\Comment;
use App\Models\Lesson;
use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Support\Str;

/** Everything an instructor should hear about, one factory per event. All links open the instructor panel. */
final class InstructorNotification extends AppNotification
{
    public static function lessonComment(Comment $comment, Lesson $lesson, User $author, bool $isReply): self
    {
        return new self(
            'lesson_comment',
            ($isReply ? 'New reply' : 'New comment') . " on {$lesson->title}",
            "{$author->name}: " . Str::limit($comment->body, 120),
            LessonComments::getUrl(panel: 'instructor'),
            'heroicon-o-chat-bubble-left-right',
            'info',
        );
    }

    /** Deliberately generic: the provider's reason is an internal matter and is shown to admins only. */
    public static function payoutFailed(PayoutBatch $batch): self
    {
        return new self(
            'payout_failed',
            "We couldn't send your payout",
            self::usd($batch->amount_cents) . ' could not be sent this time. Your balance is safe and will be included in the next payout run.',
            PayoutAccount::getUrl(panel: 'instructor'),
            'heroicon-o-exclamation-triangle',
            'warning',
        );
    }

    public static function payoutAccountReady(): self
    {
        return new self(
            'payout_account_ready',
            'Payout account ready',
            "You're all set. Your earnings are paid out weekly.",
            PayoutAccount::getUrl(panel: 'instructor'),
            'heroicon-o-check-badge',
            'success',
        );
    }

    public static function payoutAccountNeedsAttention(): self
    {
        return new self(
            'payout_account_attention',
            'Payout account needs attention',
            'We need more information before we can pay you. Open Payouts to continue.',
            PayoutAccount::getUrl(panel: 'instructor'),
            'heroicon-o-exclamation-triangle',
            'warning',
        );
    }

    public static function courseCompleted(Certificate $certificate): self
    {
        return new self(
            'course_completed',
            'A student completed your course',
            "{$certificate->student_name} completed {$certificate->course_title}.",
            CourseCertificates::getUrl(panel: 'instructor'),
            'heroicon-o-academic-cap',
            'success',
        );
    }
}
