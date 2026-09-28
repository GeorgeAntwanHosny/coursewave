"use client";

import { useMe } from "@/hooks/use-auth";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

export default function DashboardPage() {
  const { data: user, isLoading, isError } = useMe();

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
    <div className="mx-auto max-w-2xl px-4 py-10">
      <Card>
        <CardHeader>
          <CardTitle>Welcome, {user.name}</CardTitle>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          <p>Email: {user.email}</p>
          <p>Role: {user.role}</p>
        </CardContent>
      </Card>
    </div>
  );
}