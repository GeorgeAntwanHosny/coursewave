"use client";

import { useEffect, useRef } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import { useLesson, useRecordProgress } from "@/hooks/use-learning";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { CheckCircle2, ArrowLeft, ArrowRight, ExternalLink, HelpCircle } from "lucide-react";
import { toast } from "sonner";

const HEARTBEAT_INTERVAL_MS = 15000;

export default function LessonPage() {
  const params = useParams<{ slug: string; lessonId: string }>();
  const lessonId = Number(params.lessonId);
  const { data: lesson, isLoading, isError } = useLesson(lessonId);
  const { mutate: recordProgress, isPending: isMarking } = useRecordProgress(lessonId);

  const videoRef = useRef<HTMLVideoElement>(null);
  const lastReportedTimeRef = useRef(0);
  const heartbeatRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    return () => {
      if (heartbeatRef.current) clearInterval(heartbeatRef.current);
    };
  }, []);

  useEffect(() => {
    // Reset per-lesson refs when navigating between lessons via the sidebar,
    // since this component instance is reused across client-side navigations.
    lastReportedTimeRef.current = 0;
    if (heartbeatRef.current) {
      clearInterval(heartbeatRef.current);
      heartbeatRef.current = null;
    }
  }, [lessonId]);

  const startHeartbeat = () => {
    if (heartbeatRef.current) return;
    heartbeatRef.current = setInterval(() => {
      const video = videoRef.current;
      if (!video) return;
      const delta = Math.round(video.currentTime - lastReportedTimeRef.current);
      if (delta > 0) {
        recordProgress({ watched_seconds: delta });
        lastReportedTimeRef.current = video.currentTime;
      }
    }, HEARTBEAT_INTERVAL_MS);
  };

  const stopHeartbeat = () => {
    if (heartbeatRef.current) {
      clearInterval(heartbeatRef.current);
      heartbeatRef.current = null;
    }
  };

  const handleEnded = () => {
    stopHeartbeat();
    recordProgress({ completed: true });
    toast.success("Lesson marked as complete.");
  };

  const handleMarkComplete = () => {
    stopHeartbeat();
    recordProgress(
      { completed: true },
      { onSuccess: () => toast.success("Lesson marked as complete.") }
    );
  };

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-6 w-1/3" />
        <Skeleton className="h-80 w-full" />
      </div>
    );
  }

  if (isError || !lesson) {
    return (
      <div className="py-16 text-center">
        <p className="text-destructive">Couldn't load this lesson.</p>
      </div>
    );
  }

  const isCompleted = lesson.progress.status === "completed";

  return (
    <div>
      <Link href={`/learn/${lesson.course.slug}`} className="text-sm text-muted-foreground hover:underline">
        ← Back to {lesson.course.title}
      </Link>

      <h1 className="mt-2 text-xl font-semibold sm:text-2xl">{lesson.title}</h1>

      {isCompleted && (
        <div className="mt-2 flex items-center gap-1 text-sm text-green-600">
          <CheckCircle2 className="h-4 w-4" /> Completed
        </div>
      )}

      <div className="mt-6">
        {lesson.type === "video" && lesson.video_url && (
          <video
            key={lesson.id}
            ref={videoRef}
            src={lesson.video_url}
            controls
            className="w-full rounded-lg bg-black"
            onPlay={startHeartbeat}
            onPause={stopHeartbeat}
            onEnded={handleEnded}
          />
        )}

        {lesson.type === "text" && (
          <Card>
            <CardContent className="max-w-none py-6 text-sm leading-relaxed [&_p]:mb-4 [&_h2]:mb-2 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5">
              <div dangerouslySetInnerHTML={{ __html: lesson.body ?? "" }} />
            </CardContent>
          </Card>
        )}
      </div>

      {!isCompleted && (
        <Button className="mt-4" onClick={handleMarkComplete} disabled={isMarking}>
          {isMarking ? "Marking..." : "Mark as complete"}
        </Button>
      )}

      {lesson.quiz_id && (
        <Card className="mt-6">
          <CardContent className="flex items-center justify-between py-4">
            <div className="flex items-center gap-2">
              <HelpCircle className="h-4 w-4 text-muted-foreground" />
              <div>
                <p className="font-medium">Test your knowledge</p>
                <p className="text-sm text-muted-foreground">This lesson has a quick quiz.</p>
              </div>
            </div>
            <Link href={`/learn/${lesson.course.slug}/${lesson.id}/quiz`}>
              <Button variant="outline">Take quiz</Button>
            </Link>
          </CardContent>
        </Card>
      )}

      {lesson.resource_links.length > 0 && (
        <Card className="mt-6">
          <CardHeader>
            <CardTitle className="text-base">Resources</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {lesson.resource_links.map((link) => (
              <a
                key={link.id}
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-2 text-sm text-primary hover:underline"
              >
                <ExternalLink className="h-4 w-4" /> {link.title}
              </a>
            ))}
          </CardContent>
        </Card>
      )}

      <div className="mt-8 flex items-center justify-between">
        {lesson.previous_lesson_id ? (
          <Link href={`/learn/${lesson.course.slug}/${lesson.previous_lesson_id}`}>
            <Button variant="outline">
              <ArrowLeft className="mr-1 h-4 w-4" /> Previous
            </Button>
          </Link>
        ) : (
          <span />
        )}
        {lesson.next_lesson_id ? (
          <Link href={`/learn/${lesson.course.slug}/${lesson.next_lesson_id}`}>
            <Button>
              Next <ArrowRight className="ml-1 h-4 w-4" />
            </Button>
          </Link>
        ) : (
          <span />
        )}
      </div>
    </div>
  );
}