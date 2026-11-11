import { useQuery } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";

export type LandingCourse = {
  id: number | string;
  title: string;
  slug: string;
  description: string | null;
  instructorName: string | null;
  lessonsCount: number | null;
};

type BasePlan = {
  id: number | string;
  name: string;
  slug: string;
  termDays: number;
  priceCents: number | null;
};

export type LandingPlan = BasePlan & {
  perMonthCents: number | null;
  /** Percent cheaper per month than the shortest plan; 0 for that plan itself. */
  savingsPercent: number;
};

// The landing page must never crash because an API field is named differently than expected,
// so everything below reads defensively and drops what it can't understand.
const str = (v: unknown): string | null => (typeof v === "string" && v.trim() !== "" ? v : null);

const num = (v: unknown): number | null => {
  const n = typeof v === "number" ? v : typeof v === "string" && v.trim() !== "" ? Number(v) : NaN;
  return Number.isFinite(n) ? n : null;
};

const obj = (v: unknown): Record<string, unknown> | null =>
  v && typeof v === "object" && !Array.isArray(v) ? (v as Record<string, unknown>) : null;

function unwrapList(payload: unknown): unknown[] {
  if (Array.isArray(payload)) return payload;

  const o = obj(payload);
  if (o) {
    for (const key of ["items", "data", "courses", "plans"]) {
      if (Array.isArray(o[key])) return o[key] as unknown[];
    }
  }

  return [];
}

function toCourse(raw: unknown): LandingCourse | null {
  const r = obj(raw);
  if (!r) return null;

  const id = num(r.id) ?? str(r.id);
  const title = str(r.title);
  const slug = str(r.slug);
  if (id === null || !title || !slug) return null;

  const description = str(r.description);

  return {
    id,
    title,
    slug,
    description: description ? description.replace(/<[^>]*>/g, "").trim() || null : null,
    instructorName: str(r.instructor_name) ?? str(obj(r.instructor)?.name),
    lessonsCount: num(r.lessons_count) ?? num(r.total_lessons),
  };
}

function toPlan(raw: unknown): BasePlan | null {
  const r = obj(raw);
  if (!r) return null;

  const id = num(r.id) ?? str(r.id);
  const name = str(r.name);
  if (id === null || !name) return null;

  const dollars = num(r.price);
  const cents = num(r.price_cents) ?? (dollars !== null ? Math.round(dollars * 100) : null);

  return {
    id,
    name,
    slug: str(r.slug) ?? String(id),
    termDays: num(r.term_days) ?? num(r.termDays) ?? 30,
    priceCents: cents,
  };
}

function withSavings(plans: BasePlan[]): LandingPlan[] {
  const sorted = [...plans].sort((a, b) => a.termDays - b.termDays);
  const base = sorted.find((p) => p.priceCents !== null);
  const baseMonthly = base && base.priceCents !== null ? base.priceCents / (base.termDays / 30) : null;

  return sorted.map((plan) => {
    const monthly = plan.priceCents !== null ? Math.round(plan.priceCents / (plan.termDays / 30)) : null;
    const savings =
      monthly !== null && baseMonthly && base && plan.id !== base.id
        ? Math.max(0, Math.round((1 - monthly / baseMonthly) * 100))
        : 0;

    return { ...plan, perMonthCents: monthly, savingsPercent: savings };
  });
}

export function formatUsd(cents: number): string {
  return new Intl.NumberFormat("en-US", { style: "currency", currency: "USD" }).format(cents / 100);
}

export function termLabel(termDays: number): string {
  if (termDays === 30) return "per month";
  if (termDays === 90) return "every 3 months";
  if (termDays === 365) return "per year";
  return `every ${termDays} days`;
}

export function usePublicCourses() {
  return useQuery({
    queryKey: ["landing-courses"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<unknown>>("/courses");

      return unwrapList(data.data)
        .map(toCourse)
        .filter((course): course is LandingCourse => course !== null);
    },
    staleTime: 5 * 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
  });
}

export function usePublicPlans() {
  return useQuery({
    queryKey: ["landing-plans"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<unknown>>("/plans");

      return withSavings(
        unwrapList(data.data)
          .map(toPlan)
          .filter((plan): plan is BasePlan => plan !== null)
      );
    },
    staleTime: 5 * 60_000,
    retry: 1,
    refetchOnWindowFocus: false,
  });
}