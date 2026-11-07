<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LiveSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LiveSessionSeeder extends Seeder
{
    public function run(): void
    {
        $courses = Course::where('status', 'published')->orderBy('id')->limit(4)->get();

        if ($courses->isEmpty()) {
            $this->command?->warn('No published courses found. Run CourseContentSeeder first.');

            return;
        }

        $links = [
            'https://meet.google.com/abc-defg-hij',
            'https://zoom.us/j/1234567890',
            'https://meet.google.com/klm-nopq-rst',
            'https://zoom.us/j/9876543210',
        ];

        foreach ($courses as $i => $course) {
            $this->session(
                $course,
                "Live Q&A: {$course->title}",
                now()->addDays($i + 1)->setTime(18, 0),
                60,
                $links[$i % count($links)],
            );
        }

        // Starts in 45 minutes: outside the 30-minute reminder window right now, so the reminder
        // and the join window can both be watched happening a little later.
        $this->session(
            $courses->first(),
            'Starting soon (test session)',
            now()->addMinutes(45)->startOfMinute(),
            30,
            $links[0],
        );

        $this->command?->info('Seeded live sessions for ' . $courses->count() . ' course(s).');
    }

    protected function session(Course $course, string $title, Carbon $startsAt, int $minutes, string $url): void
    {
        LiveSession::firstOrCreate(
            ['course_id' => $course->id, 'title' => $title],
            [
                'description' => 'Bring your questions — we will go through them live.',
                'starts_at' => $startsAt,
                'duration_minutes' => $minutes,
                // Set explicitly: DatabaseSeeder uses WithoutModelEvents, so the model's own
                // starts_at + duration calculation does not run when this seeder is called through it.
                'ends_at' => $startsAt->copy()->addMinutes($minutes),
                'meeting_url' => $url,
                'status' => 'scheduled',
            ]
        );
    }
}