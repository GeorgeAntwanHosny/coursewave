"use client";

import { Check } from "lucide-react";
import { formatUsd, termLabel, usePublicPlans } from "@/hooks/use-landing-data";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { LinkButton } from "@/components/ui/link-button";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

const included = [
  "Every course from every instructor",
  "Live sessions and certificates",
  "Cancel anytime — unused days are refunded",
];

export function Pricing() {
  const { data: plans, isLoading, isError, refetch } = usePublicPlans();

  const bestSavings = plans ? Math.max(0, ...plans.map((p) => p.savingsPercent)) : 0;

  return (
    <section id="pricing" className="lp-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="pricing-title">
      <div className="mx-auto max-w-6xl">
        <Reveal>
          <SectionHeading
            eyebrow="Pricing"
            title={<span id="pricing-title">One price, everything included</span>}
            description="Pay up front for the term you choose. The longer the plan, the less you pay per month."
          />
        </Reveal>

        {isLoading && (
          <div className="mt-14 grid gap-6 md:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-[26rem] w-full rounded-3xl" />
            ))}
          </div>
        )}

        {(isError || (plans && plans.length === 0)) && (
          <div className="mt-14 space-y-3 text-center">
            <p className="text-sm text-muted-foreground">
              {isError ? "We couldn’t load the plans right now." : "Plans will appear here soon."}
            </p>
            <div className="flex justify-center gap-3">
              {isError && (
                <Button variant="outline" size="sm" onClick={() => refetch()}>
                  Try again
                </Button>
              )}
              <LinkButton href="/plans" size="sm">
                See plans
              </LinkButton>
            </div>
          </div>
        )}

        {plans && plans.length > 0 && (
          <div className="mt-14 grid gap-6 md:grid-cols-3">
            {plans.map((plan, index) => {
              const best = bestSavings > 0 && plan.savingsPercent === bestSavings;

              return (
                <Reveal key={plan.id} delay={index * 90}>
                  <div
                    className={cn(
                      "relative flex h-full flex-col rounded-3xl border bg-card p-7",
                      best ? "border-transparent shadow-xl ring-2 ring-primary" : "border-border"
                    )}
                  >
                    {best && (
                      <Badge className="lp-btn-gradient absolute -top-3 left-1/2 -translate-x-1/2 border-0 px-3">
                        Best value
                      </Badge>
                    )}

                    <h3 className="text-lg font-semibold">{plan.name}</h3>

                    <div className="mt-5">
                      {plan.priceCents !== null ? (
                        <>
                          <p className="text-4xl font-bold tracking-tight">{formatUsd(plan.priceCents)}</p>
                          <p className="mt-1 text-sm text-muted-foreground">{termLabel(plan.termDays)}</p>
                        </>
                      ) : (
                        <p className="text-xl font-semibold">See price at checkout</p>
                      )}

                      {plan.perMonthCents !== null && plan.termDays !== 30 && (
                        <p className="mt-3 text-sm">
                          <span className="font-medium">{formatUsd(plan.perMonthCents)}</span>
                          <span className="text-muted-foreground"> a month</span>
                          {plan.savingsPercent > 0 && (
                            <span className="lp-tone-green ml-2 rounded-full px-2 py-0.5 text-xs font-medium">
                              Save {plan.savingsPercent}%
                            </span>
                          )}
                        </p>
                      )}
                    </div>

                    <ul className="mt-6 flex-1 space-y-3 text-sm">
                      {included.map((item) => (
                        <li key={item} className="flex items-start gap-2.5">
                          <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                          <span>{item}</span>
                        </li>
                      ))}
                    </ul>

                    <LinkButton
                      href="/plans"
                      size="lg"
                      variant={best ? "default" : "outline"}
                      className={cn("mt-8 w-full", best && "lp-btn-gradient")}
                    >
                      Choose {plan.name}
                    </LinkButton>
                  </div>
                </Reveal>
              );
            })}
          </div>
        )}

        <p className="mt-8 text-center text-xs text-muted-foreground">
          Payments are handled by Stripe. We never see or store your card number.
        </p>
      </div>
    </section>
  );
}