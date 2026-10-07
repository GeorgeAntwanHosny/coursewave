import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const GUEST_ONLY_ROUTES = ["/login", "/register"];

const PROTECTED_ROUTES: { prefix: string; roles: string[] }[] = [
  { prefix: "/dashboard", roles: [] },
  { prefix: "/billing", roles: [] },
  { prefix: "/learn", roles: [] },
  { prefix: "/instructor", roles: ["instructor"] },
  { prefix: "/admin", roles: ["admin"] },
];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const role = request.cookies.get("cw_role")?.value;
  const isAuthenticated = !!role;

  const isGuestRoute = GUEST_ONLY_ROUTES.some((r) => pathname.startsWith(r));
  if (isGuestRoute && isAuthenticated) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  const matchedProtected = PROTECTED_ROUTES.find((r) => pathname.startsWith(r.prefix));
  if (matchedProtected) {
    if (!isAuthenticated) {
      const loginUrl = new URL("/login", request.url);
      loginUrl.searchParams.set("redirect", pathname);
      return NextResponse.redirect(loginUrl);
    }
    if (matchedProtected.roles.length > 0 && !matchedProtected.roles.includes(role!)) {
      return NextResponse.redirect(new URL("/dashboard", request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/login",
    "/register",
    "/dashboard/:path*",
    "/billing/:path*",
    "/learn/:path*",
    "/instructor/:path*",
    "/admin/:path*",
  ],
};