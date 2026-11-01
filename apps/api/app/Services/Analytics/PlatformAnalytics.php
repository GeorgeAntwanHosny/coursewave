<?php

namespace App\Services\Analytics;

use App\Models\Course;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Platform-wide numbers for admins. Never exposed through the instructor panel. */
class PlatformAnalytics
{
    use CachesAnalytics;

    /**
     * @return array{
     *   gross_sales_cents:int, refunded_cents:int, platform_fee_cents:int, instructor_accrued_cents:int,
     *   paid_out_cents:int, outstanding_owed_cents:int, active_subscriptions:int, new_subscriptions:int,
     *   canceled_subscriptions:int, active_learners:int, engagement_seconds:int, students_total:int,
     *   instructors_total:int, published_courses:int, certificates:int
     * }
     */
    public function overview(AnalyticsRange $range): array
    {
        return $this->remember("platform:overview:{$range->key()}", function () use ($range) {
            $from = $range->from;
            $to = $range->to;

            $ledger = fn (string $type) => (int) DB::table('ledger_entries')
                ->where('entry_type', $type)
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount_cents');

            $paidInPeriod = fn () => Subscription::query()
                ->whereNotNull('starts_at')
                ->whereDate('starts_at', '>=', $from->toDateString())
                ->whereDate('starts_at', '<=', $to->toDateString());

            $events = DB::table('consumption_events')
                ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()]);

            return [
                'gross_sales_cents' => (int) $paidInPeriod()->sum('total_amount_cents'),
                'refunded_cents' => (int) DB::table('refunds')
                    ->where('status', 'succeeded')
                    ->whereBetween('completed_at', [$from, $to])
                    ->sum('amount_cents'),
                'platform_fee_cents' => $ledger('platform_fee'),
                'instructor_accrued_cents' => $ledger('accrual'),
                // Payout entries are debits (negative); show the amount sent as a positive number.
                'paid_out_cents' => abs($ledger('payout')),
                'outstanding_owed_cents' => (int) DB::table('instructor_balances')
                    ->selectRaw('COALESCE(SUM(owed_cents + paid_cents), 0) as outstanding')
                    ->value('outstanding'),
                'active_subscriptions' => Subscription::where('status', 'active')->count(),
                'new_subscriptions' => $paidInPeriod()->count(),
                'canceled_subscriptions' => Subscription::query()
                    ->whereNotNull('canceled_at')
                    ->whereBetween('canceled_at', [$from, $to])
                    ->count(),
                'active_learners' => (int) (clone $events)->distinct()->count('student_id'),
                'engagement_seconds' => (int) (clone $events)->sum('engagement_seconds'),
                'students_total' => User::role('student')->count(),
                'instructors_total' => User::role('instructor')->count(),
                'published_courses' => Course::where('status', 'published')->count(),
                'certificates' => (int) DB::table('certificates')->whereBetween('issued_at', [$from, $to])->count(),
            ];
        });
    }

    /** What an admin has to look at. Current state, not a period, so it is cached only briefly. */
    public function health(): array
    {
        return $this->remember('platform:health', fn () => [
            'payouts_uncertain' => (int) DB::table('payout_batches')->where('status', 'uncertain')->count(),
            'payouts_failed_7d' => (int) DB::table('payout_batches')
                ->where('status', 'failed')
                ->where('completed_at', '>=', now()->subDays(7))
                ->count(),
            'refunds_uncertain' => (int) DB::table('refunds')->where('status', 'uncertain')->count(),
            'refunds_failed' => (int) DB::table('refunds')->where('status', 'failed')->count(),
        ], 60);
    }

    /**
     * Health plus the one condition that isn't a failure yet: instructors who are owed at least the payout
     * minimum but have no payout account that can receive it.
     *
     * @return array{
     *   payouts_uncertain:int, payouts_failed_7d:int, refunds_uncertain:int, refunds_failed:int,
     *   instructors_unpayable:int, payout_minimum_cents:int
     * }
     */
    public function attention(): array
    {
        return $this->remember('platform:attention', function () {
            $minimum = max(1, (int) config('services.payouts.minimum_cents'));

            $unpayable = (int) DB::table('instructor_balances as b')
                ->leftJoin('instructor_payout_accounts as a', 'a.instructor_id', '=', 'b.instructor_id')
                ->whereRaw('b.owed_cents + b.paid_cents >= ?', [$minimum])
                ->where(fn ($q) => $q->whereNull('a.instructor_id')->orWhere('a.payouts_enabled', false))
                ->distinct()
                ->count('b.instructor_id');

            return $this->health() + [
                'instructors_unpayable' => $unpayable,
                'payout_minimum_cents' => $minimum,
            ];
        }, 60);
    }

    /** @return array{new: array<string,int>, canceled: array<string,int>} per-day counts */
    public function subscriptionsSeries(AnalyticsRange $range): array
    {
        return $this->remember("platform:subscriptions:{$range->key()}", function () use ($range) {
            $new = Subscription::query()
                ->whereNotNull('starts_at')
                ->whereDate('starts_at', '>=', $range->from->toDateString())
                ->whereDate('starts_at', '<=', $range->to->toDateString())
                ->selectRaw('DATE(starts_at) as d, COUNT(*) as total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->map(fn ($v) => (int) $v)
                ->all();

            $canceled = Subscription::query()
                ->whereNotNull('canceled_at')
                ->whereBetween('canceled_at', [$range->from, $range->to])
                ->selectRaw('DATE(canceled_at) as d, COUNT(*) as total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->map(fn ($v) => (int) $v)
                ->all();

            return ['new' => $range->fill($new), 'canceled' => $range->fill($canceled)];
        });
    }

    /** @return array{platform: array<string,int>, instructors: array<string,int>} cents per day */
    public function revenueSeries(AnalyticsRange $range): array
    {
        return $this->remember("platform:revenue:{$range->key()}", function () use ($range) {
            $rows = DB::table('ledger_entries')
                ->whereIn('entry_type', ['platform_fee', 'accrual'])
                ->whereBetween('created_at', [$range->from, $range->to])
                ->selectRaw('DATE(created_at) as d, entry_type, SUM(amount_cents) as total')
                ->groupBy('d', 'entry_type')
                ->get();

            $platform = [];
            $instructors = [];

            foreach ($rows as $row) {
                if ($row->entry_type === 'platform_fee') {
                    $platform[$row->d] = (int) $row->total;
                } else {
                    $instructors[$row->d] = (int) $row->total;
                }
            }

            return ['platform' => $range->fill($platform), 'instructors' => $range->fill($instructors)];
        });
    }

    /** @return array<string, int> date => seconds of engagement across the platform */
    public function engagementSeries(AnalyticsRange $range): array
    {
        return $this->remember("platform:engagement:{$range->key()}", function () use ($range) {
            $rows = DB::table('consumption_events')
                ->whereBetween('event_date', [$range->from->toDateString(), $range->to->toDateString()])
                ->selectRaw('DATE(event_date) as d, SUM(engagement_seconds) as total')
                ->groupBy('d')
                ->pluck('total', 'd')
                ->map(fn ($v) => (int) $v)
                ->all();

            return $range->fill($rows);
        });
    }
}
