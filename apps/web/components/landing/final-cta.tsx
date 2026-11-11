"use client";

import { useEffect, useState } from "react";
import { ArrowRight } from "lucide-react";
import { LinkButton } from "@/components/ui/link-button";
import { Reveal } from "@/components/landing/reveal";
import { useAuthStore } from "@/stores/auth-store";

export function FinalCta() {
  const user = useAuthStore((s) => s.user);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const loggedIn = mounted && !!user;

  return (
    <section className="px-4 pb-20 sm:px-6 lg:px-8 lg:pb-28" aria-labelledby="cta-title">
      <Reveal>
        <div className="lp-cta-card mx-auto max-w-5xl rounded-3xl px-6 py-14 text-center sm:px-12 sm:py-20">
          <h2 id="cta-title" className="text-3xl font-bold tracking-tight sm:text-4xl">
            Ready to learn from everyone?
          </h2>
          <p className="mx-auto mt-4 max-w-xl text-base text-white/85 sm:text-lg">
            One subscription, every course, and a payment model that rewards the people who teach.
          </p>

          <div className="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
            <LinkButton
              href={loggedIn ? "/dashboard" : "/register"}
              size="lg"
              className="h-12 bg-white px-7 text-base text-slate-900 hover:bg-white/90"
            >
              {loggedIn ? "Go to your dashboard" : "Create your account"}
              <ArrowRight className="h-4 w-4" />
            </LinkButton>

            <LinkButton
              href="/courses"
              size="lg"
              variant="outline"
              className="h-12 border-white/40 bg-transparent px-7 text-base text-white hover:bg-white/10 hover:text-white"
            >
              Browse courses
            </LinkButton>
          </div>
        </div>
      </Reveal>
    </section>
  );
}