"use client";

import { useState } from "react";
import { useParams } from "next/navigation";
import { useCourseLearn } from "@/hooks/use-learning";
import { CourseSidebar } from "@/components/learn/course-sidebar";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { Menu } from "lucide-react";
import { cn } from "@/lib/utils";

export default function CourseLearnLayout({ children }: { children: React.ReactNode }) {
  const params = useParams<{ slug: string }>();
  const { data, isLoading, isError } = useCourseLearn(params.slug);
  const [sidebarOpen, setSidebarOpen] = useState(true);

  if (isLoading) {
    return (
      <div className="mx-auto max-w-6xl px-4 py-6">
        <div className="grid grid-cols-1 gap-6 md:grid-cols-[300px_1fr]">
          <Skeleton className="h-96 w-full" />
          <Skeleton className="h-96 w-full" />
        </div>
      </div>
    );
  }

  if (isError || !data) {
    return <div className="mx-auto max-w-3xl px-4 py-10">{children}</div>;
  }

  return (
    // h-[calc(100vh-4rem)] pins this whole section to exactly the viewport
    // height below the 4rem navbar, and overflow-hidden on this flex row is
    // what makes the sidebar and content each scroll independently instead
    // of the page itself growing taller than the screen.
    <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
      {/* Mobile backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 top-16 z-30 bg-black/40 md:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Sidebar: fixed full-height overlay on mobile (out of normal flow);
          a real flex child with an animated width on desktop, so it sits
          beside the content column rather than stacking above it. */}
      <aside
        className={cn(
          "fixed inset-y-16 left-0 z-40 w-80 max-w-[85vw] overflow-hidden border-r border-border shadow-lg transition-transform duration-300 ease-in-out",
          sidebarOpen ? "translate-x-0" : "-translate-x-full",
          "md:static md:inset-auto md:z-0 md:h-full md:shrink-0 md:translate-x-0 md:shadow-none md:transition-[width,border-color] md:duration-300",
          sidebarOpen ? "md:w-80 md:border-r" : "md:w-0 md:border-r-0"
        )}
      >
        <div className="h-full w-80">
          <CourseSidebar
            course={data.course}
            completionPercent={data.completion_percent}
            onNavigate={() => {
              if (window.innerWidth < 768) setSidebarOpen(false);
            }}
            onClose={() => setSidebarOpen(false)}
          />
        </div>
      </aside>

      {/* Content column: flex-1 so it fills whatever width the sidebar
          leaves, min-w-0 so long content can't force the row to overflow,
          and its own overflow-y-auto so it scrolls independently of the
          sidebar's own internal scroll area. */}
      <div className="flex min-w-0 flex-1 flex-col">
        <div className="flex shrink-0 items-center gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/60">
          <Button variant="outline" size="icon" className="h-8 w-8 shrink-0" onClick={() => setSidebarOpen((v) => !v)}>
            <Menu className="h-4 w-4" />
            <span className="sr-only">Toggle course menu</span>
          </Button>
          <h2 className="truncate text-sm font-medium text-muted-foreground">{data.course.title}</h2>
        </div>

        <div className="flex-1 overflow-y-auto">
          <div className="mx-auto max-w-3xl px-4 py-6">{children}</div>
        </div>
      </div>
    </div>
  );
}