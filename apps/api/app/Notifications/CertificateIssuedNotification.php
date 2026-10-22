<?php

namespace App\Notifications;

use App\Models\Certificate;

final class CertificateIssuedNotification extends AppNotification
{
    public static function make(Certificate $certificate): self
    {
        return new self(
            'certificate_issued',
            'You earned a certificate',
            "You completed {$certificate->course_title}.",
            '/certificates',
        );
    }
}
