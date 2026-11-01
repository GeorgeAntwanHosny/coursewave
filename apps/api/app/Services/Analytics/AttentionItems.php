<?php

namespace App\Services\Analytics;

use App\Filament\Admin\Pages\PayoutBatches;
use App\Filament\Admin\Pages\Refunds;
use App\Filament\Instructor\Pages\LessonComments;
use App\Filament\Instructor\Pages\LiveSessions;
use App\Filament\Instructor\Pages\PayoutAccount;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Turns the attention figures into the list a dashboard shows. Building the list here (not in a Blade view)
 * keeps the wording and the rules testable. A list with nothing wrong always starts with an "all clear" item,
 * so the dashboard is never empty.
 */
final class AttentionItems
{
    /** @return array<int, array{icon:string, color:string, label:string, title:string, detail:string, url:?string, action:?string}> */
    public static function forInstructor(int $instructorId): array
    {
        $data = (new InstructorAnalytics($instructorId))->attention();
        $items = [];

        if ($data['unanswered_comments'] > 0) {
            $items[] = self::item(
                'heroicon-o-chat-bubble-left-right', 'warning', 'Reply needed',
                self::count($data['unanswered_comments'], 'comment') . ' waiting for your reply',
                'Learners are asking questions on your lessons. Turn on "Needs my reply" to see only the ones you have not answered.',
                LessonComments::getUrl(panel: 'instructor'), 'Open comments'
            );
        }

        if ($data['payout_account'] === 'none') {
            $items[] = self::item(
                'heroicon-o-banknotes', 'warning', 'Action needed',
                'Set up your payout account',
                'Your earnings keep accruing, but we cannot pay you until a payout account is connected.',
                PayoutAccount::getUrl(panel: 'instructor'), 'Set up payouts'
            );
        } elseif ($data['payout_account'] === 'incomplete') {
            $items[] = self::item(
                'heroicon-o-banknotes', 'warning', 'Action needed',
                'Finish your payout account',
                'Stripe needs a little more information before we can pay you.',
                PayoutAccount::getUrl(panel: 'instructor'), 'Continue setup'
            );
        }

        if ($data['next_session']) {
            $session = $data['next_session'];
            $startsAt = Carbon::parse($session['starts_at']);

            $items[] = self::item(
                'heroicon-o-video-camera', 'info', 'Coming up',
                'Next live session: ' . $session['title'],
                $session['course_title'] . ' · ' . $startsAt->timezone(config('app.timezone'))->format('D, M j \a\t H:i T') . ' (' . $startsAt->diffForHumans() . ')',
                LiveSessions::getUrl(panel: 'instructor'), 'Manage sessions'
            );
        } elseif ($data['courses_count'] > 0) {
            $items[] = self::item(
                'heroicon-o-video-camera', 'gray', 'Idea',
                'No live sessions scheduled',
                'Scheduling one notifies the learners who are taking your course.',
                LiveSessions::getUrl(panel: 'instructor'), 'Schedule one'
            );
        }

        if ($data['courses_count'] === 0) {
            $items[] = self::item(
                'heroicon-o-academic-cap', 'info', 'Getting started',
                'No courses yet',
                'Create a course from the Courses menu and publish it to start teaching.',
                null, null
            );
        }

        return self::withAllClear(
            $items,
            'Nothing needs your attention',
            'No comments are waiting and your payout account is ready.'
        );
    }

    /** @return array<int, array{icon:string, color:string, label:string, title:string, detail:string, url:?string, action:?string}> */
    public static function forAdmin(): array
    {
        $data = (new PlatformAnalytics)->attention();
        $items = [];

        if ($data['payouts_uncertain'] > 0) {
            $items[] = self::item(
                'heroicon-o-clock', 'warning', 'Needs a check',
                self::count($data['payouts_uncertain'], 'payout') . ' unconfirmed',
                'Stripe did not confirm the outcome. Verify it in Stripe, then reconcile it.',
                PayoutBatches::getUrl(panel: 'admin'), 'Open payouts'
            );
        }

        if ($data['payouts_failed_7d'] > 0) {
            $items[] = self::item(
                'heroicon-o-exclamation-circle', 'danger', 'Failed',
                self::count($data['payouts_failed_7d'], 'payout') . ' failed this week',
                'Declined by Stripe. The amounts stay owed and roll into the next payout run.',
                PayoutBatches::getUrl(panel: 'admin'), 'Open payouts'
            );
        }

        if ($data['refunds_uncertain'] > 0) {
            $items[] = self::item(
                'heroicon-o-clock', 'warning', 'Needs a check',
                self::count($data['refunds_uncertain'], 'refund') . ' unconfirmed',
                'Automatic checks have not confirmed it. Verify it in Stripe before resending.',
                Refunds::getUrl(panel: 'admin'), 'Open refunds'
            );
        }

        if ($data['refunds_failed'] > 0) {
            $items[] = self::item(
                'heroicon-o-exclamation-circle', 'danger', 'Failed',
                self::count($data['refunds_failed'], 'refund') . ' failed',
                'Failed refunds are not retried automatically. Resolve them in the Stripe dashboard.',
                Refunds::getUrl(panel: 'admin'), 'Open refunds'
            );
        }

        if ($data['instructors_unpayable'] > 0) {
            $items[] = self::item(
                'heroicon-o-banknotes', 'warning', 'Heads up',
                self::count($data['instructors_unpayable'], 'instructor') . ' owed money but cannot be paid yet',
                'Each is owed at least ' . Money::usd($data['payout_minimum_cents']) . ' but has no payout account that can receive it. Ask them to finish Payouts setup.',
                null, null
            );
        }

        return self::withAllClear(
            $items,
            'All clear',
            'No stuck payouts or refunds, and every instructor who is owed money can be paid.'
        );
    }

    /** @return array{icon:string, color:string, label:string, title:string, detail:string, url:?string, action:?string} */
    private static function item(string $icon, string $color, string $label, string $title, string $detail, ?string $url, ?string $action): array
    {
        return compact('icon', 'color', 'label', 'title', 'detail', 'url', 'action');
    }

    /** Prepends an "all clear" item when nothing is wrong (nothing warning or danger), so the list is never empty. */
    private static function withAllClear(array $items, string $title, string $detail): array
    {
        $problems = array_filter($items, fn (array $item) => in_array($item['color'], ['warning', 'danger'], true));

        if ($problems === []) {
            array_unshift($items, self::item('heroicon-o-check-circle', 'success', 'All good', $title, $detail, null, null));
        }

        return $items;
    }

    private static function count(int $number, string $noun): string
    {
        return $number . ' ' . $noun . ($number === 1 ? '' : 's');
    }
}
