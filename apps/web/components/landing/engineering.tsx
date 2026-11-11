import { Coins, Container, Database, ShieldCheck, TrendingUp, Zap } from "lucide-react";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";

const items = [
  {
    icon: TrendingUp,
    title: "Revenue recognized daily",
    text: "A payment is deferred and recognized one day at a time, so a refund only ever touches days that haven’t happened yet.",
  },
  {
    icon: ShieldCheck,
    title: "Exactly-once payouts",
    text: "A database constraint claims each payout, the provider call carries a stored idempotency key, and a timeout is its own state — never guessed.",
  },
  {
    icon: Coins,
    title: "Splits to the cent",
    text: "Whole-cent arithmetic and the largest-remainder method: every cent is accounted for, run after run.",
  },
  {
    icon: Database,
    title: "An append-only ledger",
    text: "Balances are derived from immutable ledger rows and can always be rebuilt from them.",
  },
  {
    icon: Zap,
    title: "Real-time, never lossy",
    text: "Notifications are stored and broadcast over WebSockets. A failure to notify can never break the payment or lesson that caused it.",
  },
  {
    icon: Container,
    title: "Ships in containers",
    text: "Docker images for the API, queue worker, scheduler and WebSocket server, with a health-checked, one-command setup.",
  },
];

export function Engineering() {
  return (
    <section id="engineering" className="lp-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="eng-title">
      <div className="mx-auto max-w-7xl">
        <Reveal>
          <SectionHeading
            eyebrow="Under the hood"
            title={<span id="eng-title">Built like a real product</span>}
            description="The hard problems here are financial and operational. This is how they’re solved."
          />
        </Reveal>

        <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {items.map(({ icon: Icon, title, text }, index) => (
            <Reveal key={title} delay={index * 70}>
              <div className="lp-card h-full rounded-2xl border border-border bg-card p-6">
                <span className="lp-icon-tile">
                  <Icon className="h-5 w-5" />
                </span>
                <h3 className="mt-5 text-lg font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{text}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}