import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Reveal } from "@/components/landing/reveal";
import { SectionHeading } from "@/components/landing/section-heading";

const faqs = [
    {
        q: "How does the subscription work?",
        a: "You pay once, up front, for the term you choose: monthly, 3 months or a year. That single payment gives you access to every course on the platform for the whole term.",
    },
    {
        q: "Can I cancel? Do I get money back?",
        a: "Yes. When you cancel, the days you haven’t used yet are refunded to your original payment method. Days that have already passed are not refunded.",
    },
    {
        q: "Can I switch plans?",
        a: "Yes. Switching cancels your current plan, refunds its unused days, and starts the new plan.",
    },
    {
        q: "How are instructors paid?",
        a: "Each day’s share of every subscription, after a platform fee, is split between the instructors a learner actually spent time with that day. Instructors are paid out on a schedule through Stripe.",
    },
    {
        q: "Do I get a certificate?",
        a: "When you complete every lesson in a course you get a PDF certificate with a unique number. Anyone can check that number online to confirm it’s genuine.",
    },
    {
        q: "What are live sessions?",
        a: "Instructors can schedule live Q&As on Zoom or Google Meet. You’re reminded beforehand, and the join link appears shortly before the session starts.",
    },
    {
        q: "Is my payment secure?",
        a: "Payments are processed by Stripe. Your card details go to Stripe directly; CourseWave never sees or stores them.",
    },
];

export function Faq() {
    return (
        <section id="faq" className="lp-section bg-muted/30 px-4 py-20 sm:px-6 lg:px-8 lg:py-28" aria-labelledby="faq-title">
            <div className="mx-auto max-w-3xl">
                <Reveal>
                    <SectionHeading eyebrow="FAQ" title={<span id="faq-title">Questions, answered</span>} />
                </Reveal>

                <Reveal delay={100}>
                    <Accordion
                        multiple={false}
                        className="mt-12 rounded-2xl border border-border bg-card px-5 sm:px-7"
                    >
                        {faqs.map((item, index) => (
                            <AccordionItem
                                key={item.q}
                                value={`item-${index}`}
                                className={index === faqs.length - 1 ? "border-b-0" : ""}
                            >
                                <AccordionTrigger className="text-left text-base">
                                    {item.q}
                                </AccordionTrigger>
                                <AccordionContent className="leading-relaxed text-muted-foreground">
                                    {item.a}
                                </AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>
                </Reveal>
            </div>
        </section>
    );
}