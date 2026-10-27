"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { Award } from "lucide-react";
import { useCourseLearn } from "@/hooks/use-learning";
import { useMyCertificates } from "@/hooks/use-certificates";
import { useCourseLiveSessions } from "@/hooks/use-live-sessions";
import { LiveSessionCard } from "@/components/live/live-session-card";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";

export default function CourseLearnPage() {
  const params = useParams<{ slug: string }>();
  const { data, isLoading, isError, error } = useCourseLearn(params.slug);
  const { data: certificates } = useMyCertificates();
  const { data: liveSessions } = useCourseLiveSessions(params.slug);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-2/3" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  const status = (error as any)?.response?.status;

  if (isError && status === 403) {
    return (
      <div className="mx-auto max-w-md space-y-4 py-16 text-center">
        <h1 className="text-xl font-semibold">Subscribe to access this course</h1>
        <p className="text-muted-foreground">
          An active subscription gives you access to every course on CourseWave.
        </p>
        <Link href="/plans">
          <Button>View plans</Button>
        </Link>
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="py-16 text-center">
        <p className="text-destructive">Couldn't load this course.</p>
      </div>
    );
  }

  const { course, completion_percent } = data;
  const firstLesson = course.modules[0]?.lessons[0];
  const totalLessons = course.modules.reduce((sum, m) => sum + m.lessons.length, 0);
  const certificate = certificates?.find((c) => c.course_slug === course.slug);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold sm:text-3xl">{course.title}</h1>
        <p className="mt-1 text-sm text-muted-foreground">By {course.instructor_name}</p>
      </div>

      {certificate && (
        <Card className="border-primary/40 bg-primary/5">
          <CardContent className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
              <Award className="h-5 w-5 shrink-0 text-primary" />
              <div>
                <p className="font-medium">You completed this course</p>
                <p className="text-sm text-muted-foreground">Certificate issued {certificate.issued_at_human}.</p>
              </div>
            </div>
            <Link href="/certificates">
              <Button size="sm" className="w-full sm:w-auto">
                View certificate
              </Button>
            </Link>
          </CardContent>
        </Card>
      )}

      {liveSessions && liveSessions.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-lg font-semibold">Live sessions</h2>
          {liveSessions.map((session) => (
            <LiveSessionCard key={session.id} session={session} showCourse={false} />
          ))}
        </div>
      )}

      <div className="space-y-2">
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Course progress</span>
          <span className="font-medium">{completion_percent}%</span>
        </div>
        <Progress value={completion_percent} />
        <p className="text-xs text-muted-foreground">
          {course.modules.length} modules · {totalLessons} lessons
        </p>
      </div>

      {firstLesson && (
        <Link href={`/learn/${course.slug}/${firstLesson.id}`}>
          <Button size="lg">{completion_percent > 0 ? "Continue learning" : "Start learning"}</Button>
        </Link>
      )}

      <p className="text-sm text-muted-foreground">
        Use the menu icon above to browse all lessons and quizzes.
      </p>
    </div>
  );
}