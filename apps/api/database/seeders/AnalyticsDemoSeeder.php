<?php

namespace Database\Seeders;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\FinalizePayoutBatchAction;
use App\Actions\Billing\FinalizeRefundAction;
use App\Actions\Billing\GenerateRecognitionSlicesAction;
use App\Actions\Learning\IssueCertificateAction;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LiveSession;
use App\Models\PayoutBatch;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\RevenueRecognitionSlice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * LOCAL/TESTING ONLY. Fills the analytics dashboards (instructor@llm.com, admin@llm.com and the other
 * seeded instructors) with ~95 days of realistic activity in every state: active, canceled, refunded,
 * failed and unconfirmed payouts and refunds, certificates, comments and live sessions.
 *
 * Run on its own, after the normal seeders:  php artisan db:seed --class=AnalyticsDemoSeeder
 *
 * It uses the real Cancel / Finalize / Certificate actions wherever they exist. Revenue recognition is
 * re-implemented here with the same ledger shape and idempotency keys (and the projection moves only when a
 * row was really inserted). Notifications and queued jobs are faked, so nothing is sent and no provider is called.
 */
class AnalyticsDemoSeeder extends Seeder
{
    private const HISTORY_DAYS = 95;
    private const MARKER_EMAIL = 'demo.student01@llm.com';

    private const NAMES = [
        'Amira Hassan', 'Omar Farouk', 'Layla Mansour', 'Youssef Adel', 'Nour El-Din', 'Salma Khaled',
        'Karim Nabil', 'Hana Mostafa', 'Tarek Samir', 'Mariam Fathy', 'Ziad Ashraf', 'Dina Ibrahim',
        'Hossam Gamal', 'Rania Said', 'Mahmoud Reda', 'Farah Emad', 'Ali Tawfik', 'Yasmin Sherif',
        'Khaled Hany', 'Nadine Wael', 'Mostafa Ehab', 'Sara Magdy', 'Ahmed Lotfy', 'Heba Ramzy',
        'Mohamed Zaki', 'Lina Osama', 'Adham Rashad', 'Jana Mourad', 'Seif Anwar', 'Malak Essam',
        'Fady Nagy', 'Reem Hatem', 'Bassem Yehia', 'Noha Selim', 'Walid Hegazy', 'Aya Fouad',
        'Sherif Badr', 'Mona Galal', 'Hady Salah', 'Dalia Younes',
    ];

    private Carbon $today;
    private int $feeBps = 2000;
    private ?User $instructor = null;
    private ?User $admin = null;
    private Collection $courses;
    private Collection $ownCourses;

    /** @var array<int, Collection> */
    private array $lessonsByCourse = [];

    /** @var array<int, array<int, bool>> student id => lesson id => already has progress */
    private array $done = [];

    private array $stats = [
        'subscriptions' => 0, 'events' => 0, 'ledger' => 0, 'certificates' => 0,
        'refunds' => 0, 'payouts' => 0, 'comments' => 0, 'sessions' => 0,
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('AnalyticsDemoSeeder only runs when APP_ENV is local or testing.');

            return;
        }

        if (User::where('email', self::MARKER_EMAIL)->exists()) {
            $this->command?->info('Analytics demo data is already seeded (demo.student01@llm.com exists). Nothing to do.');

            return;
        }

        $this->instructor = User::where('email', 'instructor@llm.com')->first();
        $this->admin = User::where('email', 'admin@llm.com')->first();
        $this->courses = Course::where('status', 'published')->orderBy('id')->get();
        $plans = Plan::all()->keyBy('slug');

        if (! $this->instructor || $this->courses->isEmpty() || ! $plans->has('monthly') || ! $plans->has('quarterly') || ! $plans->has('annual')) {
            $this->command?->warn('Run the normal seeders first (roles, plans, users, course content), then this one.');

            return;
        }

        $this->ownCourses = $this->courses->where('instructor_id', $this->instructor->id)->values();

        if ($this->ownCourses->isEmpty()) {
            $this->command?->warn('instructor@llm.com has no published courses. Run CourseContentSeeder first.');

            return;
        }

        $this->today = now()->startOfDay();
        $rawFee = (float) env('PLATFORM_FEE_PERCENT', 20);
        $this->feeBps = (int) round(($rawFee > 1 ? $rawFee / 100 : $rawFee) * 10000);

        // Nothing seeded here may notify anyone or call a payment provider.
        Notification::fake();
        Queue::fake();
        mt_srand(20261027);

        $from = $this->today->copy()->subDays(self::HISTORY_DAYS);
        $this->ensureMonthlyPartitions('ledger_entries', $from, $this->today);
        $this->ensureMonthlyPartitions('consumption_events', $from, $this->today);

        try {
            $students = $this->createStudents();
            $this->runJourneys($students, $plans);
            $this->seedPayouts();
            $this->seedComments($students);
            $this->seedLiveSessions();
        } finally {
            Carbon::setTestNow();
        }

        try {
            Cache::flush(); // so the dashboards don't serve cached zeros
        } catch (\Throwable) {
        }

        $this->summary();
    }

    // ------------------------------------------------------------------ partitions

    /** History reaches back into months that may have no partition yet; create the missing ones. */
    protected function ensureMonthlyPartitions(string $table, Carbon $from, Carbon $to): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $partitioned = DB::selectOne('select 1 as p from pg_partitioned_table where partrelid = ?::regclass', [$table]);

        if (! $partitioned) {
            return;
        }

        $bounds = collect(DB::select(
            'select pg_get_expr(c.relpartbound, c.oid) as b from pg_inherits i join pg_class c on c.oid = i.inhrelid where i.inhparent = ?::regclass',
            [$table]
        ))->pluck('b')->implode(' ');

        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $start = $month->format('Y-m-d');

            if (str_contains($bounds, "'{$start}")) {
                continue; // already covered
            }

            $next = $month->copy()->addMonth()->format('Y-m-d');
            $name = "{$table}_demo_" . $month->format('Ym');

            try {
                DB::statement("CREATE TABLE IF NOT EXISTS {$name} PARTITION OF {$table} FOR VALUES FROM ('{$start}') TO ('{$next}')");
            } catch (\Throwable $e) {
                $this->command?->warn("Could not create a {$table} partition for {$start}: " . $e->getMessage());
            }
        }
    }

    // ------------------------------------------------------------------ students and journeys

    /** @return array<int, User> */
    protected function createStudents(): array
    {
        $password = Hash::make('Password123');
        $students = [];

        foreach (self::NAMES as $i => $name) {
            $n = $i + 1;
            $student = User::create([
                'name' => $name,
                'email' => sprintf('demo.student%02d@llm.com', $n),
                'password' => $password,
            ]);
            $student->assignRole('student');
            $students[$n] = $student;
        }

        return $students;
    }

    /** @return array<int, array<string, mixed>> one profile per demo student, numbered 1–40 */
    protected function profiles(): array
    {
        $profiles = [];

        for ($i = 1; $i <= 12; $i++) {
            $profiles[$i] = ['plan' => 'monthly', 'start' => mt_rand(3, 25)];
        }
        for ($i = 13; $i <= 20; $i++) {
            $profiles[$i] = ['plan' => 'quarterly', 'start' => mt_rand(15, 80)];
        }
        for ($i = 21; $i <= 26; $i++) {
            $profiles[$i] = ['plan' => 'annual', 'start' => mt_rand(10, 85)];
        }

        // Canceled mid-term, with a refund in each state.
        $refundOutcomes = [27 => 'succeeded', 28 => 'succeeded', 29 => 'succeeded', 30 => 'succeeded', 31 => 'succeeded',
            32 => 'failed', 33 => 'uncertain', 34 => 'pending'];

        foreach ($refundOutcomes as $i => $outcome) {
            $start = $i === 34 ? 6 : mt_rand(10, 45);
            $profiles[$i] = [
                'plan' => $i % 2 ? 'monthly' : 'quarterly',
                'start' => $start,
                'cancel_after' => $i === 34 ? 5 : mt_rand(2, min(9, $start - 2)),
                'outcome' => $outcome,
                'reason' => $i === 28 ? 'admin_cancel' : 'student_cancel',
            ];
        }

        // The term had already ended when they canceled, so there is nothing to refund.
        foreach ([35, 36] as $i) {
            $profiles[$i] = ['plan' => 'monthly', 'start' => mt_rand(42, 60), 'cancel_after' => 33, 'outcome' => null, 'reason' => 'student_cancel'];
        }

        // Plan switches: the monthly plan is canceled (refund for the unused days) and a longer plan starts.
        foreach ([37, 38, 39, 40] as $i) {
            $profiles[$i] = ['plan' => 'monthly', 'start' => mt_rand(45, 65), 'switch_after' => mt_rand(10, 20), 'switch_to' => $i % 2 ? 'annual' : 'quarterly'];
        }

        return $profiles;
    }

    protected function runJourneys(array $students, Collection $plans): void
    {
        foreach ($this->profiles() as $n => $profile) {
            $student = $students[$n];
            $start = $this->today->copy()->subDays($profile['start']);

            if (isset($profile['switch_after'])) {
                $switchAt = $start->copy()->addDays($profile['switch_after']);

                $first = $this->createSubscription($student, $plans['monthly'], $start);
                $this->simulateLearning($student, $first, $start, $switchAt->copy()->subDay());
                $this->cancel($first, $switchAt, 'plan_switch', 'succeeded');
                $this->recognize($first->fresh());

                $second = $this->createSubscription($student, $plans[$profile['switch_to']], $switchAt);
                $this->simulateLearning($student, $second, $switchAt, $this->today);
                $this->recognize($second->fresh());

                continue;
            }

            $subscription = $this->createSubscription($student, $plans[$profile['plan']], $start);

            if (isset($profile['cancel_after'])) {
                $cancelAt = $start->copy()->addDays($profile['cancel_after']);
                $termEnd = $start->copy()->addDays($subscription->term_days - 1);

                $this->simulateLearning($student, $subscription, $start, $cancelAt->copy()->subDay()->min($termEnd));
                $this->cancel($subscription, $cancelAt, $profile['reason'], $profile['outcome']);
            } else {
                $this->simulateLearning($student, $subscription, $start, $this->today);
            }

            $this->recognize($subscription->fresh());
        }
    }

    protected function createSubscription(User $student, Plan $plan, Carbon $start): Subscription
    {
        Carbon::setTestNow($start->copy()->setTime(10, 0));

        $subscription = Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'term_days' => $plan->term_days,
            'total_amount_cents' => $plan->price_cents,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_demo_' . Str::lower(Str::random(14)),
            'starts_at' => $start->toDateString(),
            'ends_at' => $start->copy()->addDays($plan->term_days)->toDateString(),
            'created_at' => $start->copy()->setTime(10, 0),
            'updated_at' => $start->copy()->setTime(10, 0),
        ]);

        app(GenerateRecognitionSlicesAction::class)->execute($subscription->fresh());
        $this->stats['subscriptions']++;

        return $subscription->fresh();
    }

    /** The real cancel action, run "as of" the cancel date, then the refund is taken to the requested state. */
    protected function cancel(Subscription $subscription, Carbon $cancelAt, string $reason, ?string $outcome): void
    {
        Carbon::setTestNow($cancelAt->copy()->setTime(14, 0));

        $refund = app(CancelSubscriptionAction::class)->execute(
            $subscription->fresh(),
            $reason,
            $reason === 'admin_cancel' ? $this->admin?->id : null
        );

        if (! $refund) {
            return; // nothing unused to refund
        }

        $this->stats['refunds']++;
        $outcome ??= 'succeeded';

        if ($outcome === 'pending') {
            return; // left as the cancel action created it, so the Refunds page shows a pending one
        }

        Refund::whereKey($refund->id)->update(['status' => 'submitted']);
        Carbon::setTestNow($cancelAt->copy()->addDay()->setTime(9, 0));

        $finalize = app(FinalizeRefundAction::class);

        match ($outcome) {
            'succeeded' => $finalize->markSucceeded($refund->id, 're_demo_' . $refund->id),
            'failed' => $finalize->markFailed($refund->id, 'charge_already_refunded'),
            'uncertain' => $finalize->markUncertain($refund->id, 'Timed out waiting for Stripe', false),
        };
    }

    // ------------------------------------------------------------------ learning activity

    protected function simulateLearning(User $student, Subscription $subscription, Carbon $from, Carbon $to): void
    {
        if ($from->gt($to)) {
            return;
        }

        $cursor = $from->copy()->addDays(mt_rand(0, 2));

        foreach ($this->pickCourses() as $course) {
            $lessons = $this->lessonsFor($course->id);

            if ($lessons->isEmpty()) {
                continue;
            }

            $target = $this->targetLessonCount($lessons->count());
            $attempted = 0;

            foreach ($lessons as $lesson) {
                if ($attempted >= $target) {
                    break;
                }

                if (isset($this->done[$student->id][$lesson->id])) {
                    $attempted++;

                    continue;
                }

                if ($cursor->gt($to)) {
                    break 2; // past the window in which this subscription is usable
                }

                // The last lesson of a partial path is left half-watched some of the time.
                $partial = $attempted === $target - 1 && $target < $lessons->count() && mt_rand(1, 100) <= 40;

                $this->recordLesson($student, $subscription, $course, $lesson, $cursor, ! $partial);
                $this->done[$student->id][$lesson->id] = true;
                $attempted++;

                if (mt_rand(0, 1)) {
                    $cursor->addDays(mt_rand(1, 2));
                }
            }

            if ($attempted >= $lessons->count()) {
                Carbon::setTestNow($cursor->copy()->setTime(20, 0));
                $certificate = app(IssueCertificateAction::class)->execute($student, $course);

                if ($certificate?->wasRecentlyCreated) {
                    $this->stats['certificates']++;
                }
            }
        }
    }

    /** @return Course[] one to three courses; about two thirds of students take one of instructor@llm.com's */
    protected function pickCourses(): array
    {
        $pool = $this->courses->values();
        $own = $this->ownCourses->values();
        $want = min(mt_rand(1, 3), $pool->count());
        $picked = [];

        if (mt_rand(1, 100) <= 65) {
            $course = $own[mt_rand(0, $own->count() - 1)];
            $picked[$course->id] = $course;
        }

        while (count($picked) < $want) {
            $course = $pool[mt_rand(0, $pool->count() - 1)];
            $picked[$course->id] = $course;
        }

        return array_values($picked);
    }

    protected function targetLessonCount(int $total): int
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 35 => $total,                                                              // finishes the course
            $roll <= 60 => max(1, (int) round($total * mt_rand(70, 90) / 100)),                  // nearly done
            $roll <= 85 => max(1, (int) round($total * mt_rand(30, 60) / 100)),                  // about half
            default => min($total, mt_rand(1, 2)),                                              // just started
        };
    }

    protected function recordLesson(User $student, Subscription $subscription, Course $course, Lesson $lesson, Carbon $day, bool $completed): void
    {
        $moment = $day->copy()->setTime(mt_rand(8, 22), mt_rand(0, 59));
        Carbon::setTestNow($moment);

        $full = $lesson->type === 'video'
            ? max(30, (int) ($lesson->video_duration_seconds ?: 120))
            : mt_rand(180, 420);
        $seconds = $completed ? $full : max(20, (int) round($full * mt_rand(20, 70) / 100));

        ConsumptionEvent::create([
            'subscription_id' => $subscription->id,
            'student_id' => $student->id,
            'instructor_id' => $course->instructor_id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'lesson_type' => $lesson->type,
            'event_date' => $day->toDateString(),
            'engagement_seconds' => $seconds,
            'completed' => $completed,
        ]);
        $this->stats['events']++;

        LessonProgress::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'status' => $completed ? 'completed' : 'in_progress',
            'watched_seconds' => $lesson->type === 'video' ? $seconds : 0,
            'completed_at' => $completed ? $moment : null,
            'last_engaged_at' => $moment,
        ]);
    }

    protected function lessonsFor(int $courseId): Collection
    {
        return $this->lessonsByCourse[$courseId] ??= Lesson::query()
            ->join('modules', 'lessons.module_id', '=', 'modules.id')
            ->where('modules.course_id', $courseId)
            ->orderBy('modules.order')
            ->orderBy('lessons.order')
            ->select('lessons.*')
            ->get();
    }

    // ------------------------------------------------------------------ revenue recognition

    /**
     * Same rules as the real recognition: only past days, only days with engagement, a day without engagement
     * stays pending and is picked up with the next active day, the platform fee comes off the top, and the pool
     * is split by watched seconds with the largest-remainder method. Entries use the real idempotency-key shape
     * and carry the economic date as created_at; the projection moves only for rows that were really inserted.
     */
    protected function recognize(Subscription $subscription): void
    {
        $byDay = [];

        DB::table('consumption_events')
            ->where('subscription_id', $subscription->id)
            ->selectRaw('event_date, instructor_id, SUM(engagement_seconds) as seconds')
            ->groupBy('event_date', 'instructor_id')
            ->get()
            ->each(function ($row) use (&$byDay) {
                $byDay[substr((string) $row->event_date, 0, 10)][(int) $row->instructor_id] = (int) $row->seconds;
            });

        $slices = RevenueRecognitionSlice::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->where('slice_date', '<', $this->today->toDateString())
            ->orderBy('slice_date')
            ->get();

        $backlog = collect();
        $owed = [];

        foreach ($slices as $slice) {
            $day = Carbon::parse($slice->slice_date)->toDateString();
            $backlog->push($slice);

            $weights = $byDay[$day] ?? null;

            if (! $weights || array_sum($weights) <= 0) {
                continue; // nobody engaged that day: the slice stays pending
            }

            $amount = (int) $backlog->sum('amount_cents');
            $fee = intdiv($amount * $this->feeBps, 10000);
            $createdAt = Carbon::parse($day)->endOfDay();

            if ($fee > 0) {
                $this->ledger(null, 'platform_fee', $fee, $subscription->id, $slice->id, "platform_fee:{$subscription->id}:{$day}", $createdAt);
            }

            foreach ($this->split($amount - $fee, $weights) as $instructorId => $cents) {
                if ($cents <= 0) {
                    continue;
                }

                $key = "accrual:{$subscription->id}:{$day}:{$instructorId}";

                if ($this->ledger($instructorId, 'accrual', $cents, $subscription->id, $slice->id, $key, $createdAt)) {
                    $owed[$instructorId] = ($owed[$instructorId] ?? 0) + $cents;
                }
            }

            RevenueRecognitionSlice::whereIn('id', $backlog->pluck('id'))->update(['status' => 'recognized']);
            $backlog = collect();
        }

        foreach ($owed as $instructorId => $cents) {
            DB::statement(
                'INSERT INTO instructor_balances (instructor_id, owed_cents, paid_cents, updated_at)
                 VALUES (?, ?, 0, ?)
                 ON CONFLICT (instructor_id) DO UPDATE SET
                    owed_cents = instructor_balances.owed_cents + EXCLUDED.owed_cents,
                    updated_at = EXCLUDED.updated_at',
                [$instructorId, $cents, now()]
            );
        }
    }

    protected function ledger(?int $instructorId, string $type, int $cents, ?int $subscriptionId, ?int $sliceId, string $key, Carbon $createdAt): bool
    {
        $inserted = DB::table('ledger_entries')->insertOrIgnore([[
            'instructor_id' => $instructorId,
            'entry_type' => $type,
            'amount_cents' => $cents,
            'subscription_id' => $subscriptionId,
            'recognition_slice_id' => $sliceId,
            'payout_batch_id' => null,
            'idempotency_key' => $key,
            'created_at' => $createdAt,
            'recorded_at' => now(),
        ]]);

        if ($inserted) {
            $this->stats['ledger']++;
        }

        return (bool) $inserted;
    }

    /**
     * Largest-remainder split in whole cents: nobody gets a fractional cent and every cent is allocated.
     *
     * @param  array<int, int>  $weights  instructor id => watched seconds
     * @return array<int, int>  instructor id => cents
     */
    protected function split(int $pool, array $weights): array
    {
        ksort($weights);
        $total = array_sum($weights);
        $shares = [];
        $fractions = [];
        $given = 0;

        foreach ($weights as $id => $weight) {
            $raw = $pool * $weight;
            $shares[$id] = intdiv($raw, $total);
            $fractions[$id] = $raw % $total;
            $given += $shares[$id];
        }

        $order = array_keys($weights);
        usort($order, fn ($a, $b) => $fractions[$b] <=> $fractions[$a] ?: $a <=> $b);

        foreach (array_slice($order, 0, $pool - $given) as $id) {
            $shares[$id]++;
        }

        return $shares;
    }

    // ------------------------------------------------------------------ payouts

    protected function seedPayouts(): void
    {
        $plan = [
            'instructor@llm.com' => ['paid' => [28, 21, 14], 'tail' => ['failed', 3]],
            'instructor1@llm.com' => ['paid' => [21, 14], 'tail' => ['uncertain', 2]],
            'instructor2@llm.com' => ['paid' => [14], 'tail' => null],
        ];

        foreach ($plan as $email => $config) {
            $instructor = User::where('email', $email)->first();

            if (! $instructor) {
                continue;
            }

            foreach ($config['paid'] as $daysAgo) {
                $this->payout($instructor, $daysAgo, 'succeeded');
            }

            if ($config['tail']) {
                $this->payout($instructor, $config['tail'][1], $config['tail'][0]);
            }
        }
    }

    /** Pays out everything accrued before the cutoff and not already paid, through the real finalize actions. */
    protected function payout(User $instructor, int $daysAgo, string $outcome): void
    {
        $claimedAt = $this->today->copy()->subDays($daysAgo)->setTime(2, 0);
        $periodEnd = $claimedAt->copy()->subDay()->startOfDay();

        $accrued = (int) DB::table('ledger_entries')
            ->where('instructor_id', $instructor->id)
            ->where('entry_type', 'accrual')
            ->where('created_at', '<=', $periodEnd->copy()->endOfDay())
            ->sum('amount_cents');
        $paid = abs((int) DB::table('ledger_entries')
            ->where('instructor_id', $instructor->id)
            ->where('entry_type', 'payout')
            ->sum('amount_cents'));

        $amount = $accrued - $paid;

        if ($amount < 500) {
            return;
        }

        Carbon::setTestNow($claimedAt);

        $batch = PayoutBatch::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_demo_' . $instructor->id,
            'period_start' => $periodEnd->copy()->subDays(6)->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'amount_cents' => $amount,
            'status' => 'submitted',
            'gateway_idempotency_key' => (string) Str::uuid(),
            'claimed_at' => $claimedAt,
        ]);

        Carbon::setTestNow($claimedAt->copy()->addHour());
        $finalize = app(FinalizePayoutBatchAction::class);

        match ($outcome) {
            'succeeded' => $finalize->markSucceeded($batch->id, 'tr_demo_' . $batch->id),
            'failed' => $finalize->markFailed($batch->id, 'insufficient_funds_in_platform_balance'),
            'uncertain' => $finalize->markUncertain($batch->id, 'Timed out waiting for Stripe'),
        };

        $this->stats['payouts']++;
    }

    // ------------------------------------------------------------------ comments and live sessions

    protected function seedComments(array $students): void
    {
        $studentIds = array_map(fn (User $user) => $user->id, array_values($students));
        $questions = [
            'Could you explain this part again? I got lost halfway through.',
            'Great lesson, the example really made it click for me.',
            'Is there a recommended order for the reading material?',
            'I get an error when I try the exercise on my machine. Any ideas?',
            'This is the clearest explanation I have seen so far, thank you!',
            'How does this apply to a larger project?',
            'Could you share the code from the demo?',
            'The audio drops out near the end of the video.',
        ];
        $answers = [
            'Good question! Try re-reading the previous section first, then run the example step by step.',
            'Thanks for the feedback, glad it helped. The next module builds directly on this.',
            'Yes, I will add the full example to the resources shortly.',
            'That usually means a missing dependency. Check the setup lesson and let me know if it persists.',
        ];

        foreach ($this->courses as $course) {
            $isOwn = $course->instructor_id === $this->instructor->id;
            $lessons = $this->lessonsFor($course->id);

            if ($lessons->isEmpty()) {
                continue;
            }

            for ($i = 0, $count = $isOwn ? 14 : 6; $i < $count; $i++) {
                $lesson = $lessons[mt_rand(0, $lessons->count() - 1)];
                $at = $this->today->copy()->subDays(mt_rand(0, 60))->setTime(mt_rand(8, 22), mt_rand(0, 59));

                $parentId = $this->comment($lesson, $studentIds[mt_rand(0, count($studentIds) - 1)], null, $questions[mt_rand(0, count($questions) - 1)], $at, mt_rand(1, 12) === 1);

                if (mt_rand(1, 100) <= 60) {
                    $this->comment($lesson, $course->instructor_id, $parentId, $answers[mt_rand(0, count($answers) - 1)], $at->copy()->addHours(mt_rand(1, 6)));
                }

                if (mt_rand(1, 100) <= 30) {
                    $this->comment($lesson, $studentIds[mt_rand(0, count($studentIds) - 1)], $parentId, 'Thanks, that fixed it for me as well!', $at->copy()->addHours(mt_rand(7, 20)));
                }
            }
        }
    }

    protected function comment(Lesson $lesson, int $userId, ?int $parentId, string $body, Carbon $at, bool $deleted = false): int
    {
        $this->stats['comments']++;

        return DB::table('comments')->insertGetId([
            'commentable_type' => $lesson->getMorphClass(),
            'commentable_id' => $lesson->id,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => $body,
            'edited_at' => null,
            'created_at' => $at,
            'updated_at' => $at,
            'deleted_at' => $deleted ? $at->copy()->addDay() : null,
        ]);
    }

    protected function seedLiveSessions(): void
    {
        foreach ($this->courses as $i => $course) {
            $link = 'https://meet.google.com/demo-' . Str::lower(Str::random(4)) . '-' . Str::lower(Str::random(4));
            $isOwn = $course->instructor_id === $this->instructor->id;

            $this->session($course, "Demo: Past Q&A — {$course->title}", $this->today->copy()->subDays(mt_rand(5, 30))->setTime(18, 0), 60, $link, 'ended');
            $this->session($course, "Demo: Canceled workshop — {$course->title}", $this->today->copy()->addDays(mt_rand(3, 9))->setTime(17, 0), 90, $link, 'canceled');
            $this->session($course, "Demo: Upcoming session — {$course->title}", $this->today->copy()->addDays(mt_rand(2, 14))->setTime(19, 0), 60, $link, 'upcoming');

            if ($isOwn || $i % 2 === 0) {
                $this->session($course, "Demo: Office hours — {$course->title}", $this->today->copy()->addDays(mt_rand(15, 25))->setTime(16, 0), 45, $link, 'upcoming');
            }
        }
    }

    protected function session(Course $course, string $title, Carbon $startsAt, int $minutes, string $link, string $state): void
    {
        LiveSession::firstOrCreate(
            ['course_id' => $course->id, 'title' => $title],
            [
                'description' => 'Demo session created by AnalyticsDemoSeeder.',
                'starts_at' => $startsAt,
                'duration_minutes' => $minutes,
                'meeting_url' => $link,
                'status' => $state === 'canceled' ? 'canceled' : 'scheduled',
                'canceled_at' => $state === 'canceled' ? now() : null,
                'reminder_sent_at' => $state === 'ended' ? $startsAt->copy()->subMinutes(30) : null,
            ]
        );

        $this->stats['sessions']++;
    }

    // ------------------------------------------------------------------ summary

    protected function summary(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Analytics demo data seeded.');
        $this->command->table(['What', 'Count'], [
            ['Demo students', count(self::NAMES)],
            ['Subscriptions', $this->stats['subscriptions']],
            ['Consumption events', $this->stats['events']],
            ['Ledger entries written', $this->stats['ledger']],
            ['Refunds', $this->stats['refunds']],
            ['Payout batches', $this->stats['payouts']],
            ['Certificates', $this->stats['certificates']],
            ['Comments', $this->stats['comments']],
            ['Live sessions', $this->stats['sessions']],
        ]);
        $this->command->line('Log in: admin@llm.com / admin@12334, instructor@llm.com / instructor@12334, demo students / Password123.');
    }
}
