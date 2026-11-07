"use client";

import Link from "next/link";
import { CalendarClock, ExternalLink, Video } from "lucide-react";
import { LiveSessionItem } from "@/hooks/use-live-sessions";
import { useNow } from "@/hooks/use-now";
import { describeCountdown, formatSessionStart, formatTimeOnly } from "@/lib/live-session-time";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export function LiveSessionCard({ session, showCourse = true }: { session: LiveSessionItem; showCourse?: boolean }) {
  const now = useNow();
  const isLive = session.status === "live";

  return (
    <Card className={cn(isLive && "border-primary/50 bg-primary/5")}>
      <CardContent className="space-y-3 py-4">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            <h3 className="font-semibold leading-snug">{session.title}</h3>
            {showCourse && (
              <Link
                href={`/learn/${session.course.slug}`}
                className="text-sm text-muted-foreground hover:underline"
              >
                {session.course.title}
              </Link>
            )}
            <p className="text-sm text-muted-foreground">with {session.instructor_name}</p>
          </div>

          {isLive ? (
            <Badge className="gap-1.5">
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-primary-foreground" />
              Live now
            </Badge>
          ) : (
            <Badge variant="secondary">{describeCountdown(session.starts_at, now)}</Badge>
          )}
        </div>

        <p className="flex items-center gap-2 text-sm">
          <CalendarClock className="h-4 w-4 shrink-0 text-muted-foreground" />
          <span>
            {formatSessionStart(session.starts_at)} · {session.duration_minutes} min
          </span>
        </p>

        {session.description && <p className="text-sm text-muted-foreground">{session.description}</p>}

        {session.can_join && session.join_url ? (
          <Button 
            nativeButton={false}
             render={<a href={session.join_url} target="_blank" rel="noopener noreferrer">
              <Video className="mr-2 h-4 w-4" />
              Join session
              <ExternalLink className="ml-2 h-3.5 w-3.5" />
            </a>}
            className="w-full sm:w-auto">
          </Button>
        ) : (
          <Button disabled variant="outline" className="w-full sm:w-auto">
            <Video className="mr-2 h-4 w-4" />
            Link opens at {formatTimeOnly(session.join_opens_at)}
          </Button>
        )}
      </CardContent>
    </Card>
  );
}