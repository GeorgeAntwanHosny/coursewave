"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCourseLearn } from "@/hooks/use-learning";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";

export default function CourseLearnPage() {
  const params = useParams<{ slug: string }>();
  const { data, isLoading, isError, error } = useCourseLearn(params.slug);

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

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold sm:text-3xl">{course.title}</h1>
        <p className="mt-1 text-sm text-muted-foreground">By {course.instructor_name}</p>
      </div>

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