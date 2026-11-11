import type { Metadata } from "next";
import "./landing.css";
import { Hero } from "@/components/landing/hero";
import { StackStrip } from "@/components/landing/stack-strip";
import { Features } from "@/components/landing/features";
import { HowItWorks } from "@/components/landing/how-it-works";
import { RevenueSplitDemo } from "@/components/landing/revenue-split-demo";
import { CoursesShowcase } from "@/components/landing/courses-showcase";
import { Pricing } from "@/components/landing/pricing";
import { InstructorSection } from "@/components/landing/instructor-section";
import { Engineering } from "@/components/landing/engineering";
import { Faq } from "@/components/landing/faq";
import { FinalCta } from "@/components/landing/final-cta";
import { SiteFooter } from "@/components/landing/site-footer";
import { landingConfig } from "@/lib/landing-config";

export const metadata: Metadata = {
  title: "CourseWave — One subscription, every instructor",
  description:
    "Unlock every course with a single plan. Learn with video, reading, quizzes and live sessions, earn verifiable certificates, and know your payment is shared fairly with the instructors you learn from.",
  openGraph: {
    title: "CourseWave — One subscription, every instructor",
    description:
      "One plan unlocks every course. Your payment is shared with the instructors you actually learn from, down to the cent.",
    type: "website",
  },
  twitter: {
    card: "summary",
    title: "CourseWave — One subscription, every instructor",
    description: "One plan unlocks every course, with a payment model that rewards the people who teach.",
  },
};

export default function HomePage() {
  return (
    <div className="lp-root">
      {/* Without JavaScript the scroll-reveal never fires, so show everything immediately. */}
      <noscript>
        <style>{".lp-reveal{opacity:1!important;transform:none!important}"}</style>
      </noscript>

      <Hero />
      {landingConfig.showEngineering && <StackStrip />}
      <Features />
      <HowItWorks />
      <RevenueSplitDemo />
      <CoursesShowcase />
      <Pricing />
      <InstructorSection />
      {landingConfig.showEngineering && <Engineering />}
      <Faq />
      <FinalCta />
      <SiteFooter />
    </div>
  );
}