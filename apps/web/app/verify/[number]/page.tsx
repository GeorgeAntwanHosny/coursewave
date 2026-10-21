"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { CheckCircle2, XCircle } from "lucide-react";
import { useVerifyCertificate } from "@/hooks/use-certificates";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

export default function VerifyCertificatePage() {
  const params = useParams<{ number: string }>();
  const number = decodeURIComponent(params.number ?? "");
  const { data, isLoading, isError } = useVerifyCertificate(number);

  return (
    <div className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-lg items-center justify-center px-4 py-12">
      <Card className="w-full">
        {isLoading && (
          <CardContent className="space-y-4 py-10">
            <Skeleton className="mx-auto h-12 w-12 rounded-full" />
            <Skeleton className="mx-auto h-5 w-2/3" />
            <Skeleton className="mx-auto h-4 w-1/2" />
          </CardContent>
        )}

        {!isLoading && data && (
          <>
            <CardHeader className="items-center text-center">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
              <CardTitle className="mt-2">Valid certificate</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-center">
              <div>
                <p className="text-sm text-muted-foreground">Awarded to</p>
                <p className="text-lg font-semibold">{data.student_name}</p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">For completing</p>
                <p className="font-medium">{data.course_title}</p>
              </div>
              <p className="text-sm text-muted-foreground">
                Taught by {data.instructor_name} · Issued {data.issued_at_human}
              </p>
              <p className="break-all font-mono text-xs text-muted-foreground">{data.certificate_number}</p>
            </CardContent>
          </>
        )}

        {!isLoading && (isError || !data) && (
          <CardContent className="space-y-4 py-10 text-center">
            <XCircle className="mx-auto h-12 w-12 text-destructive" />
            <h1 className="text-xl font-semibold">We couldn't verify this certificate</h1>
            <p className="text-sm text-muted-foreground">
              No certificate matches <span className="break-all font-mono">{number}</span>. Check the number and try again.
            </p>
            <Link href="/">
              <Button variant="outline">Back to CourseWave</Button>
            </Link>
          </CardContent>
        )}
      </Card>
    </div>
  );
}