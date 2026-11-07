import { Suspense } from "react";

import { Skeleton } from "@/components/ui/skeleton";
import { LoginForm } from "@/components/auth/login-form";

// useSearchParams() inside LoginForm opts the page out of static prerendering.
// Wrapping it in <Suspense> lets Next.js render this shell at build time
// and fill in the form in the browser.
function LoginFormSkeleton() {
  return (
    <div className="mx-auto w-full max-w-md space-y-6 p-6" aria-busy="true">
      <div className="space-y-2">
        <Skeleton className="h-7 w-40" />
        <Skeleton className="h-4 w-64" />
      </div>
      <div className="space-y-4">
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={<LoginFormSkeleton />}>
      <div className="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
        <LoginForm />
      </div>
    </Suspense>
  );
}