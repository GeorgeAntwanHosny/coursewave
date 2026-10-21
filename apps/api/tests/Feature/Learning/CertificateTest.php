<?php

namespace Tests\Feature\Learning;

use App\Actions\Learning\GenerateCertificatePdfAction;
use App\Actions\Learning\IssueCertificateAction;
use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Storage::fake(GenerateCertificatePdfAction::DISK);
        Queue::fake(); // jobs are inspected; tests run the PDF job explicitly when they need it
    }

    protected function actingWith(string $token): static
    {
        // The auth guard caches the first resolved user for the whole test (convention #37).
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /** @return array{0: User, 1: Course, 2: \Illuminate\Support\Collection} */
    protected function courseWithLessons(int $count = 2): array
    {
        $instructor = User::factory()->create(['name' => 'Ada Instructor']);
        $instructor->assignRole('instructor');
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => 'published',
            'title' => 'Laravel Mastery',
        ]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessons = collect(range(1, $count))->map(fn ($i) => Lesson::factory()->create([
            'module_id' => $module->id, 'type' => 'text', 'body' => 'short', 'order' => $i,
        ]));

        return [$instructor, $course, $lessons];
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

    protected function complete(string $token, Lesson $lesson)
    {
        return $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/progress", ['completed' => true]);
    }

    /** Completes every lesson through the API and returns the issued certificate. */
    protected function earnCertificate(): array
    {
        [$instructor, $course, $lessons] = $this->courseWithLessons();
        [$student, $token] = $this->subscribedStudent();

        $lessons->each(fn ($lesson) => $this->complete($token, $lesson));

        return [$student, $token, Certificate::firstOrFail(), $course, $instructor];
    }

    // ---------------------------------------------------------------- issuing

    public function test_completing_the_last_lesson_issues_one_certificate_with_snapshots(): void
    {
        [$instructor, $course, $lessons] = $this->courseWithLessons();
        [$student, $token] = $this->subscribedStudent();

        $this->complete($token, $lessons[0])
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_issued', false);
        $this->assertEquals(0, Certificate::count());

        $this->complete($token, $lessons[1])
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_issued', true);

        $certificate = Certificate::firstOrFail();
        $this->assertEquals($student->id, $certificate->student_id);
        $this->assertEquals($course->id, $certificate->course_id);
        $this->assertEquals('Sam Student', $certificate->student_name);
        $this->assertEquals('Laravel Mastery', $certificate->course_title);
        $this->assertEquals('Ada Instructor', $certificate->instructor_name);
        $this->assertMatchesRegularExpression(
            '/^CW-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/',
            $certificate->certificate_number
        );

        Queue::assertPushed(GenerateCertificatePdfJob::class, fn ($job) => $job->certificateId === $certificate->id);
    }

    public function test_an_incomplete_course_issues_nothing(): void
    {
        [, , $lessons] = $this->courseWithLessons(3);
        [, $token] = $this->subscribedStudent();

        $this->complete($token, $lessons[0]);
        $this->complete($token, $lessons[1]);

        $this->assertEquals(0, Certificate::count());
        Queue::assertNothingPushed();
    }

    public function test_completing_again_never_issues_a_second_certificate(): void
    {
        [, $course, $lessons] = $this->courseWithLessons();
        [, $token] = $this->subscribedStudent();

        $lessons->each(fn ($lesson) => $this->complete($token, $lesson));

        $this->complete($token, $lessons[1])
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_issued', false);

        $this->assertEquals(1, Certificate::count());
        Queue::assertPushed(GenerateCertificatePdfJob::class, 1);
    }

    public function test_a_course_with_no_lessons_can_never_be_completed(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        Module::factory()->create(['course_id' => $course->id]);
        [$student] = $this->subscribedStudent();

        $this->assertNull(app(IssueCertificateAction::class)->execute($student, $course));
        $this->assertEquals(0, Certificate::count());
    }

    public function test_the_database_refuses_a_second_certificate_for_the_same_student_and_course(): void
    {
        [$student, , $certificate] = $this->earnCertificate();

        $this->expectException(UniqueConstraintViolationException::class);

        Certificate::create([
            'student_id' => $student->id,
            'course_id' => $certificate->course_id,
            'certificate_number' => 'CW-AAAA-BBBB-CCCC',
            'student_name' => 'x', 'course_title' => 'x', 'instructor_name' => 'x',
            'issued_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- verification

    public function test_public_verification_returns_only_what_is_printed_and_needs_no_login(): void
    {
        [$student, , $certificate] = $this->earnCertificate();

        $this->app['auth']->forgetGuards();
        $response = $this->getJson("/api/certificates/verify/{$certificate->certificate_number}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.student_name', 'Sam Student')
            ->assertJsonPath('data.course_title', 'Laravel Mastery')
            ->assertJsonPath('data.instructor_name', 'Ada Instructor');

        $this->assertStringNotContainsString($student->email, $response->getContent());
    }

    public function test_verification_accepts_lowercase_and_missing_hyphens(): void
    {
        [, , $certificate] = $this->earnCertificate();
        $sloppy = strtolower(str_replace('-', '', $certificate->certificate_number));

        $this->getJson("/api/certificates/verify/{$sloppy}")
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_number', $certificate->certificate_number);
    }

    public function test_unknown_number_is_not_found(): void
    {
        $this->getJson('/api/certificates/verify/CW-AAAA-BBBB-CCCC')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_a_certificate_stays_valid_and_unchanged_after_renames(): void
    {
        [$student, , $certificate, $course] = $this->earnCertificate();

        $student->update(['name' => 'Changed Name']);
        $course->update(['title' => 'Renamed Course']);

        $this->getJson("/api/certificates/verify/{$certificate->certificate_number}")
            ->assertJsonPath('data.student_name', 'Sam Student')
            ->assertJsonPath('data.course_title', 'Laravel Mastery');
    }

    public function test_deleting_the_course_keeps_the_certificate_verifiable(): void
    {
        [, , $certificate, $course] = $this->earnCertificate();

        $course->delete();

        $this->assertNull($certificate->fresh()->course_id);
        $this->getJson("/api/certificates/verify/{$certificate->certificate_number}")->assertStatus(200);
    }

    // ---------------------------------------------------------------- list + download

    public function test_a_student_lists_only_their_own_certificates_and_guests_cannot(): void
    {
        [, $token, $certificate] = $this->earnCertificate();
        [, $otherToken] = $this->subscribedStudent('Other Student');

        $this->actingWith($token)->getJson('/api/certificates')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.certificate_number', $certificate->certificate_number)
            ->assertJsonPath('data.0.pdf_ready', false);

        $this->actingWith($otherToken)->getJson('/api/certificates')->assertJsonCount(0, 'data');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson('/api/certificates')->assertStatus(401);
    }

    public function test_pdf_job_renders_a_real_pdf_and_the_owner_can_download_it(): void
    {
        [, $token, $certificate] = $this->earnCertificate();

        (new GenerateCertificatePdfJob($certificate->id))->handle(app(GenerateCertificatePdfAction::class));

        $certificate->refresh();
        $this->assertNotNull($certificate->pdf_path);
        Storage::disk(GenerateCertificatePdfAction::DISK)->assertExists($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk(GenerateCertificatePdfAction::DISK)->get($certificate->pdf_path));

        $this->actingWith($token)->get("/api/certificates/{$certificate->id}/download")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_another_student_cannot_download_it(): void
    {
        [, , $certificate] = $this->earnCertificate();
        (new GenerateCertificatePdfJob($certificate->id))->handle(app(GenerateCertificatePdfAction::class));
        [, $otherToken] = $this->subscribedStudent('Other Student');

        $this->actingWith($otherToken)->getJson("/api/certificates/{$certificate->id}/download")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_download_before_the_pdf_exists_returns_409(): void
    {
        [, $token, $certificate] = $this->earnCertificate(); // queue is faked, so no PDF yet

        $this->actingWith($token)->getJson("/api/certificates/{$certificate->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_pdf_generation_is_idempotent_and_force_regenerates(): void
    {
        [, , $certificate] = $this->earnCertificate();
        $generate = app(GenerateCertificatePdfAction::class);

        $generate->execute($certificate);
        $first = $certificate->fresh()->pdf_generated_at;

        $this->travel(5)->minutes();

        $generate->execute($certificate->fresh());
        $this->assertTrue($first->equalTo($certificate->fresh()->pdf_generated_at)); // untouched

        $generate->execute($certificate->fresh(), force: true);
        $this->assertTrue($certificate->fresh()->pdf_generated_at->greaterThan($first));
    }

    // ---------------------------------------------------------------- backfill command

    public function test_sync_issues_missing_certificates_and_redispatches_missing_pdfs(): void
    {
        [, $course, $lessons] = $this->courseWithLessons();
        [$student] = $this->subscribedStudent();

        // Completed everything before Phase 9 existed: progress rows, no certificate.
        $lessons->each(fn ($lesson) => LessonProgress::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'status' => 'completed',
            'watched_seconds' => 0,
            'completed_at' => now(),
        ]));

        $this->artisan('certificates:sync')->assertExitCode(0);

        $this->assertDatabaseHas('certificates', ['student_id' => $student->id, 'course_id' => $course->id]);
        Queue::assertPushed(GenerateCertificatePdfJob::class, 1);

        // A certificate whose PDF never got generated is picked up once it is old enough.
        Certificate::query()->update(['created_at' => now()->subHour()]);
        $this->artisan('certificates:sync')->assertExitCode(0);

        Queue::assertPushed(GenerateCertificatePdfJob::class, 2);
        $this->assertEquals(1, Certificate::count()); // running it again never duplicates
    }
}
