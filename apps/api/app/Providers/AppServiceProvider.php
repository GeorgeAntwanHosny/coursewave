<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Policies\CoursePolicy;
use App\Policies\LessonPolicy;
use App\Policies\ModulePolicy;
use App\Policies\QuizPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Payments\PaymentGatewayInterface::class,
            \App\Services\Payments\StripeGateway::class
        );
    }

    public function boot(): void
    {
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Module::class, ModulePolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
    }
}
