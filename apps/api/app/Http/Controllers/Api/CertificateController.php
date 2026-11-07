<?php

namespace App\Http\Controllers\Api;

use App\Actions\Learning\GenerateCertificatePdfAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\CertificateVerificationResource;
use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $certificates = $request->user()->certificates()->with('course')->latest('issued_at')->get();

        return ApiResponse::success(CertificateResource::collection($certificates), 'Certificates retrieved.');
    }

    public function download(Request $request, int $id)
    {
        // Query-scope layer: someone else's certificate id looks exactly like a missing one (404).
        $certificate = $request->user()->certificates()->whereKey($id)->first();

        if (! $certificate || ! Gate::allows('view', $certificate)) {
            return ApiResponse::error('Certificate not found.', null, 404);
        }

        $disk = Storage::disk(GenerateCertificatePdfAction::DISK);

        if (! $certificate->pdf_path || ! $disk->exists($certificate->pdf_path)) {
            // Self-healing: the job may have failed or the queue may be down. Try again now.
            GenerateCertificatePdfJob::dispatch($certificate->id);
            $certificate->refresh();

            if (! $certificate->pdf_path || ! $disk->exists($certificate->pdf_path)) {
                return ApiResponse::error('Your certificate PDF is still being prepared. Please try again in a moment.', null, 409);
            }
        }

        return $disk->download(
            $certificate->pdf_path,
            "CourseWave-Certificate-{$certificate->certificate_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    /** Public. Throttled. Returns only what is printed on the certificate. */
    public function verify(string $number)
    {
        $certificate = Certificate::where('certificate_number', Certificate::normalizeNumber($number))->first();

        if (! $certificate) {
            return ApiResponse::error('Certificate not found.', null, 404);
        }

        return ApiResponse::success(new CertificateVerificationResource($certificate), 'Certificate verified.');
    }
}
