import { Suspense } from "react";
import BillingSuccessContent from "./billing-success-content";

export default function BillingSuccessPage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-md px-4 py-16 text-center">
          <p className="text-muted-foreground">
            Loading payment details...
          </p>
        </div>
      }
    >
      <BillingSuccessContent />
    </Suspense>
  );
}