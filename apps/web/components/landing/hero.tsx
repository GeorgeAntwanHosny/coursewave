"use client";

import { useEffect, useState } from "react";
import { ArrowRight, Award, RefreshCcw, Sparkles, Video } from "lucide-react";
import { LinkButton } from "@/components/ui/link-button";
import { ProductPreview } from "@/components/landing/product-preview";
import { useAuthStore } from "@/stores/auth-store";

const trust = [
  { icon: RefreshCcw, text: "Cancel anytime — unused days are refunded" },
  { icon: Award, text: "Certificates anyone can verify" },
  { icon: Video, text: "Live sessions with your instructors" },
];

export function Hero() {
  const user = useAuthStore((s) => s.user);
  const [mounted, setMounted] = useState(false);

  // The server can't know who is logged in, so the buttons switch only after mount (no hydration mismatch).
  useEffect(() => {
    setMounted(true);
  }, []);

  const loggedIn = mounted && !!user;

  return (
    <section className="relative px-4 pb-20 pt-12 sm:px-6 sm:pt-16 lg:px-8 lg:pb-28 lg:pt-24" aria-labelledby="hero-title">
      <div className="lp-hero-bg" aria-hidden="true">
        <div className="lp-blob lp-blob-1" />
        <div className="lp-blob lp-blob-2" />
        <div className="lp-blob lp-blob-3" />
        <div className="lp-grid-bg" />
      </div>

      <div className="mx-auto grid max-w-7xl items-center gap-16 lg:grid-cols-[1.05fr_1fr]">
        <div className="text-center lg:text-left">
          <div
            className="lp-fade-up inline-flex items-center gap-2 rounded-full border border-border bg-card/70 px-3.5 py-1.5 text-xs font-medium backdrop-blur"
            style={{ ["--lp-delay" as string]: "0ms" }}
          >
            <Sparkles className="h-3.5 w-3.5 text-primary" />
            <span>Subscription learning, fairly shared</span>
          </div>

          <h1
            id="hero-title"
            className="lp-fade-up mt-6 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl"
            style={{ ["--lp-delay" as string]: "100ms" }}
          >
            One subscription.
            <br />
            <span className="lp-gradient-text">Every instructor.</span>
          </h1>

          <p
            className="lp-fade-up mx-auto mt-6 max-w-xl text-base text-muted-foreground sm:text-lg lg:mx-0"
            style={{ ["--lp-delay" as string]: "200ms" }}
          >
            Unlock every course on CourseWave with a single plan. Your payment is shared with the instructors you
            actually learn from — down to the cent.
          </p>

          <div
            className="lp-fade-up mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center lg:justify-start"
            style={{ ["--lp-delay" as string]: "300ms" }}
          >
            <LinkButton
              href={loggedIn ? "/dashboard" : "/register"}
              size="lg"
              className="lp-btn-gradient h-12 px-7 text-base"
            >
              {loggedIn ? "Continue learning" : "Start learning"}
              <ArrowRight className="h-4 w-4" />
            </LinkButton>

            <LinkButton href="/courses" size="lg" variant="outline" className="h-12 bg-card/60 px-7 text-base backdrop-blur">
              Browse courses
            </LinkButton>
          </div>

          <p
            className="lp-fade-up mt-3 text-xs text-muted-foreground lg:text-left"
            style={{ ["--lp-delay" as string]: "360ms" }}
          >
            You can browse the whole catalog without an account.
          </p>

          <ul
            className="lp-fade-up mt-8 flex flex-col items-center gap-3 text-sm text-muted-foreground sm:flex-row sm:flex-wrap sm:justify-center lg:justify-start"
            style={{ ["--lp-delay" as string]: "440ms" }}
          >
            {trust.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-2">
                <Icon className="h-4 w-4 shrink-0 text-primary" />
                <span>{text}</span>
              </li>
            ))}
          </ul>
        </div>

        <div className="lp-fade-up px-2 sm:px-8 lg:px-0" style={{ ["--lp-delay" as string]: "250ms" }}>
          <ProductPreview />
        </div>
      </div>
    </section>
  );
}