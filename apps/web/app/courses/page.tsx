"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { useCourses } from "@/hooks/use-courses";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import Image from "next/image";

export default function CoursesPage() {
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(search);
    }, 600);

    return () => clearTimeout(timer);
  }, [search]);

  const { data, isLoading, isError } = useCourses(debouncedSearch);

  return (
    <div className="mx-auto max-w-6xl px-4 py-10">
      <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-semibold sm:text-3xl">Browse courses</h1>
        <Input
          placeholder="Search courses..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="sm:max-w-xs"
        />
      </div>

      {isLoading && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="space-y-3">
              <Skeleton className="h-40 w-full rounded-lg" />
              <Skeleton className="h-5 w-3/4" />
              <Skeleton className="h-4 w-1/2" />
            </div>
          ))}
        </div>
      )}

      {isError && (
        <p className="text-destructive">Couldn't load courses. Please try again shortly.</p>
      )}

      {!isLoading && !isError && data?.items.length === 0 && (
        <p className="text-muted-foreground">No courses match your search yet.</p>
      )}

      {!isLoading && !isError && data && data.items.length > 0 && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {data.items.map((course) => (
            <Link key={course.id} href={`/courses/${course.slug}`}>
              <Card className="h-full overflow-hidden transition-shadow hover:shadow-md">
                {course.thumbnail_url ? (
                  <Image
                    src={course.thumbnail_url}
                    alt={course.title}
                    width={400}
                    height={160}
                    className="h-40 w-full object-cover"
                  />
                ) : (
                  <div className="flex h-40 w-full items-center justify-center bg-muted text-muted-foreground">
                    No image
                  </div>
                )}
                <CardHeader>
                  <CardTitle className="line-clamp-2 text-base">{course.title}</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-muted-foreground">
                  <p>By {course.instructor_name}</p>
                  <p>{course.modules_count} modules</p>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}