"use client";

import { useParams } from "next/navigation";
import { useCourse } from "@/hooks/use-courses";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PlayCircle, FileText, HelpCircle } from "lucide-react";
import Image from "next/image";

export default function CourseDetailPage() {
  const params = useParams<{ slug: string }>();
  const { data: course, isLoading, isError } = useCourse(params.slug);

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl space-y-4 px-4 py-10">
        <Skeleton className="h-48 w-full rounded-lg" />
        <Skeleton className="h-8 w-2/3" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-5/6" />
      </div>
    );
  }

  if (isError || !course) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10">
        <p className="text-destructive">This course couldn't be found.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      {course.thumbnail_url && (
        <Image
          src={course.thumbnail_url}
          alt={course.title}
          className="mb-6 h-48 w-full rounded-lg object-cover sm:h-64"
        />
      )}
      <h1 className="text-2xl font-semibold sm:text-3xl">{course.title}</h1>
      <p className="mt-1 text-sm text-muted-foreground">By {course.instructor_name}</p>
      <p className="mt-4 text-muted-foreground">{course.description}</p>

      <div className="mt-8 space-y-4">
        {course.modules.length === 0 && (
          <p className="text-muted-foreground">Content for this course is coming soon.</p>
        )}
        {course.modules.map((module) => (
          <Card key={module.id}>
            <CardHeader>
              <CardTitle className="text-base">{module.title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {module.lessons.map((lesson) => (
                <div key={lesson.id} className="flex items-center gap-2 text-sm text-muted-foreground">
                  {lesson.type === "video" ? (
                    <PlayCircle className="h-4 w-4 shrink-0" />
                  ) : (
                    <FileText className="h-4 w-4 shrink-0" />
                  )}
                  <span>{lesson.title}</span>
                  {lesson.has_quiz && <HelpCircle className="h-4 w-4 shrink-0 text-primary" />}
                </div>
              ))}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}