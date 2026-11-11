import { Award, Banknote, Check, Play, Video } from "lucide-react";

/** A decorative mock of the learning screen. Not interactive, and hidden from assistive technology. */
export function ProductPreview() {
  const lessons = [
    { title: "Routing basics", done: true },
    { title: "Controllers", done: true },
    { title: "Eloquent models", current: true },
    { title: "Relationships" },
  ];

  return (
    <div className="relative mx-auto w-full max-w-xl" aria-hidden="true">
      {/* Browser-style window */}
      <div className="lp-window overflow-hidden rounded-2xl border border-border bg-card">
        <div className="flex items-center gap-2 border-b border-border bg-muted/50 px-4 py-3">
          <span className="h-2.5 w-2.5 rounded-full bg-red-400/80" />
          <span className="h-2.5 w-2.5 rounded-full bg-amber-400/80" />
          <span className="h-2.5 w-2.5 rounded-full bg-emerald-400/80" />
          <span className="ml-3 truncate text-xs text-muted-foreground">Laravel for Beginners</span>
        </div>

        <div className="grid sm:grid-cols-[9.5rem_1fr]">
          <div className="hidden space-y-1 border-r border-border bg-muted/30 p-3 sm:block">
            <p className="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
              Core concepts
            </p>
            {lessons.map((lesson) => (
              <div
                key={lesson.title}
                className={
                  "flex items-center gap-2 rounded-md px-2 py-1.5 text-xs " +
                  (lesson.current ? "bg-primary/10 font-medium text-foreground" : "text-muted-foreground")
                }
              >
                <span
                  className={
                    "flex h-4 w-4 shrink-0 items-center justify-center rounded-full border " +
                    (lesson.done ? "lp-tone-green border-transparent" : "border-border")
                  }
                >
                  {lesson.done && <Check className="h-2.5 w-2.5" />}
                  {lesson.current && <span className="h-1.5 w-1.5 rounded-full bg-primary" />}
                </span>
                <span className="truncate">{lesson.title}</span>
              </div>
            ))}
          </div>

          <div className="p-4 sm:p-5">
            <div className="lp-video relative flex aspect-video items-center justify-center rounded-xl">
              <span className="flex h-14 w-14 items-center justify-center rounded-full bg-white/90 text-slate-900 shadow-lg">
                <Play className="ml-0.5 h-6 w-6 fill-current" />
              </span>
              <span className="absolute bottom-2 right-3 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">
                12:40
              </span>
            </div>

            <div className="mt-4 space-y-2">
              <p className="text-sm font-semibold">Eloquent models</p>
              <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>Lesson 7 of 11</span>
                <span>64%</span>
              </div>
              <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div className="lp-bar-fill h-full rounded-full" style={{ width: "64%" }} />
              </div>
              <div className="flex flex-wrap gap-1.5 pt-1">
                {["Video", "Quick quiz", "Resources"].map((chip) => (
                  <span key={chip} className="rounded-full border border-border px-2 py-0.5 text-[10px] text-muted-foreground">
                    {chip}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Floating cards */}
      <div className="lp-float-card lp-float absolute -right-2 -top-5 flex items-center gap-3 rounded-xl border border-border bg-card px-3.5 py-2.5 sm:-right-8">
        <span className="lp-tone-green flex h-9 w-9 items-center justify-center rounded-lg">
          <Award className="h-4 w-4" />
        </span>
        <div>
          <p className="text-xs font-semibold">Certificate earned</p>
          <p className="text-[11px] text-muted-foreground">Laravel for Beginners</p>
        </div>
      </div>

      <div className="lp-float-card lp-float-slow absolute -bottom-6 -left-2 flex items-center gap-3 rounded-xl border border-border bg-card px-3.5 py-2.5 sm:-left-8">
        <span className="lp-tone-violet relative flex h-9 w-9 items-center justify-center rounded-lg">
          <Video className="h-4 w-4" />
          <span className="absolute -right-0.5 -top-0.5 flex h-2.5 w-2.5">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75" />
            <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-500" />
          </span>
        </span>
        <div>
          <p className="text-xs font-semibold">Live Q&amp;A starts in 12 min</p>
          <p className="text-[11px] text-muted-foreground">Join link opens soon</p>
        </div>
      </div>

      <div className="lp-float-card lp-float-late absolute -right-1 bottom-16 hidden items-center gap-3 rounded-xl border border-border bg-card px-3.5 py-2.5 sm:flex sm:-right-10">
        <span className="lp-tone-blue flex h-9 w-9 items-center justify-center rounded-lg">
          <Banknote className="h-4 w-4" />
        </span>
        <div>
          <p className="text-xs font-semibold">Payout processed</p>
          <p className="text-[11px] text-muted-foreground">+$182.40 to your account</p>
        </div>
      </div>
    </div>
  );
}