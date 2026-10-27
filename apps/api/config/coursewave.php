<?php

return [
    'notifications' => [
        // Admins get one alert per new paid subscription. Turn off once volume makes it noise.
        'admin_new_subscription' => (bool) env('ADMIN_NOTIFY_NEW_SUBSCRIPTION', true),

        // Admins get an alert when a student or another admin cancels a subscription.
        'admin_subscription_canceled' => (bool) env('ADMIN_NOTIFY_SUBSCRIPTION_CANCELED', true),

        // Instructors get an alert when a student completes one of their courses.
        'instructor_course_completed' => (bool) env('INSTRUCTOR_NOTIFY_COURSE_COMPLETED', true),
    ],

    'live_sessions' => [
        // Students are reminded once, this many minutes before a session starts.
        'reminder_minutes_before' => (int) env('LIVE_SESSION_REMINDER_MINUTES', 30),

        // The join link is revealed this many minutes before the start, until the session ends.
        'join_opens_minutes_before' => (int) env('LIVE_SESSION_JOIN_OPENS_MINUTES', 15),
    ],
];