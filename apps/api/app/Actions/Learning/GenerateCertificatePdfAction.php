<?php

namespace App\Actions\Learning;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateCertificatePdfAction
{
    public const DISK = 'certificates';

    /**
     * Renders and stores the PDF. Idempotent: if a PDF already exists it does
     * nothing unless $force is set (admin "Regenerate"). Safe to run twice
     * concurrently — both write identical content to the same path.
     */
    public function execute(Certificate $certificate, bool $force = false): void
    {
        $disk = Storage::disk(self::DISK);

        if (! $force && $certificate->pdf_path && $disk->exists($certificate->pdf_path)) {
            return;
        }

        $output = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'verificationUrl' => $certificate->verificationUrl(),
        ])->setPaper('a4', 'landscape')->output();

        $path = "{$certificate->certificate_number}.pdf";
        $disk->put($path, $output);

        $certificate->update(['pdf_path' => $path, 'pdf_generated_at' => now()]);
    }
}
