import { stackBadges } from "@/lib/landing-config";

export function StackStrip() {
  return (
    <section className="border-y border-border bg-muted/30 py-8" aria-label="Technology">
      <p className="mb-5 text-center text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">
        Built with production-grade tools
      </p>

      <div className="lp-marquee">
        <div className="lp-marquee-track">
          {[0, 1].map((copy) => (
            <div key={copy} className="lp-marquee-set" aria-hidden={copy === 1 ? true : undefined}>
              {stackBadges.map((name) => (
                <span
                  key={`${copy}-${name}`}
                  className="whitespace-nowrap rounded-full border border-border bg-card px-4 py-1.5 text-sm text-muted-foreground"
                >
                  {name}
                </span>
              ))}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}