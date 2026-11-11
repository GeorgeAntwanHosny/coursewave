import { ArrowRight, Bell, Check } from "lucide-react";
import { Reveal } from "@/components/landing/reveal";
import { LinkButton } from "@/components/ui/link-button";
import { apiOrigin } from "@/lib/landing-config";

const points = [
  "See what you’ve earned, what’s outstanding and what’s been paid out",
  "Know how many learners you have and how far they’ve gotten",
  "Reply to questions on your lessons and host live sessions",
  "Get paid on a schedule through Stripe, with every step recorded",
];

export function InstructorSection() {
  const origin = apiOrigin();

  return (
    <section id="teach" className="lp-section bg-muted/30 px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="teach-title">
      <div className="mx-auto grid max-w-6xl items-center gap-14 lg:grid-cols-2">
        <Reveal>
          <p className="lp-eyebrow">For instructors</p>
          <h2 id="teach-title" className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">
            Teach what you know. Be paid for what’s learned.
          </h2>
          <p className="mt-4 text-base text-muted-foreground sm:text-lg">
            Instructors get their own dashboard: earnings, learners, comments, live sessions and payouts, all in one place.
          </p>

          <ul className="mt-7 space-y-3">
            {points.map((point) => (
              <li key={point} className="flex items-start gap-3 text-sm sm:text-base">
                <span className="lp-tone-green mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full">
                  <Check className="h-3 w-3" />
                </span>
                <span>{point}</span>
              </li>
            ))}
          </ul>

          {origin && (
            <LinkButton href={`${origin}/instructor`} external size="lg" variant="outline" className="mt-8">
              Open the instructor panel
              <ArrowRight className="h-4 w-4" />
            </LinkButton>
          )}
        </Reveal>

        <Reveal delay={120}>
          <div className="rounded-3xl border border-border bg-card p-5 shadow-sm sm:p-6" aria-hidden="true">
            <div className="flex items-center justify-between">
              <p className="text-sm font-semibold">Earnings · last 30 days</p>
              <span className="rounded-full border border-border px-2.5 py-0.5 text-[11px] text-muted-foreground">
                Illustrative data
              </span>
            </div>

            <div className="mt-5 grid grid-cols-3 gap-3">
              {[
                { label: "Earned", value: "$1,284", note: "+12%" },
                { label: "Learners", value: "342", note: "+24" },
                { label: "Owed now", value: "$212", note: "pays Fri" },
              ].map((tile) => (
                <div key={tile.label} className="rounded-xl bg-muted/50 p-3">
                  <p className="text-[11px] text-muted-foreground">{tile.label}</p>
                  <p className="mt-1 text-lg font-semibold tabular-nums sm:text-xl">{tile.value}</p>
                  <p className="text-[11px] text-muted-foreground">{tile.note}</p>
                </div>
              ))}
            </div>

            <svg viewBox="0 0 300 90" className="mt-5 h-28 w-full" preserveAspectRatio="none">
              <defs>
                <linearGradient id="lp-area" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#6366f1" stopOpacity="0.35" />
                  <stop offset="100%" stopColor="#6366f1" stopOpacity="0" />
                </linearGradient>
                <linearGradient id="lp-line" x1="0" y1="0" x2="1" y2="0">
                  <stop offset="0%" stopColor="#6366f1" />
                  <stop offset="100%" stopColor="#06b6d4" />
                </linearGradient>
              </defs>
              <path
                d="M0 70 L25 62 L50 66 L75 50 L100 54 L125 40 L150 46 L175 30 L200 36 L225 22 L250 26 L275 12 L300 16 L300 90 L0 90 Z"
                fill="url(#lp-area)"
              />
              <path
                d="M0 70 L25 62 L50 66 L75 50 L100 54 L125 40 L150 46 L175 30 L200 36 L225 22 L250 26 L275 12 L300 16"
                fill="none"
                stroke="url(#lp-line)"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
              />
            </svg>

            <div className="mt-4 space-y-2">
              {[
                { tone: "lp-tone-blue", title: "Payout processed", text: "$182.40 was sent to your account." },
                { tone: "lp-tone-violet", title: "New comment on “Eloquent models”", text: "A learner is waiting for your reply." },
              ].map((item) => (
                <div key={item.title} className="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5">
                  <span className={`${item.tone} flex h-8 w-8 shrink-0 items-center justify-center rounded-lg`}>
                    <Bell className="h-4 w-4" />
                  </span>
                  <div className="min-w-0">
                    <p className="truncate text-xs font-semibold">{item.title}</p>
                    <p className="truncate text-[11px] text-muted-foreground">{item.text}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}