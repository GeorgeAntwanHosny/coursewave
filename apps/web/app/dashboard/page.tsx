"use client";

import { useState } from "react";
import Link from "next/link";
import { useMe } from "@/hooks/use-auth";
import { useCancelSubscription, useCurrentSubscription } from "@/hooks/use-subscriptions";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

export default function DashboardPage() {
  const { data: user, isLoading, isError } = useMe();
  const { data: subscription, isLoading: isSubLoading } = useCurrentSubscription();
  const { mutate: cancelSubscription, isPending: isCanceling } = useCancelSubscription();
  const [confirmOpen, setConfirmOpen] = useState(false);

  if (isLoading) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-10 space-y-4">
        <Skeleton className="h-8 w-1/3" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  if (isError || !user) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-10">
        <p className="text-destructive">Couldn't load your account. Try logging in again.</p>
      </div>
    );
  }

  const hasRefund = (subscription?.refundable_cents ?? 0) > 0;

  return (
    <div className="mx-auto max-w-2xl space-y-6 px-4 py-10">
      <Card>
        <CardHeader>
          <CardTitle>Welcome, {user.name}</CardTitle>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          <p>Email: {user.email}</p>
          <p>Role: {user.role}</p>
        </CardContent>
      </Card>

      {user.role === "student" && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Your subscription</CardTitle>
          </CardHeader>
          <CardContent>
            {isSubLoading && <Skeleton className="h-16 w-full" />}

            {!isSubLoading && subscription && (
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1 text-sm text-muted-foreground">
                  <p className="font-medium text-foreground">{subscription.plan.name}</p>
                  <p>Access until {subscription.ends_at_human}</p>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full sm:w-auto"
                  disabled={isCanceling}
                  onClick={() => setConfirmOpen(true)}
                >
                  {isCanceling ? "Canceling..." : "Cancel subscription"}
                </Button>
              </div>
            )}

            {!isSubLoading && !subscription && (
              <div className="space-y-3">
                <p className="text-sm text-muted-foreground">You don't have an active subscription yet.</p>
                <Link href="/plans">
                  <Button size="sm">Browse plans</Button>
                </Link>
              </div>
            )}
          </CardContent>
        </Card>
      )}

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel your subscription?</AlertDialogTitle>
            <AlertDialogDescription>
              You'll lose access to all courses immediately.{" "}
              {hasRefund
                ? `The ${subscription?.refundable_human} for your unused days will be refunded to your original payment method.`
                : "There are no unused days left to refund."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isCanceling}>Keep subscription</AlertDialogCancel>
            <AlertDialogAction
              disabled={isCanceling}
              onClick={(e) => {
                e.preventDefault(); // keep the dialog open until the request finishes
                cancelSubscription(undefined, { onSuccess: () => setConfirmOpen(false) });
              }}
            >
              {isCanceling ? "Canceling..." : "Yes, cancel"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}