<?php

namespace App\Services\Analytics;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Everything an instructor may see about their own teaching. Every query is scoped to
 * $instructorId at the query level (convention #14): there is no method that takes
 * another instructor's data, so a widget can't leak it by accident.
 */
class InstructorAnalytics
{
    use CachesAnalytics;

    public function __construct(protected int $instructorId) {}

    /** @return int[] */
    public function courseIds(): array
    {
        return Course::where('instructor_id', $this->instructorId)->pluck('id')->all();
    }

    /**
     * @return array{
     *   earned_cents:int, owed_cents:int, paid_out_cents:int, outstanding_cents:int,
     *   active_learners:int, engagement_seconds:int, lessons_completed:int, certificates:int,
     *   new_comments:int, upcoming_live_sessions:int
     * }
     */
    public function overview(AnalyticsRange $range): array
    {
        return $this->remember("instructor:{$this->instructorId}:overview:{$range->key()}", function () use ($range) {
            $courseIds = $this->courseIds();
            $from = $range->from;
            $to = $range->to;

            // Earned = accrual ledger entries in the period. The ledger is the source of truth;
            // the projection below only supplies the all-time balance.
            $earned = (int) DB::table('ledger_entries')
                ->where('instructor_id', $this->instructorId)
                ->where('entry_type', 'accrual')
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount_cents');

            // paid_cents is stored negative (payouts are debits), so outstanding = owed + paid.
            $balance = DB::table('instructor_balances')->where('instructor_id', $this->instructorId)->first();
            $owed = (int) ($balance->owed_cents ?? 0);
            $paidOut = abs((int) ($balance->paid_cents ?? 0));

            $events = DB::table('consumption_events')
                ->where('instructor_id', $this->instructorId)
                ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()]);

            $activeLearners = (int) (clone $events)->distinct()->count('student_id');
            $engagement = (int) (clone $events)->sum('engagement_seconds');

            $lessonsCompleted = $courseIds === [] ? 0 : (int) DB::table('lesson_progress')
                ->whereIn('course_id', $courseIds)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$from, $to])
                ->count();

            $certificates = $courseIds === [] ? 0 : (int) DB::table('certificates')
                ->whereIn('course_id', $courseIds)
                ->whereBetween('issued_at', [$from, $to])
                ->count();

            $upcomingSessions = $courseIds === [] ? 0 : (int) DB::table('live_sessions')
                ->whereIn('course_id', $courseIds)
                ->where('status', 'scheduled')
                ->where('ends_at', '>', now())
                ->count();

            return [
                'earned_cents' => $earned,
                'owed_cents' => $owed,
                'paid_out_cents' => $paidOut,
                'outstanding_cents' => $owed - $paidOut,
                'active_learners' => $activeLearners,
                'engagement_seconds' => $engagement,
                'lessons_completed' => $lessonsCompleted,
                'certificates' => $certificates,
                'new_comments' => $this->newComments($courseIds, $range),
                'upcoming_live_sessions' => $upcomingSessions,
            ];
        });
    }

    /**
     * What this instructor should look at now. Current state (not a period), so it is cached only briefly.
     *
     * @return array{
     *   courses_count:int, unanswered_comments:int, payout_account:string,
     *   next_session: array{title:string, course_title:string, starts_at:string}|null
     * }
     */
    public function attention(): array
    {
        return $this->remember("instructor:{$this->instructorId}:attention", function () {
            $courseIds = $this->courseIds();

            $account = DB::table('instructor_payout_accounts')->where('instructor_id', $this->instructorId)->first();

            $next = $courseIds === [] ? null : DB::table('live_sessions')
                ->join('courses', 'courses.id', '=', 'live_sessions.course_id')
                ->whereIn('live_sessions.course_id', $courseIds)
                ->where('live_sessions.status', 'scheduled')
                ->where('live_sessions.ends_at', '>', now())
                ->orderBy('live_sessions.starts_at')
                ->first(['live_sessions.title', 'live_sessions.starts_at', 'courses.title as course_title']);

            return [
                'courses_count' => count($courseIds),
                'unanswered_comments' => $this->unansweredComments($courseIds),
                // none = no account row yet, incomplete = onboarding unfinished, ready = payouts enabled
                'payout_account' => ! $account ? 'none' : ($account->payouts_enabled ? 'ready' : 'incomplete'),
                'next_session' => $next ? [
                    'title' => (string) $next->title,
                    'course_title' => (string) $next->course_title,
                    'starts_at' => (string) $next->starts_at,
                ] : null,
            ];
        }, 60);
    }

    /** @return array<string, int> date => cents earned that day */
    public function earningsSeries(AnalyticsRange $range): array
    {
        return $this->remember("instructor:{$this->instructorId}:earnings:{$range->key()}", function () use ($range) {
            $rows = DB::table('ledger_entries')
                ->where('instructor_id', $this->instructorId)
                ->where('entry_type', 'accrual')
                ->whereBetween('created_at', [$range->from, $range->to])
                ->selectRaw('DATE(created_at) as d, SUM(amount_cents) as total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->map(fn ($value) => (int) $value)
                ->all();

            return $range->fill($rows);
        });
    }

    /** @return array<string, int> date => seconds of engagement that day */
    public function engagementSeries(AnalyticsRange $range): array
    {
        return $this->remember("instructor:{$this->instructorId}:engagement:{$range->key()}", function () use ($range) {
            $rows = DB::table('consumption_events')
                ->where('instructor_id', $this->instructorId)
                ->whereBetween('event_date', [$range->from->toDateString(), $range->to->toDateString()])
                ->selectRaw('DATE(event_date) as d, SUM(engagement_seconds) as total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->map(fn ($value) => (int) $value)
                ->all();

            return $range->fill($rows);
        });
    }

    /** Comments by other people on this instructor's lessons. The instructor's own and deleted ones don't count. */
    protected function newComments(array $courseIds, AnalyticsRange $range): int
    {
        if ($courseIds === []) {
            return 0;
        }

        return (int) DB::table('comments')
            ->where('commentable_type', (new Lesson)->getMorphClass())
            ->whereIn('commentable_id', $this->lessonIdsQuery($courseIds))
            ->whereNull('deleted_at')
            ->where('user_id', '!=', $this->instructorId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->count();
    }

    /**
     * Top-level comments by others on this instructor's lessons that the instructor has not replied to.
     * Deleted comments and deleted replies don't count; a reply from another student does not "answer" a comment.
     */
    protected function unansweredComments(array $courseIds): int
    {
        if ($courseIds === []) {
            return 0;
        }

        return (int) DB::table('comments as c')
            ->where('c.commentable_type', (new Lesson)->getMorphClass())
            ->whereIn('c.commentable_id', $this->lessonIdsQuery($courseIds))
            ->whereNull('c.parent_id')
            ->whereNull('c.deleted_at')
            ->where('c.user_id', '!=', $this->instructorId)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('comments as r')
                    ->whereColumn('r.parent_id', 'c.id')
                    ->where('r.user_id', $this->instructorId)
                    ->whereNull('r.deleted_at');
            })
            ->count();
    }

    protected function lessonIdsQuery(array $courseIds)
    {
        return DB::table('lessons')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->whereIn('modules.course_id', $courseIds)
            ->select('lessons.id');
    }
}
