import Link from "next/link";
import { Button } from "@/components/ui/button";
import { XCircle } from "lucide-react";

export default function BillingCancelPage() {
  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center space-y-4">
      <XCircle className="mx-auto h-12 w-12 text-muted-foreground" />
      <h1 className="text-2xl font-semibold">Checkout canceled</h1>
      <p className="text-muted-foreground">No charge was made. You can try again anytime.</p>
      <Link href="/plans">
        <Button>Back to plans</Button>
      </Link>
    </div>
  );
}