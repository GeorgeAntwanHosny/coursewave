"use client";

import Link from "next/link";
import { useMe } from "@/hooks/use-auth";
import { useCurrentSubscription } from "@/hooks/use-subscriptions";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";

export default function DashboardPage() {
  const { data: user, isLoading, isError } = useMe();
  const { data: subscription, isLoading: isSubLoading } = useCurrentSubscription();

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
              <div className="space-y-1 text-sm text-muted-foreground">
                <p className="text-foreground font-medium">{subscription.plan.name}</p>
                <p>Access until {subscription.ends_at_human}</p>
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
    </div>
  );
}