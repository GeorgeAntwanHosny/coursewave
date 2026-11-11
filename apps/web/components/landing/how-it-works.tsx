import { Coins, CreditCard, GraduationCap } from "lucide-react";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";

const steps = [
  {
    icon: CreditCard,
    title: "Subscribe once",
    text: "Pick a monthly, 3-month or annual plan. One payment unlocks every course.",
  },
  {
    icon: GraduationCap,
    title: "Learn anything",
    text: "Watch, read, take quizzes and join live sessions. Your progress is tracked as you go.",
  },
  {
    icon: Coins,
    title: "Instructors get paid fairly",
    text: "Your plan is recognized day by day and shared by learning time. Instructors are paid out through Stripe.",
  },
];

export function HowItWorks() {
  return (
    <section id="how-it-works" className="lp-section bg-muted/30 px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="how-title">
      <div className="mx-auto max-w-6xl">
        <Reveal>
          <SectionHeading
            eyebrow="How it works"
            title={<span id="how-title">Three steps, no surprises</span>}
            description="No bundles to compare and no courses to buy one at a time."
          />
        </Reveal>

        <div className="relative mt-16 grid gap-10 lg:grid-cols-3 lg:gap-8">
          <div
            className="absolute left-[16.6%] right-[16.6%] top-7 hidden h-px bg-gradient-to-r from-transparent via-border to-transparent lg:block"
            aria-hidden="true"
          />

          {steps.map(({ icon: Icon, title, text }, index) => (
            <Reveal key={title} delay={index * 120}>
              <div className="relative text-center">
                <div className="relative mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-border bg-card shadow-sm">
                  <Icon className="h-6 w-6 text-primary" />
                  <span className="lp-btn-gradient absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold">
                    {index + 1}
                  </span>
                </div>
                <h3 className="mt-6 text-lg font-semibold">{title}</h3>
                <p className="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground">{text}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}