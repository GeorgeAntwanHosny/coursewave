"use client";

import Link from "next/link";
import { useState } from "react";
import { Menu, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ThemeToggle } from "@/components/theme-toggle";
import { useAuthStore } from "@/stores/auth-store";
import { useLogout } from "@/hooks/use-auth";
import { NotificationBell } from "@/components/notifications/notification-bell";

export function Navbar() {
  const [open, setOpen] = useState(false);
  const user = useAuthStore((s) => s.user);
  const { mutate: logout, isPending } = useLogout();

  return (
    <header className="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <Link href="/" className="text-lg font-semibold">
          LearnLedger
        </Link>

        <nav className="hidden items-center gap-4 md:flex">
          <Link href="/courses" className="text-sm text-muted-foreground hover:text-foreground">
            Courses
          </Link>
          <Link href="/plans" className="text-sm text-muted-foreground hover:text-foreground">
            Plans
          </Link>
          {user ? (
            <>
              <Link href="/dashboard" className="text-sm text-muted-foreground hover:text-foreground">
                Dashboard
              </Link>
              <Link href="/certificates" className="text-sm text-muted-foreground hover:text-foreground">
                Certificates
              </Link>
              <span className="text-sm text-muted-foreground">Hi, {user.name}</span>
              <Button variant="outline" size="sm" disabled={isPending} onClick={() => logout()}>
                {isPending ? "Logging out..." : "Logout"}
              </Button>
            </>
          ) : (
            <>
              <Link href="/login">
                <Button variant="ghost" size="sm">Log in</Button>
              </Link>
              <Link href="/register">
                <Button size="sm">Register</Button>
              </Link>
            </>
          )}
          <NotificationBell />
          <ThemeToggle />
        </nav>

        <div className="flex items-center gap-2 md:hidden">
          <NotificationBell />
          <ThemeToggle />
          <Button variant="ghost" size="icon" onClick={() => setOpen(!open)} aria-label="Toggle menu">
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </Button>
        </div>
      </div>

      {open && (
        <div className="border-t border-border px-4 py-3 md:hidden">
          <div className="flex flex-col gap-3">
            <Link href="/courses" className="text-sm text-muted-foreground hover:text-foreground">
              Courses
            </Link>
            <Link href="/plans" className="text-sm text-muted-foreground hover:text-foreground">
              Plans
            </Link>
            {user ? (
              <>
                <Link href="/dashboard" onClick={() => setOpen(false)} className="text-sm">
                  Dashboard
                </Link>
                <Link href="/certificates" className="text-sm text-muted-foreground hover:text-foreground">
                  Certificates
                </Link>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={isPending}
                  onClick={() => {
                    logout();
                    setOpen(false);
                  }}
                >
                  {isPending ? "Logging out..." : "Logout"}
                </Button>
              </>
            ) : (
              <>
                <Link href="/login" onClick={() => setOpen(false)}>
                  <Button variant="ghost" size="sm" className="w-full">Log in</Button>
                </Link>
                <Link href="/register" onClick={() => setOpen(false)}>
                  <Button size="sm" className="w-full">Register</Button>
                </Link>
              </>
            )}
          </div>
        </div>
      )}
    </header>
  );
}