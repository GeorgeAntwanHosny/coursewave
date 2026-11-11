import { Award, BookOpen, Coins, RefreshCcw, TrendingUp, Video } from "lucide-react";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";

const features = [
  {
    icon: BookOpen,
    title: "Every course, one plan",
    text: "Video and reading lessons from every instructor on the platform. No per-course purchases, ever.",
  },
  {
    icon: TrendingUp,
    title: "Progress you can see",
    text: "Per-lesson progress, course completion and quick quizzes keep you moving toward the finish.",
  },
  {
    icon: Video,
    title: "Live sessions",
    text: "Instructors host live Q&As. You get a reminder, and the join link opens right when it should.",
  },
  {
    icon: Award,
    title: "Certificates that verify",
    text: "Finish a course and get a PDF certificate with a number that anyone can check online.",
  },
  {
    icon: Coins,
    title: "Fair to instructors",
    text: "Your payment is shared by how much you actually learn from each instructor, not by who has the loudest course page.",
  },
  {
    icon: RefreshCcw,
    title: "Change your mind, keep your money",
    text: "Cancel or switch plans and the days you haven’t used yet are refunded to your original payment method.",
  },
];

export function Features() {
  return (
    <section id="features" className="lp-section px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="features-title">
      <div className="mx-auto max-w-7xl">
        <Reveal>
          <SectionHeading
            eyebrow="Why CourseWave"
            title={<span id="features-title">Everything you need to actually finish a course</span>}
            description="Learning, live help and proof of completion in one place — with a payment model that’s fair to the people who teach."
          />
        </Reveal>

        <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {features.map(({ icon: Icon, title, text }, index) => (
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