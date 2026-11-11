"use client";

import { useMemo, useState } from "react";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";

const PLAN_CENTS = 1999; // the example monthly plan
const FEE_PERCENT = 20; // the example platform fee

const instructors = [
  { name: "Laravel course", initial: "L" },
  { name: "React course", initial: "R" },
  { name: "PostgreSQL course", initial: "P" },
];

const usd = (cents: number) => `$${(cents / 100).toFixed(2)}`;

/**
 * Integer-only largest-remainder split: every cent is allocated, nobody gets a fractional cent, and ties
 * go to the earlier entry so the result is deterministic. This is the same idea the platform uses.
 */
function largestRemainder(pool: number, weights: number[]): number[] {
  const total = weights.reduce((sum, w) => sum + w, 0);
  if (total === 0) return weights.map(() => 0);

  const raw = weights.map((w) => pool * w);
  const shares = raw.map((r) => Math.floor(r / total));
  const order = raw.map((r, i) => ({ i, remainder: r % total })).sort((a, b) => b.remainder - a.remainder || a.i - b.i);

  let left = pool - shares.reduce((sum, s) => sum + s, 0);
  for (let k = 0; left > 0; k++, left--) {
    shares[order[k].i] += 1;
  }

  return shares;
}

export function RevenueSplitDemo() {
  const [minutes, setMinutes] = useState([180, 90, 30]);

  const result = useMemo(() => {
    const fee = Math.floor((PLAN_CENTS * FEE_PERCENT) / 100);
    const pool = PLAN_CENTS - fee;
    const shares = largestRemainder(pool, minutes);

    return { fee, pool, shares, nobodyLearned: minutes.every((m) => m === 0) };
  }, [minutes]);

  const sharedTotal = result.shares.reduce((sum, s) => sum + s, 0);

  return (
    <section id="fair-split" className="lp-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="split-title">
      <div className="mx-auto max-w-5xl">
        <Reveal>
          <SectionHeading
            eyebrow="Try it"
            title={<span id="split-title">See how a subscription is shared</span>}
            description="Drag the sliders to change how long a learner spent in each course this month. The money follows the learning."
          />
        </Reveal>

        <Reveal delay={100}>
          <div className="mt-12 grid gap-8 rounded-3xl border border-border bg-card p-5 shadow-sm sm:p-8 lg:grid-cols-[1.2fr_1fr]">
            <div className="space-y-6">
              {instructors.map((instructor, index) => {
                const inputId = `split-${index}`;
                const share = result.shares[index];
                const widthPercent = result.pool > 0 ? (share / result.pool) * 100 : 0;

                return (
                  <div key={instructor.name}>
                    <div className="flex items-center justify-between gap-3">
                      <label htmlFor={inputId} className="flex items-center gap-3 text-sm font-medium">
                        <span className="lp-icon-tile !h-9 !w-9 !rounded-lg text-sm font-semibold">{instructor.initial}</span>
                        {instructor.name}
                      </label>
                      <span className="text-sm tabular-nums text-muted-foreground">{minutes[index]} min</span>
                    </div>

                    <input
                      id={inputId}
                      type="range"
                      min={0}
                      max={600}
                      step={5}
                      value={minutes[index]}
                      onChange={(e) => {
                        const next = [...minutes];
                        next[index] = Number(e.target.value);
                        setMinutes(next);
                      }}
                      className="lp-range mt-2"
                      aria-label={`Minutes spent in the ${instructor.name}`}
                    />

                    <div className="mt-1 flex items-center gap-3">
                      <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                        <div className="lp-bar-fill h-full rounded-full" style={{ width: `${widthPercent}%` }} />
                      </div>
                      <span className="w-16 text-right text-sm font-semibold tabular-nums">{usd(share)}</span>
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="flex flex-col justify-between rounded-2xl bg-muted/50 p-5">
              <div>
                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  Example: one {usd(PLAN_CENTS)} plan
                </p>

                <dl className="mt-4 space-y-3 text-sm">
                  <div className="flex justify-between">
                    <dt className="text-muted-foreground">Platform fee ({FEE_PERCENT}%)</dt>
                    <dd className="font-medium tabular-nums">{usd(result.fee)}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-muted-foreground">Shared with instructors</dt>
                    <dd className="font-medium tabular-nums">{usd(result.pool)}</dd>
                  </div>
                  <div className="flex justify-between border-t border-border pt-3 text-base font-semibold">
                    <dt>Total</dt>
                    <dd className="tabular-nums">{usd(result.fee + sharedTotal)}</dd>
                  </div>
                </dl>

                <p className="mt-4 text-xs text-muted-foreground" aria-live="polite">
                  {result.nobodyLearned
                    ? "No learning yet, so there’s nothing to share. Move a slider."
                    : sharedTotal === result.pool
                      ? "Adds up to the cent: no fractional cents, nothing lost."
                      : ""}
                </p>
              </div>

              <p className="mt-6 text-xs leading-relaxed text-muted-foreground">
                In CourseWave this happens every day on that day’s slice of your subscription, using whole cents and the
                largest-remainder method. This demo splits a whole month at once to keep it simple.
              </p>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}