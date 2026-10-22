<?php

namespace App\Notifications;

final class SystemTestNotification extends AppNotification
{
    public static function make(string $title, string $body, string $url = '/dashboard'): self
    {
        return new self('system_test', $title, $body, $url);
    }
}
