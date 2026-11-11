"use client";

import Link from "next/link";
import { ArrowRight, GraduationCap } from "lucide-react";
import { usePublicCourses, type LandingCourse } from "@/hooks/use-landing-data";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";
import { Button } from "@/components/ui/button";
import { LinkButton } from "@/components/ui/link-button";
import { Skeleton } from "@/components/ui/skeleton";

function hueFor(text: string) {
  let hash = 0;
  for (let i = 0; i < text.length; i++) hash = (hash * 31 + text.charCodeAt(i)) % 360;
  return hash;
}

function CourseCard({ course }: { course: LandingCourse }) {
  const hue = hueFor(course.title);

  return (
    <Link
      href={`/courses/${course.slug}`}
      className="lp-card group block h-full overflow-hidden rounded-2xl border border-border bg-card"
    >
      <div
        className="relative flex h-32 items-end p-4"
        style={{ backgroundImage: `linear-gradient(135deg, hsl(${hue} 80% 58%), hsl(${(hue + 45) % 360} 85% 42%))` }}
      >
        <GraduationCap className="absolute right-4 top-4 h-6 w-6 text-white/70" aria-hidden="true" />
        {course.lessonsCount !== null && (
          <span className="rounded-full bg-black/30 px-2.5 py-0.5 text-xs text-white backdrop-blur">
            {course.lessonsCount} lessons
          </span>
        )}
      </div>

      <div className="p-5">
        <h3 className="line-clamp-2 text-base font-semibold leading-snug">{course.title}</h3>
        {course.instructorName && <p className="mt-1 text-sm text-muted-foreground">with {course.instructorName}</p>}
        {course.description && (
          <p className="mt-3 line-clamp-2 text-sm leading-relaxed text-muted-foreground">{course.description}</p>
        )}
        <span className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary">
          View course
          <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
        </span>
      </div>
    </Link>
  );
}

export function CoursesShowcase() {
  const { data, isLoading, isError, refetch } = usePublicCourses();

  // Nothing to show once loaded: leave the section out entirely rather than show an empty shelf.
  if (!isLoading && !isError && (!data || data.length === 0)) return null;

  return (
    <section id="courses" className="lp-section bg-muted/30 px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="courses-title">
      <div className="mx-auto max-w-7xl">
        <Reveal>
          <SectionHeading
            eyebrow="The catalog"
            title={<span id="courses-title">Start with something real</span>}
            description="A taste of what your subscription unlocks."
          />
        </Reveal>

        {isLoading && (
          <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="overflow-hidden rounded-2xl border border-border bg-card">
                <Skeleton className="h-32 w-full rounded-none" />
                <div className="space-y-3 p-5">
                  <Skeleton className="h-5 w-3/4" />
                  <Skeleton className="h-4 w-1/2" />
                  <Skeleton className="h-4 w-full" />
                </div>
              </div>
            ))}
          </div>
        )}

        {isError && (
          <div className="mt-14 space-y-3 text-center">
            <p className="text-sm text-muted-foreground">We couldn’t load the catalog right now.</p>
            <div className="flex justify-center gap-3">
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                Try again
              </Button>
              <LinkButton href="/courses" size="sm">
                Open the catalog
              </LinkButton>
            </div>
          </div>
        )}

        {data && data.length > 0 && (
          <>
            <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {data.slice(0, 6).map((course, index) => (
                <Reveal key={course.id} delay={index * 70}>
                  <CourseCard course={course} />
                </Reveal>
              ))}
            </div>

            <div className="mt-10 text-center">
              <LinkButton href="/courses" variant="outline" size="lg">
                See all courses
                <ArrowRight className="h-4 w-4" />
              </LinkButton>
            </div>
          </>
        )}
      </div>
    </section>
  );
}