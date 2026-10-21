"use client";

import Link from "next/link";
import { Award, Copy, Download, Link2 } from "lucide-react";
import { toast } from "sonner";
import { useDownloadCertificate, useMyCertificates } from "@/hooks/use-certificates";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

async function copyToClipboard(text: string, label: string) {
  try {
    await navigator.clipboard.writeText(text);
    toast.success(`${label} copied.`);
  } catch {
    toast.error("Couldn't copy to the clipboard.");
  }
}

export default function CertificatesPage() {
  const { data, isLoading, isError, refetch } = useMyCertificates();
  const { mutate: download, isPending, variables } = useDownloadCertificate();

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="mb-2 text-2xl font-semibold sm:text-3xl">My certificates</h1>
      <p className="mb-8 text-sm text-muted-foreground">
        Earned by completing every lesson of a course. Anyone can verify a certificate with its number.
      </p>

      {isLoading && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {Array.from({ length: 2 }).map((_, i) => (
            <Skeleton key={i} className="h-56 w-full rounded-lg" />
          ))}
        </div>
      )}

      {isError && (
        <div className="space-y-3 text-center">
          <p className="text-destructive">Couldn't load your certificates.</p>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            Try again
          </Button>
        </div>
      )}

      {!isLoading && !isError && data && data.length === 0 && (
        <div className="space-y-4 py-12 text-center">
          <Award className="mx-auto h-10 w-10 text-muted-foreground" />
          <p className="text-muted-foreground">You haven't earned a certificate yet. Finish every lesson in a course to get one.</p>
          <Link href="/courses">
            <Button>Browse courses</Button>
          </Link>
        </div>
      )}

      {data && data.length > 0 && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {data.map((certificate) => {
            const downloading = isPending && variables?.id === certificate.id;

            return (
              <Card key={certificate.id}>
                <CardHeader>
                  <div className="flex items-start gap-3">
                    <Award className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                    <div className="min-w-0">
                      <CardTitle className="text-base leading-snug">{certificate.course_title}</CardTitle>
                      <p className="mt-1 text-sm text-muted-foreground">Taught by {certificate.instructor_name}</p>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-1 text-sm">
                    <p className="text-muted-foreground">Issued {certificate.issued_at_human}</p>
                    <p className="break-all font-mono text-xs">{certificate.certificate_number}</p>
                  </div>

                  <div className="flex flex-wrap gap-2">
                    <Button size="sm" disabled={downloading} onClick={() => download(certificate)}>
                      <Download className="mr-1 h-4 w-4" />
                      {downloading ? "Downloading..." : certificate.pdf_ready ? "Download PDF" : "Get PDF"}
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => copyToClipboard(certificate.certificate_number, "Certificate number")}
                    >
                      <Copy className="mr-1 h-4 w-4" /> Copy number
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => copyToClipboard(certificate.verification_url, "Verification link")}
                    >
                      <Link2 className="mr-1 h-4 w-4" /> Copy link
                    </Button>
                  </div>

                  {!certificate.pdf_ready && (
                    <p className="text-xs text-muted-foreground">
                      Your PDF is being prepared. Press “Get PDF” again in a moment if it doesn't download.
                    </p>
                  )}
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}