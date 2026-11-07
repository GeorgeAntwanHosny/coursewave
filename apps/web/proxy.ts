import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const GUEST_ONLY_ROUTES = ["/login", "/register"];

const PROTECTED_ROUTES: { prefix: string; roles: string[] }[] = [
  { prefix: "/dashboard", roles: [] },
  { prefix: "/billing", roles: [] },
  { prefix: "/learn", roles: [] },
  { prefix: "/certificates", roles: [] },
  { prefix: "/live", roles: [] },
  { prefix: "/instructor", roles: ["instructor"] },
  { prefix: "/admin", roles: ["admin"] },
];

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const role = request.cookies.get("cw_role")?.value;
  const isAuthenticated = !!role;

  // Redirect authenticated users away from guest-only routes.
  const isGuestRoute = GUEST_ONLY_ROUTES.some((route) =>
    pathname.startsWith(route)
  );

  if (isGuestRoute && isAuthenticated) {
    return NextResponse.redirect(new URL("/dashboard", request.url));
  }

  // Check protected routes.
  const matchedProtected = PROTECTED_ROUTES.find((route) =>
    pathname.startsWith(route.prefix)
  );

  if (matchedProtected) {
    // Redirect unauthenticated users to login.
    if (!isAuthenticated) {
      const loginUrl = new URL("/login", request.url);
      loginUrl.searchParams.set("redirect", pathname);

      return NextResponse.redirect(loginUrl);
    }

    // Check user role permissions.
    if (
      matchedProtected.roles.length > 0 &&
      !matchedProtected.roles.includes(role!)
    ) {
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
    "/certificates/:path*",
    "/live/:path*",
    "/instructor/:path*",
    "/admin/:path*",
  ],
};