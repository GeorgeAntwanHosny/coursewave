"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Progress } from "@/components/ui/progress";
import { Button } from "@/components/ui/button";
import { CheckCircle2, Circle, PlayCircle, FileText, HelpCircle, X } from "lucide-react";
import { cn } from "@/lib/utils";
import type { CourseLearn } from "@/hooks/use-learning";

export function CourseSidebar({
  course,
  completionPercent,
  onNavigate,
  onClose,
}: {
  course: CourseLearn;
  completionPercent: number;
  onNavigate?: () => void;
  onClose?: () => void;
}) {
  const pathname = usePathname();
  const segments = pathname.split("/").filter(Boolean);
  const activeLessonId = segments[2] ? Number(segments[2]) : null;
  const isQuizActive = segments[3] === "quiz";

  const activeModuleId = course.modules.find((m) => m.lessons.some((l) => l.id === activeLessonId))?.id;
  const defaultOpen = activeModuleId
    ? [`module-${activeModuleId}`]
    : course.modules[0]
      ? [`module-${course.modules[0].id}`]
      : [];

  return (
    <div className="flex h-full flex-col bg-card">
      <div className="flex items-start justify-between gap-2 border-b border-border p-4">
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold" title={course.title}>
            {course.title}
          </p>
          <div className="mt-2 flex items-center gap-2">
            <Progress value={completionPercent} className="h-1.5" />
            <span className="shrink-0 text-xs text-muted-foreground">{completionPercent}%</span>
          </div>
        </div>
        {onClose && (
          <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0" onClick={onClose} aria-label="Close course menu">
            <X className="h-4 w-4" />
          </Button>
        )}
      </div>

      <div className="flex-1 overflow-y-auto p-2">
        <Accordion type="multiple" defaultValue={defaultOpen} className="w-full">
          {course.modules.map((module) => {
            const total = module.lessons.length;
            const completed = module.lessons.filter((l) => l.status === "completed").length;
            const modulePercent = total > 0 ? Math.round((completed / total) * 100) : 0;

            return (
              <AccordionItem key={module.id} value={`module-${module.id}`} className="border-b-0">
                <AccordionTrigger className="rounded-md px-2 py-2.5 text-sm hover:bg-muted hover:no-underline">
                  <div className="flex w-full flex-col items-start gap-1.5 pr-2 text-left">
                    <span className="font-medium">{module.title}</span>
                    <div className="flex w-full items-center gap-2">
                      <Progress value={modulePercent} className="h-1" />
                      <span className="shrink-0 text-[11px] text-muted-foreground">
                        {completed}/{total}
                      </span>
                    </div>
                  </div>
                </AccordionTrigger>
                <AccordionContent className="pb-1">
                  <div className="flex flex-col gap-0.5 pl-2">
                    {module.lessons.map((lesson) => {
                      const isActive = lesson.id === activeLessonId && !isQuizActive;
                      return (
                        <Link
                          key={lesson.id}
                          href={`/learn/${course.slug}/${lesson.id}`}
                          onClick={onNavigate}
                          aria-current={isActive ? "page" : undefined}
                          className={cn(
                            "flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors",
                            isActive
                              ? "bg-primary/10 font-medium text-primary"
                              : "text-muted-foreground hover:bg-muted hover:text-foreground"
                          )}
                        >
                          {lesson.status === "completed" ? (
                            <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-green-500" />
                          ) : (
                            <Circle className="h-3.5 w-3.5 shrink-0" />
                          )}
                          {lesson.type === "video" ? (
                            <PlayCircle className="h-3.5 w-3.5 shrink-0" />
                          ) : (
                            <FileText className="h-3.5 w-3.5 shrink-0" />
                          )}
                          <span className="truncate">{lesson.title}</span>
                        </Link>
                      );
                    })}

                    {module.lessons
                      .filter((l) => l.has_quiz && l.quiz_id)
                      .map((lesson) => {
                        const isActive = lesson.id === activeLessonId && isQuizActive;
                        return (
                          <Link
                            key={`quiz-${lesson.quiz_id}`}
                            href={`/learn/${course.slug}/${lesson.id}/quiz`}
                            onClick={onNavigate}
                            aria-current={isActive ? "page" : undefined}
                            className={cn(
                              "flex items-center gap-2 rounded-md px-2 py-2 pl-7 text-sm transition-colors",
                              isActive
                                ? "bg-primary/10 font-medium text-primary"
                                : "text-muted-foreground hover:bg-muted hover:text-foreground"
                            )}
                          >
                            <HelpCircle className="h-3.5 w-3.5 shrink-0" />
                            <span className="truncate">Quiz: {lesson.title}</span>
                          </Link>
                        );
                      })}
                  </div>
                </AccordionContent>
              </AccordionItem>
            );
          })}
        </Accordion>
      </div>
    </div>
  );
}