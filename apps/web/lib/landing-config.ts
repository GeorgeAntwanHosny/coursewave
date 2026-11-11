// Edit this file to personalize the landing page. Nothing else needs to change.

export const landingConfig = {
  brand: "CourseWave",
  tagline: "One subscription. Every instructor.",

  // Shown in the footer. Leave a field empty ("") to hide it.
  owner: {
    name: "George Antwan Hosny Ghaly",
    role: "Full-stack engineer",
    github: "https://github.com/GeorgeAntwanHosny",
    linkedin: "https://www.linkedin.com/in/georgeantwan/",
    website: "https://georgeantwanhosny.github.io/portfolio/",
    email: "georgeantwanhosnyghaly@outlook.com",
  },

  // The "Under the hood" section and the tech marquee are for portfolio visitors.
  // Set this to false to turn the page into a pure product page.
  showEngineering: true,
};

export const stackBadges = [
  "Next.js 16",
  "TypeScript",
  "Tailwind CSS",
  "shadcn/ui",
  "TanStack Query",
  "Laravel 13",
  "PostgreSQL",
  "Redis",
  "Laravel Reverb",
  "Stripe Connect",
  "Filament",
  "Docker",
];

/** The API's origin without the /api suffix (used to link to the instructor panel). */
export function apiOrigin(): string | null {
  const url = process.env.NEXT_PUBLIC_API_URL;
  if (!url) return null;
  return url.replace(/\/api\/?$/, "").replace(/\/$/, "");
}