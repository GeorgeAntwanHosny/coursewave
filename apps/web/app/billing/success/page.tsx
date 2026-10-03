"use client";

import { useEffect, useRef } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import { useConfirmSubscription } from "@/hooks/use-subscriptions";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { CheckCircle2, XCircle } from "lucide-react";

export default function BillingSuccessPage() {
  const searchParams = useSearchParams();
  const sessionId = searchParams.get("session_id");
  const { mutate, isPending, isSuccess, isError, data, error } = useConfirmSubscription();
  const hasRun = useRef(false);

  useEffect(() => {
    if (sessionId && !hasRun.current) {
      hasRun.current = true;
      mutate(sessionId);
    }
  }, [sessionId, mutate]);

  if (!sessionId) {
    return (
      <div className="mx-auto max-w-md px-4 py-16 text-center">
        <p className="text-destructive">Missing checkout session. Nothing to confirm.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center">
      {isPending && (
        <div className="space-y-4">
          <Skeleton className="mx-auto h-12 w-12 rounded-full" />
          <p className="text-muted-foreground">Confirming your subscription...</p>
        </div>
      )}

      {isSuccess && data && (
        <div className="space-y-4">
          <CheckCircle2 className="mx-auto h-12 w-12 text-green-500" />
          <h1 className="text-2xl font-semibold">You're subscribed!</h1>
          <p className="text-muted-foreground">
            {data.plan.name} — active through {data.ends_at_human}
          </p>
          <Link href="/dashboard">
            <Button>Go to dashboard</Button>
          </Link>
        </div>
      )}

      {isError && (
        <div className="space-y-4">
          <XCircle className="mx-auto h-12 w-12 text-destructive" />
          <h1 className="text-2xl font-semibold">Couldn't confirm subscription</h1>
          <p className="text-sm text-muted-foreground">
            {(error as any)?.response?.data?.message ?? "Something went wrong. Please contact support."}
          </p>
          <Link href="/plans">
            <Button variant="outline">Back to plans</Button>
          </Link>
        </div>
      )}
    </div>
  );
}