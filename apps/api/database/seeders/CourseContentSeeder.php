<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\ResourceLink;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CourseContentSeeder extends Seeder
{
    /**
     * Real, freely-licensed sample video clips (Google's public GTV test
     * assets — safe to reference directly, no auth/CORS issues). Durations
     * are the actual clip lengths, all well over 1 minute, so heartbeat
     * tracking and "mark as watched" testing works against real playback
     * rather than a placeholder URL with no real duration.
     */
    // protected array $sampleVideos = [
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 'duration' => 596],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 'duration' => 653],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 'duration' => 15],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4', 'duration' => 15],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4', 'duration' => 888],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4', 'duration' => 596],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/TearsOfSteel.mp4', 'duration' => 734],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/VolkswagenGTIReview.mp4', 'duration' => 89],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/WeAreGoingOnBullrun.mp4', 'duration' => 47],
    //     ['url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/WhatCarCanYouGetForAGrand.mp4', 'duration' => 155],
    // ];
    protected array $sampleVideos = [
        ['url' => 'https://html5demos.com/assets/dinky.mp4', 'duration' => 60],
        ['url' => 'https://media.w3.org/2010/05/sintel/trailer.mp4', 'duration' => 52],
        ['url' => 'https://vjs.zencdn.net/v/oceans.mp4', 'duration' => 46],
        ['url' => 'https://raw.githubusercontent.com/intel-iot-devkit/sample-videos/master/free-video-file-480p.mp4', 'duration' => 60],
        ['url' => 'https://upload.wikimedia.org/wikipedia/commons/transcoded/f/f1/Sintel_trailer_1080p.it.ogv/Sintel_trailer_1080p.it.ogv.360p.vp9.webm', 'duration' => 52],
        ['url' => 'https://files.testim.io/sample-video.mp4', 'duration' => 60],
        ['url' => 'https://raw.githubusercontent.com/andreasbotsikas/DemoVideos/master/big_buck_bunny.mp4', 'duration' => 596],
        ['url' => 'https://raw.githubusercontent.com/andreasbotsikas/DemoVideos/master/elephants_dream.mp4', 'duration' => 653],
        ['url' => 'https://raw.githubusercontent.com/andreasbotsikas/DemoVideos/master/tears_of_steel.mp4', 'duration' => 734],
        ['url' => 'https://test-videos.co.uk/vids/jellyfish/mp4/h264/1080/Jellyfish_1080_10s_1MB.mp4', 'duration' => 60],
    ];
    protected array $courseTopics = [
        ['title' => 'Laravel for Beginners', 'desc' => 'A practical introduction to building web applications with Laravel, from routing to Eloquent.'],
        ['title' => 'Mastering React Hooks', 'desc' => 'Deep dive into useState, useEffect, custom hooks, and patterns for scalable React apps.'],
        ['title' => 'PostgreSQL Performance Tuning', 'desc' => 'Indexing strategies, query planning, and partitioning for high-scale Postgres databases.'],
        ['title' => 'TypeScript in Practice', 'desc' => 'Moving from JavaScript to TypeScript with confidence — types, generics, and real-world patterns.'],
        ['title' => 'System Design Fundamentals', 'desc' => 'Core concepts behind designing scalable, reliable distributed systems.'],
        ['title' => 'Docker & Containerization', 'desc' => 'Package, ship, and run applications consistently across environments with Docker.'],
        ['title' => 'Introduction to Stripe Payments', 'desc' => 'Accepting payments, handling webhooks, and building subscription billing with Stripe.'],
        ['title' => 'Next.js App Router Deep Dive', 'desc' => 'Server components, streaming, and the mental model behind the Next.js App Router.'],
    ];

    protected array $moduleNames = ['Getting Started', 'Core Concepts', 'Hands-On Practice', 'Advanced Topics', 'Wrapping Up'];

    public function run(): void
    {
        $instructors = $this->ensureInstructors();

        foreach ($this->courseTopics as $i => $topic) {
            $instructor = $instructors[$i % count($instructors)];
            $course = $this->createCourse($instructor, $topic);
            $this->createModulesAndLessons($course);
        }

        $this->command?->info('Seeded ' . count($this->courseTopics) . ' courses with modules, lessons, resource links, and quizzes.');
    }

    protected function ensureInstructors(): array
    {
        $names = ['Sarah Chen', 'Marcus Johnson', 'Priya Patel', 'David Kim'];
        $instructors = [];

        foreach ($names as $i => $name) {
            if($i==0){
                $email = 'instructor' . '@llm.com';
            }else{
                $email = 'instructor' . ($i) . '@llm.com';
            }


            $instructor = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('Password123')]
            );

            if (! $instructor->hasRole('instructor')) {
                $instructor->assignRole('instructor');
            }

            $instructors[] = $instructor;
        }

        return $instructors;
    }

    protected function createCourse(User $instructor, array $topic): Course
    {
        return Course::firstOrCreate(
            ['slug' => Str::slug($topic['title'])],
            [
                'instructor_id' => $instructor->id,
                'title' => $topic['title'],
                'description' => $topic['desc'],
                'status' => 'published',
            ]
        );
    }

    protected function createModulesAndLessons(Course $course): void
    {
        if ($course->modules()->exists()) {
            return; // already seeded, don't duplicate on re-run
        }

        $moduleCount = random_int(3, 4);

        foreach (array_slice($this->moduleNames, 0, $moduleCount) as $order => $moduleName) {
            $module = Module::create([
                'course_id' => $course->id,
                'title' => $moduleName,
                'order' => $order,
            ]);

            $lessonCount = random_int(2, 4);

            for ($i = 0; $i < $lessonCount; $i++) {
                $type = $i === 0 ? 'video' : (random_int(0, 1) ? 'video' : 'text');
                $lesson = $this->createLesson($module, $moduleName, $i, $type);

                if (random_int(0, 2) === 0) {
                    $this->attachResourceLink($lesson);
                }

                if ($i === $lessonCount - 1 && random_int(0, 1) === 1) {
                    $this->attachQuiz($lesson);
                }
            }
        }
    }

    protected function createLesson(Module $module, string $moduleName, int $index, string $type): Lesson
    {
        $title = "{$moduleName}: " . $this->lessonTitleFor($index, $type);

        if ($type === 'video') {
            $video = $this->sampleVideos[array_rand($this->sampleVideos)];

            return Lesson::create([
                'module_id' => $module->id,
                'title' => $title,
                'type' => 'video',
                'video_url' => $video['url'],
                'video_duration_seconds' => $video['duration'],
                'order' => $index,
            ]);
        }

        return Lesson::create([
            'module_id' => $module->id,
            'title' => $title,
            'type' => 'text',
            'body' => $this->sampleArticleBody($title),
            'order' => $index,
        ]);
    }

    protected function lessonTitleFor(int $index, string $type): string
    {
        $videoTitles = ['Overview', 'Walkthrough', 'Live Demo', 'Deep Dive'];
        $textTitles = ['Key Concepts', 'Reading: Background', 'Summary & Notes', 'Further Reading'];

        $pool = $type === 'video' ? $videoTitles : $textTitles;

        return $pool[$index % count($pool)];
    }

    protected function sampleArticleBody(string $title): string
    {
        // ~350-450 words so the Phase 4 reading-time estimate (~200 wpm)
        // produces a believable, non-trivial engagement_seconds value.
        $paragraph = 'Understanding the underlying principles here matters more than memorizing syntax. '
            . 'As you work through real examples, pay attention to the decisions being made and why — '
            . 'the reasoning transfers to problems you have not seen yet, while memorized syntax does not. '
            . 'Take your time with each section, and revisit earlier material if something later does not click right away. ';

        $body = "<h2>{$title}</h2>";
        for ($i = 0; $i < 6; $i++) {
            $body .= '<p>' . str_repeat($paragraph, 2) . '</p>';
        }

        return $body;
    }

    protected function attachResourceLink(Lesson $lesson): void
    {
        ResourceLink::create([
            'lesson_id' => $lesson->id,
            'title' => 'Official documentation',
            'url' => 'https://developer.mozilla.org/en-US/',
        ]);
    }

    protected function attachQuiz(Lesson $lesson): void
    {
        $quiz = Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Quick check',
        ]);

        foreach (range(1, 2) as $qOrder) {
            $question = QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => "Sample question {$qOrder} for \"{$lesson->title}\"?",
                'order' => $qOrder,
            ]);

            $options = ['Correct answer', 'Plausible distractor', 'Another distractor', 'Unlikely answer'];
            shuffle($options);

            foreach ($options as $i => $text) {
                QuizOption::create([
                    'quiz_question_id' => $question->id,
                    'option_text' => $text,
                    'is_correct' => $text === 'Correct answer',
                ]);
            }
        }
    }
}
