"use client";

import Link from "next/link";
import { Video } from "lucide-react";
import { useLiveSessions } from "@/hooks/use-live-sessions";
import { LiveSessionCard } from "@/components/live/live-session-card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";

export default function LiveSessionsPage() {
  const { data, isLoading, isError, error, refetch } = useLiveSessions();
  const forbidden = (error as any)?.response?.status === 403;

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="mb-2 text-2xl font-semibold sm:text-3xl">Live sessions</h1>
      <p className="mb-8 text-sm text-muted-foreground">
        Upcoming sessions for the courses you're taking. The join link opens a few minutes before each one starts, and
        times are shown in your own time zone.
      </p>

      {isLoading && (
        <div className="space-y-4">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-44 w-full rounded-lg" />
          ))}
        </div>
      )}

      {isError && forbidden && (
        <div className="space-y-4 py-12 text-center">
          <h2 className="text-lg font-semibold">Subscribe to join live sessions</h2>
          <p className="text-muted-foreground">An active subscription unlocks every course and its live sessions.</p>
          <Link href="/plans">
            <Button>View plans</Button>
          </Link>
        </div>
      )}

      {isError && !forbidden && (
        <div className="space-y-3 text-center">
          <p className="text-destructive">Couldn't load your live sessions.</p>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            Try again
          </Button>
        </div>
      )}

      {!isLoading && !isError && data && data.length === 0 && (
        <div className="space-y-4 py-12 text-center">
          <Video className="mx-auto h-10 w-10 text-muted-foreground" />
          <p className="text-muted-foreground">
            No upcoming live sessions. Start a lesson in a course and you'll be told when its instructor schedules one.
          </p>
          <Link href="/courses">
            <Button>Browse courses</Button>
          </Link>
        </div>
      )}

      {data && data.length > 0 && (
        <div className="space-y-4">
          {data.map((session) => (
            <LiveSessionCard key={session.id} session={session} />
          ))}
        </div>
      )}
    </div>
  );
}