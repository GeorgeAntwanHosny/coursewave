<?php

namespace Tests\Feature\Analytics;

use App\Filament\Admin\Pages\Analytics as AdminAnalytics;
use App\Filament\Admin\Widgets\PlatformEngagementChart;
use App\Filament\Admin\Widgets\PlatformGrowthStats;
use App\Filament\Admin\Widgets\PlatformHealthStats;
use App\Filament\Admin\Widgets\PlatformRevenueChart;
use App\Filament\Admin\Widgets\PlatformRevenueStats;
use App\Filament\Admin\Widgets\PlatformSubscriptionsChart;
use App\Filament\Admin\Widgets\TopCoursesTable;
use App\Filament\Admin\Widgets\TopInstructorsTable;
use App\Filament\Instructor\Pages\Analytics as InstructorAnalyticsPage;
use App\Filament\Instructor\Pages\Learners;
use App\Filament\Instructor\Widgets\InstructorCoursePerformance;
use App\Filament\Instructor\Widgets\InstructorEarningsChart;
use App\Filament\Instructor\Widgets\InstructorEngagementChart;
use App\Filament\Instructor\Widgets\InstructorOverviewStats;
use App\Models\Certificate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

/**
 * Smoke tests: every widget and page renders for the right user, and the tables show only their own data.
 * They exercise the Filament wiring (the likeliest thing to differ between Filament versions), not the
 * numbers, which InstructorAnalyticsTest and PlatformAnalyticsTest cover.
 */
class AnalyticsWidgetsTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['coursewave.analytics.cache_seconds' => 0]);
    }

    protected function certificateFor($student, $course): Certificate
    {
        return Certificate::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'CW-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
            'student_name' => $student->name,
            'course_title' => $course->title,
            'instructor_name' => 'x',
            'issued_at' => now(),
        ]);
    }

    public function test_every_instructor_widget_and_page_renders(): void
    {
        $instructor = $this->userWithRole('instructor');
        $course = $this->publishedCourse($instructor);
        $this->studentTakingCourse($course);
        $this->actingAs($instructor);
        Filament::setCurrentPanel(Filament::getPanel('instructor'));

        foreach ([
            InstructorOverviewStats::class,
            InstructorEarningsChart::class,
            InstructorEngagementChart::class,
            InstructorCoursePerformance::class,
            InstructorAnalyticsPage::class,
            Learners::class,
        ] as $component) {
            Livewire::test($component)->assertSuccessful();
        }
    }

    public function test_an_instructor_with_nothing_yet_still_sees_every_widget(): void
    {
        $instructor = $this->userWithRole('instructor');
        $this->actingAs($instructor);
        Filament::setCurrentPanel(Filament::getPanel('instructor'));

        Livewire::test(InstructorOverviewStats::class)->assertSuccessful();
        Livewire::test(InstructorCoursePerformance::class)->assertSuccessful();
        Livewire::test(Learners::class)->assertSuccessful();
    }

    public function test_the_course_table_lists_only_the_instructors_own_courses(): void
    {
        $instructor = $this->userWithRole('instructor');
        $mine = $this->publishedCourse($instructor);
        $theirs = $this->publishedCourse();
        $this->actingAs($instructor);
        Filament::setCurrentPanel(Filament::getPanel('instructor'));

        Livewire::test(InstructorCoursePerformance::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_learners_table_lists_only_students_of_the_instructors_courses(): void
    {
        $instructor = $this->userWithRole('instructor');
        $mine = $this->publishedCourse($instructor);
        $theirs = $this->publishedCourse();
        $myStudent = $this->studentTakingCourse($mine, 'My Student');
        $theirStudent = $this->studentTakingCourse($theirs, 'Their Student');
        $notStarted = $this->subscribedStudent('Has not started');
        $this->actingAs($instructor);
        Filament::setCurrentPanel(Filament::getPanel('instructor'));

        Livewire::test(Learners::class)
            ->assertCanSeeTableRecords([$myStudent])
            ->assertCanNotSeeTableRecords([$theirStudent, $notStarted]);
    }

    public function test_the_completed_filter_shows_only_students_with_a_certificate_for_this_instructors_courses(): void
    {
        $instructor = $this->userWithRole('instructor');
        $mine = $this->publishedCourse($instructor);
        $theirs = $this->publishedCourse();

        $finisher = $this->studentTakingCourse($mine, 'Finisher');
        $inProgress = $this->studentTakingCourse($mine, 'In progress');

        $this->certificateFor($finisher, $mine);
        // A certificate for ANOTHER instructor's course must not make this student count as a finisher here.
        $this->certificateFor($inProgress, $theirs);

        $this->actingAs($instructor);
        Filament::setCurrentPanel(Filament::getPanel('instructor'));

        Livewire::test(Learners::class)
            ->filterTable('completed')
            ->assertCanSeeTableRecords([$finisher])
            ->assertCanNotSeeTableRecords([$inProgress]);
    }

    public function test_every_admin_widget_and_page_renders(): void
    {
        $admin = $this->userWithRole('admin');
        $this->publishedCourse();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ([
            PlatformHealthStats::class,
            PlatformRevenueStats::class,
            PlatformGrowthStats::class,
            PlatformRevenueChart::class,
            PlatformSubscriptionsChart::class,
            PlatformEngagementChart::class,
            TopInstructorsTable::class,
            TopCoursesTable::class,
            AdminAnalytics::class,
        ] as $component) {
            Livewire::test($component)->assertSuccessful();
        }
    }

    public function test_the_top_tables_list_instructors_and_courses(): void
    {
        $admin = $this->userWithRole('admin');
        $course = $this->publishedCourse();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(TopInstructorsTable::class)->assertCanSeeTableRecords([$course->instructor]);
        Livewire::test(TopCoursesTable::class)->assertCanSeeTableRecords([$course]);
    }
}
