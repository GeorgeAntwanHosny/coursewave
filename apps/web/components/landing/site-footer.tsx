import Link from "next/link";
import { ExternalLink, Globe, Mail } from "lucide-react";
import { apiOrigin, landingConfig } from "@/lib/landing-config";

export function SiteFooter() {
  const origin = apiOrigin();
  const { owner } = landingConfig;

  const columns: { title: string; links: { label: string; href: string; external?: boolean }[] }[] = [
    {
      title: "Product",
      links: [
        { label: "Courses", href: "/courses" },
        { label: "Pricing", href: "/#pricing" },
        { label: "Live sessions", href: "/live" },
        { label: "FAQ", href: "/#faq" },
      ],
    },
    {
      title: "Account",
      links: [
        { label: "Log in", href: "/login" },
        { label: "Sign up", href: "/register" },
        { label: "Dashboard", href: "/dashboard" },
        { label: "Certificates", href: "/certificates" },
      ],
    },
    {
      title: "Teach",
      links: [
        { label: "For instructors", href: "/#teach" },
        ...(origin ? [{ label: "Instructor panel", href: `${origin}/instructor`, external: true }] : []),
      ],
    },
  ];

  const ownerLinks = [
    { label: "GitHub", href: owner.github, icon: ExternalLink },
    { label: "LinkedIn", href: owner.linkedin, icon: ExternalLink },
    { label: "Website", href: owner.website, icon: Globe },
    { label: "Email", href: owner.email ? `mailto:${owner.email}` : "", icon: Mail },
  ].filter((link) => link.href);

  return (
    <footer className="border-t border-border px-4 py-12 sm:px-6 lg:px-8">
      <div className="mx-auto grid max-w-7xl gap-10 md:grid-cols-[1.4fr_2fr]">
        <div>
          <p className="text-lg font-semibold">{landingConfig.brand}</p>
          <p className="mt-2 max-w-xs text-sm text-muted-foreground">{landingConfig.tagline ?? "One subscription. Every instructor."}</p>
        </div>

        <div className="grid grid-cols-2 gap-8 sm:grid-cols-3">
          {columns.map((column) => (
            <div key={column.title}>
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{column.title}</p>
              <ul className="mt-4 space-y-2.5 text-sm">
                {column.links.map((link) => (
                  <li key={link.label}>
                    {link.external ? (
                      <a href={link.href} target="_blank" rel="noopener noreferrer" className="hover:text-primary">
                        {link.label}
                      </a>
                    ) : (
                      <Link href={link.href} className="hover:text-primary">
                        {link.label}
                      </Link>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>

      <div className="mx-auto mt-10 flex max-w-7xl flex-col gap-4 border-t border-border pt-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
        <p>
          © {new Date().getFullYear()} {landingConfig.brand}
          {owner.name && (
            <>
              {" "}
              · Designed and built by <span className="font-medium text-foreground">{owner.name}</span>
              {owner.role && `, ${owner.role}`}
            </>
          )}
        </p>

        {ownerLinks.length > 0 && (
          <ul className="flex flex-wrap items-center gap-4">
            {ownerLinks.map(({ label, href, icon: Icon }) => (
              <li key={label}>
                <a
                  href={href}
                  target={href.startsWith("mailto:") ? undefined : "_blank"}
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 hover:text-primary"
                >
                  <Icon className="h-3.5 w-3.5" />
                  {label}
                </a>
              </li>
            ))}
          </ul>
        )}
      </div>
    </footer>
  );
}