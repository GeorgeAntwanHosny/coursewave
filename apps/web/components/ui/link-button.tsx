import Link from "next/link";
import type { ReactNode } from "react";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

// Taken from buttonVariants itself, so this follows whatever variants and sizes your shadcn version defines.
type VariantOptions = NonNullable<Parameters<typeof buttonVariants>[0]>;

type LinkButtonProps = {
  href: string;
  variant?: VariantOptions["variant"];
  size?: VariantOptions["size"];
  className?: string;
  /** Plain <a> that opens in a new tab (for other sites, mailto:, and the Laravel panels). */
  external?: boolean;
  children: ReactNode;
};

/**
 * A link that looks like a Button. It does NOT use Button's `asChild` (the newer shadcn/Base UI Button has none):
 * the <a> itself carries the button styles, so there is no <button> wrapped around a link, and icons sit beside the text.
 */
export function LinkButton({ href, variant, size, className, external, children }: LinkButtonProps) {
  const classes = cn(
    buttonVariants({ variant, size }),
    // Explicit, so the layout is right on any shadcn version and the icon can never wrap onto its own line.
    "inline-flex items-center justify-center gap-2 whitespace-nowrap",
    className
  );

  if (external) {
    return (
      <a href={href} target="_blank" rel="noopener noreferrer" className={classes}>
        {children}
      </a>
    );
  }

  return (
    <Link href={href} className={classes}>
      {children}
    </Link>
  );
}