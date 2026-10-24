<?php

namespace Tests\Feature\Notifications;

use App\Actions\Billing\FinalizePayoutBatchAction;
use App\Actions\Billing\FinalizeRefundAction;
use App\Broadcasting\UserChannel;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\PayoutBatch;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\CertificateIssuedNotification;
use App\Notifications\CommentReplyNotification;
use App\Notifications\InstructorNotification;
use App\Notifications\PayoutProcessedNotification;
use App\Notifications\RefundProcessedNotification;
use App\Notifications\SystemTestNotification;
use App\Support\SafeNotify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Notification::fake();
        Queue::fake(); // certificate PDF jobs etc. are not what these tests are about
    }

    protected function actingWith(string $token): static
    {
        $this->app['auth']->forgetGuards(); // convention #37

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /** @return array{0: User, 1: string} */
    protected function subscribedStudent(string $name = 'Sam Student'): array
    {
        $student = User::factory()->create(['name' => $name]);
        $student->assignRole('student');
        Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => Plan::factory()->create()->id,
            'status' => 'active',
        ]);

        return [$student, $student->createToken('test')->plainTextToken];
    }

    /** @return array{0: Course, 1: \Illuminate\Support\Collection} */
    protected function courseWithLessons(int $count = 2): array
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published', 'title' => 'Laravel Mastery']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = collect(range(1, $count))->map(fn ($i) => Lesson::factory()->create([
            'module_id' => $module->id, 'type' => 'text', 'body' => 'short', 'order' => $i,
        ]));

        return [$course, $lessons];
    }

    // ---------------------------------------------------------------- comment replies

    public function test_a_reply_notifies_the_parent_comments_author(): void
    {
        [$course, $lessons] = $this->courseWithLessons(1);
        $lesson = $lessons->first();
        [$author] = $this->subscribedStudent('Ann Author');
        [, $replierToken] = $this->subscribedStudent('Bob Replier');

        $parent = Comment::create([
            'commentable_type' => Lesson::class, 'commentable_id' => $lesson->id,
            'user_id' => $author->id, 'body' => 'Question?',
        ]);

        $this->actingWith($replierToken)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'Here is my answer', 'parent_id' => $parent->id,
        ])->assertStatus(201);

        Notification::assertSentTo($author, CommentReplyNotification::class, function ($notification) use ($course, $lesson) {
            return str_contains($notification->title, 'Bob Replier')
                && $notification->body === 'Here is my answer'
                && $notification->url === "/learn/{$course->slug}/{$lesson->id}";
        });
    }

    public function test_replying_to_yourself_or_posting_top_level_sends_no_reply_notification(): void
    {
        [, $lessons] = $this->courseWithLessons(1);
        $lesson = $lessons->first();
        [, $token] = $this->subscribedStudent();

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Top level'])
            ->assertStatus(201);
        $parentId = Comment::firstOrFail()->id;

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'Replying to myself', 'parent_id' => $parentId,
        ])->assertStatus(201);

        // The lesson's instructor hears about both (see NotificationAudienceTest); nobody gets a "reply to you".
        Notification::assertNothingSentTo(User::role('student')->get()->all(), CommentReplyNotification::class);
    }

    // ---------------------------------------------------------------- certificates

    public function test_the_certificate_notification_is_sent_once_when_the_course_is_completed(): void
    {
        [, $lessons] = $this->courseWithLessons(2);
        [$student, $token] = $this->subscribedStudent();

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lessons[0]->id}/progress", ['completed' => true]);
        Notification::assertNotSentTo($student, CertificateIssuedNotification::class); // course not finished yet

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lessons[1]->id}/progress", ['completed' => true]);
        $this->actingWith($token)->postJson("/api/learning/lessons/{$lessons[1]->id}/progress", ['completed' => true]); // again

        Notification::assertSentTo($student, CertificateIssuedNotification::class, function ($notification) {
            return $notification->body === 'You completed Laravel Mastery.' && $notification->url === '/certificates';
        });
        Notification::assertSentTimes(CertificateIssuedNotification::class, 1);
    }

    // ---------------------------------------------------------------- money notifications

    public function test_a_successful_payout_notifies_the_instructor_once(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        $batch = PayoutBatch::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_test',
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'amount_cents' => 5000,
            'status' => 'submitted',
            'gateway_idempotency_key' => (string) Str::uuid(),
            'claimed_at' => now(),
        ]);

        $finalize = app(FinalizePayoutBatchAction::class);
        $finalize->markSucceeded($batch->id, 'tr_test');
        $finalize->markSucceeded($batch->id, 'tr_test'); // a retried call must not notify again

        Notification::assertSentTo($instructor, PayoutProcessedNotification::class, function ($notification) {
            return $notification->title === 'Payout processed' && str_contains($notification->body, '$50.00');
        });
        Notification::assertSentTimes(PayoutProcessedNotification::class, 1);
    }

    public function test_a_failed_payout_sends_no_processed_notification(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        $batch = PayoutBatch::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_test',
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'amount_cents' => 5000,
            'status' => 'submitted',
            'gateway_idempotency_key' => (string) Str::uuid(),
            'claimed_at' => now(),
        ]);

        app(FinalizePayoutBatchAction::class)->markFailed($batch->id, 'Declined');

        Notification::assertNotSentTo($instructor, PayoutProcessedNotification::class);
        Notification::assertSentTo($instructor, InstructorNotification::class, fn ($n) => $n->kind === 'payout_failed');
    }

    public function test_a_successful_refund_notifies_the_student_once(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);

        $refund = Refund::create([
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test',
            'amount_cents' => 700,
            'reason' => 'student_cancel',
            'status' => 'submitted',
            'idempotency_key' => "refund:{$subscription->id}",
        ]);

        $finalize = app(FinalizeRefundAction::class);
        $finalize->markSucceeded($refund->id, 're_test');
        $finalize->markSucceeded($refund->id, 're_test');

        Notification::assertSentTo($subscription->student, RefundProcessedNotification::class, function ($notification) {
            return str_contains($notification->body, '$7.00') && $notification->url === '/dashboard';
        });
        Notification::assertSentTimes(RefundProcessedNotification::class, 1);
    }

    // ---------------------------------------------------------------- shape + safety

    public function test_notifications_use_the_database_and_broadcast_channels_with_flat_fields_and_filament_format(): void
    {
        $user = User::factory()->create();
        $payload = SystemTestNotification::make('Hi', 'There', '/certificates');

        $this->assertEquals(['database', 'broadcast'], $payload->via($user));

        $data = $payload->toArray($user);
        $this->assertEquals('system_test', $data['kind']);
        $this->assertEquals('Hi', $data['title']);
        $this->assertEquals('There', $data['body']);
        $this->assertEquals('/certificates', $data['url']);
        $this->assertEquals('filament', $data['format']); // what Filament's bell filters on
    }

    public function test_a_failing_notification_never_reaches_the_caller(): void
    {
        $broken = new class {
            public function notify($notification): void
            {
                throw new \RuntimeException('queue is down');
            }
        };

        SafeNotify::send($broken, SystemTestNotification::make('x', 'y'));

        $this->assertTrue(true); // reaching this line is the assertion
    }

    public function test_a_user_may_only_join_their_own_channel(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue((new UserChannel)->join($me, $me->id));
        $this->assertFalse((new UserChannel)->join($me, $other->id));
    }
}
