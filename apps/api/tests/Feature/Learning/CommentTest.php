<?php

namespace Tests\Feature\Learning;

use App\Models\Comment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @return array{0: User, 1: Lesson} */
    protected function lessonWithInstructor(string $courseStatus = 'published'): array
    {
        $instructor = $this->userWithRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => $courseStatus]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short']);

        return [$instructor, $lesson];
    }

    /** @return array{0: User, 1: string} */
    protected function subscribedStudent(): array
    {
        $student = $this->userWithRole('student');
        Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => Plan::factory()->create()->id,
            'status' => 'active',
        ]);

        return [$student, $student->createToken('test')->plainTextToken];
    }

    protected function actingWith(string $token): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    protected function makeComment(Lesson $lesson, User $user, string $body = 'Hello', ?int $parentId = null): Comment
    {
        return Comment::create([
            'commentable_type' => Lesson::class,
            'commentable_id' => $lesson->id,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => $body,
        ]);
    }

    // ---------------------------------------------------------------- access

    public function test_guest_cannot_read_or_post(): void
    {
        [, $lesson] = $this->lessonWithInstructor();

        $this->getJson("/api/learning/lessons/{$lesson->id}/comments")->assertStatus(401);
        $this->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Hi'])->assertStatus(401);
    }

    public function test_student_without_subscription_is_blocked(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        $student = $this->userWithRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertStatus(403)->assertJsonPath('success', false);
        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Hi'])
            ->assertStatus(403);
    }

    public function test_comments_on_an_unpublished_course_are_not_found(): void
    {
        [, $lesson] = $this->lessonWithInstructor('draft');
        [, $token] = $this->subscribedStudent();

        $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments")->assertStatus(404);
    }

    // ---------------------------------------------------------------- posting

    public function test_subscribed_student_can_post_and_see_their_comment(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => '  Great lesson!  '])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.body', 'Great lesson!')
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.is_instructor', false);

        $this->assertDatabaseHas('comments', ['user_id' => $student->id, 'commentable_id' => $lesson->id, 'parent_id' => null]);

        $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.body', 'Great lesson!');
    }

    public function test_empty_whitespace_and_oversized_bodies_are_rejected(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        [, $token] = $this->subscribedStudent();
        $url = "/api/learning/lessons/{$lesson->id}/comments";

        $this->actingWith($token)->postJson($url, ['body' => ''])->assertStatus(422);
        $this->actingWith($token)->postJson($url, ['body' => '    '])->assertStatus(422);
        $this->actingWith($token)->postJson($url, ['body' => str_repeat('a', 2001)])->assertStatus(422);
        $this->assertEquals(0, Comment::count());
    }

    // ---------------------------------------------------------------- threads + instructor highlight

    public function test_student_can_reply_to_a_top_level_comment(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();
        $parent = $this->makeComment($lesson, $instructor, 'Question?');

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'My answer', 'parent_id' => $parent->id,
        ])->assertStatus(201)->assertJsonPath('data.parent_id', $parent->id);

        $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(1, 'data.items.0.replies');
    }

    public function test_reply_to_a_reply_is_rejected(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();
        $parent = $this->makeComment($lesson, $instructor);
        $reply = $this->makeComment($lesson, $student, 'reply', $parent->id);

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'nested', 'parent_id' => $reply->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_parent_from_another_lesson_is_rejected(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [, $otherLesson] = $this->lessonWithInstructor();
        [, $token] = $this->subscribedStudent();
        $foreignParent = $this->makeComment($otherLesson, $instructor);

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'sneaky', 'parent_id' => $foreignParent->id,
        ])->assertStatus(422);
    }

    public function test_course_instructor_reply_is_flagged_and_does_not_need_a_subscription(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student] = $this->subscribedStudent();
        $parent = $this->makeComment($lesson, $student, 'Help?');
        $instructorToken = $instructor->createToken('test')->plainTextToken;

        $this->actingWith($instructorToken)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'Here is how', 'parent_id' => $parent->id,
        ])->assertStatus(201)->assertJsonPath('data.is_instructor', true);

        [, $studentToken] = $this->subscribedStudent();
        $this->actingWith($studentToken)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertJsonPath('data.items.0.is_instructor', false)
            ->assertJsonPath('data.items.0.replies.0.is_instructor', true);
    }

    public function test_an_unrelated_instructor_cannot_comment_without_a_subscription(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        $other = $this->userWithRole('instructor');
        $token = $other->createToken('test')->plainTextToken;

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'hi'])
            ->assertStatus(403);
    }

    public function test_client_cannot_fake_the_instructor_flag_or_the_author(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'I am the instructor',
            'is_instructor' => true,
            'user_id' => $instructor->id,
        ])->assertStatus(201)->assertJsonPath('data.is_instructor', false);

        $this->assertDatabaseHas('comments', ['body' => 'I am the instructor', 'user_id' => $student->id]);
    }

    // ---------------------------------------------------------------- edit

    public function test_author_can_edit_and_it_is_marked_edited(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();
        $comment = $this->makeComment($lesson, $student, 'typo');

        $this->actingWith($token)->patchJson("/api/learning/comments/{$comment->id}", ['body' => 'fixed'])
            ->assertStatus(200)
            ->assertJsonPath('data.body', 'fixed')
            ->assertJsonPath('data.edited', true);
    }

    public function test_only_the_author_can_edit_not_even_the_course_instructor(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student] = $this->subscribedStudent();
        [, $otherToken] = $this->subscribedStudent();
        $comment = $this->makeComment($lesson, $student);

        $this->actingWith($otherToken)->patchJson("/api/learning/comments/{$comment->id}", ['body' => 'x'])->assertStatus(403);
        $this->actingWith($instructor->createToken('t')->plainTextToken)
            ->patchJson("/api/learning/comments/{$comment->id}", ['body' => 'x'])->assertStatus(403);
        $this->assertEquals('Hello', $comment->fresh()->body);
    }

    // ---------------------------------------------------------------- delete

    public function test_author_instructor_and_admin_can_delete_but_other_students_cannot(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student, $studentToken] = $this->subscribedStudent();
        [, $strangerToken] = $this->subscribedStudent();
        $admin = $this->userWithRole('admin');

        $byStranger = $this->makeComment($lesson, $student);
        $this->actingWith($strangerToken)->deleteJson("/api/learning/comments/{$byStranger->id}")->assertStatus(403);

        $this->actingWith($studentToken)->deleteJson("/api/learning/comments/{$byStranger->id}")->assertStatus(200);

        $this->assertSoftDeleted('comments', ['id' => $byStranger->id]);

        $forInstructor = $this->makeComment($lesson, $student);
        $this->actingWith($instructor->createToken('t')->plainTextToken)
            ->deleteJson("/api/learning/comments/{$forInstructor->id}")->assertStatus(200);

        $forAdmin = $this->makeComment($lesson, $student);
        $this->actingWith($admin->createToken('t')->plainTextToken)
            ->deleteJson("/api/learning/comments/{$forAdmin->id}")->assertStatus(200);
    }

    public function test_deleted_comment_with_replies_shows_a_placeholder_and_one_without_disappears(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();

        $withReplies = $this->makeComment($lesson, $student, 'secret text');
        $this->makeComment($lesson, $instructor, 'an answer', $withReplies->id);
        $alone = $this->makeComment($lesson, $student, 'alone');

        $withReplies->delete();
        $alone->delete();

        $response = $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.is_deleted', true)
            ->assertJsonPath('data.items.0.body', null)
            ->assertJsonPath('data.items.0.author', null)
            ->assertJsonCount(1, 'data.items.0.replies');

        $this->assertStringNotContainsString('secret text', $response->getContent());
    }

    public function test_deleting_the_same_comment_twice_is_not_found(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();
        $comment = $this->makeComment($lesson, $student);

        $this->actingWith($token)->deleteJson("/api/learning/comments/{$comment->id}")->assertStatus(200);
        $this->actingWith($token)->deleteJson("/api/learning/comments/{$comment->id}")->assertStatus(404);
    }

    // ---------------------------------------------------------------- listing

    public function test_list_is_paginated_newest_first(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        [$student, $token] = $this->subscribedStudent();

        foreach (range(1, 12) as $i) {
            $this->makeComment($lesson, $student, "Comment {$i}");
        }

        $first = $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertStatus(200)
            ->assertJsonCount(10, 'data.items')
            ->assertJsonPath('data.meta.total', 12)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.items.0.body', 'Comment 12');

        $this->actingWith($token)->getJson("/api/learning/lessons/{$lesson->id}/comments?page=2")
            ->assertJsonCount(2, 'data.items');
    }

    public function test_can_delete_flag_reflects_the_viewer(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student] = $this->subscribedStudent();
        [, $otherToken] = $this->subscribedStudent();
        $this->makeComment($lesson, $student);

        $this->actingWith($otherToken)->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertJsonPath('data.items.0.can_delete', false)
            ->assertJsonPath('data.items.0.can_edit', false);

        $this->actingWith($instructor->createToken('t')->plainTextToken)
            ->getJson("/api/learning/lessons/{$lesson->id}/comments")
            ->assertJsonPath('data.items.0.can_delete', true)
            ->assertJsonPath('data.items.0.can_edit', false);
    }
}
