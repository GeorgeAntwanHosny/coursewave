<?php

namespace Tests\Feature\Learning;

use App\Actions\Learning\GenerateCertificatePdfAction;
use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DevCertificateToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        Storage::fake(GenerateCertificatePdfAction::DISK);
        Queue::fake();
    }

    protected function actingWith(string $token): static
    {
        $this->app['auth']->forgetGuards(); // convention #37

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    protected function publishedCourse(int $lessons = 2): Course
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        foreach (range(1, $lessons) as $i) {
            Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short', 'order' => $i]);
        }

        return $course;
    }

    /** @return array{0: User, 1: string} */
    protected function student(): array
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        return [$student, $student->createToken('test')->plainTextToken];
    }

    public function test_it_completes_two_courses_and_issues_two_certificates(): void
    {
        $courses = collect([$this->publishedCourse(), $this->publishedCourse(), $this->publishedCourse()]);
        [$student, $token] = $this->student();

        $this->actingWith($token)->postJson('/api/dev/complete-courses')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.courses')
            ->assertJsonPath('data.courses.0.certificate_newly_issued', true)
            ->assertJsonPath('data.courses.0.lessons_completed', 2);

        $this->assertEquals(2, Certificate::where('student_id', $student->id)->count());
        $this->assertEquals(4, LessonProgress::where('student_id', $student->id)->where('status', 'completed')->count());

        // The third course is untouched.
        $this->assertDatabaseMissing('certificates', ['student_id' => $student->id, 'course_id' => $courses[2]->id]);
        Queue::assertPushed(GenerateCertificatePdfJob::class, 2);
    }

    public function test_it_never_writes_consumption_events(): void
    {
        $this->publishedCourse();
        $this->publishedCourse();
        [, $token] = $this->student();

        $this->actingWith($token)->postJson('/api/dev/complete-courses')->assertStatus(200);

        $this->assertEquals(0, ConsumptionEvent::count()); // no fake engagement reaching revenue recognition
    }

    public function test_calling_it_again_moves_on_and_never_duplicates(): void
    {
        $this->publishedCourse();
        $this->publishedCourse();
        [$student, $token] = $this->student();

        $this->actingWith($token)->postJson('/api/dev/complete-courses')->assertJsonCount(2, 'data.courses');
        $this->actingWith($token)->postJson('/api/dev/complete-courses')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.courses');

        $this->assertEquals(2, Certificate::where('student_id', $student->id)->count());
    }

    public function test_count_is_respected(): void
    {
        $this->publishedCourse();
        $this->publishedCourse();
        [, $token] = $this->student();

        $this->actingWith($token)->postJson('/api/dev/complete-courses', ['count' => 1])
            ->assertJsonCount(1, 'data.courses');
    }

    public function test_generate_pdf_renders_the_real_pdf_and_it_is_downloadable(): void
    {
        $this->publishedCourse();
        [, $token] = $this->student();

        $response = $this->actingWith($token)->postJson('/api/dev/complete-courses', ['count' => 1, 'generate_pdf' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.courses.0.pdf_error', null)
            ->assertJsonPath('data.courses.0.certificate.pdf_ready', true);

        $certificate = Certificate::firstOrFail();
        Storage::disk(GenerateCertificatePdfAction::DISK)->assertExists($certificate->pdf_path);

        $this->actingWith($token)->get("/api/certificates/{$certificate->id}/download")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_tool_is_unavailable_outside_local_and_testing(): void
    {
        $this->publishedCourse();
        [, $token] = $this->student();

        $this->app['env'] = 'production';

        $this->actingWith($token)->postJson('/api/dev/complete-courses')->assertStatus(404);
        $this->actingWith($token)->deleteJson('/api/dev/reset-learning')->assertStatus(404);
        $this->assertEquals(0, Certificate::count());
    }

    public function test_non_students_are_refused_and_guests_are_unauthenticated(): void
    {
        $this->publishedCourse();

        $this->postJson('/api/dev/complete-courses')->assertStatus(401);
        $this->assertEquals(0, Certificate::count());

        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        $this->actingWith($instructor->createToken('t')->plainTextToken)
            ->postJson('/api/dev/complete-courses')->assertStatus(403);

    }

    public function test_reset_clears_only_this_students_data_and_their_pdfs(): void
    {
        $this->publishedCourse();
        $this->publishedCourse();
        [$mine, $myToken] = $this->student();
        [$other, $otherToken] = $this->student();

        $this->actingWith($myToken)->postJson('/api/dev/complete-courses', ['generate_pdf' => true])->assertStatus(200);
        $this->actingWith($otherToken)->postJson('/api/dev/complete-courses')->assertStatus(200);

        $pdfPath = Certificate::where('student_id', $mine->id)->firstOrFail()->pdf_path;
        Storage::disk(GenerateCertificatePdfAction::DISK)->assertExists($pdfPath);

        $this->actingWith($myToken)->deleteJson('/api/dev/reset-learning')
            ->assertStatus(200)
            ->assertJsonPath('data.certificates_deleted', 2)
            ->assertJsonPath('data.lesson_progress_deleted', 4);

        $this->assertEquals(0, Certificate::where('student_id', $mine->id)->count());
        $this->assertEquals(0, LessonProgress::where('student_id', $mine->id)->count());
        Storage::disk(GenerateCertificatePdfAction::DISK)->assertMissing($pdfPath);

        // The other student is untouched.
        $this->assertEquals(2, Certificate::where('student_id', $other->id)->count());
    }
}
