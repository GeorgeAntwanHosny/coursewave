"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { usePlans } from "@/hooks/use-plans";
import { useCheckout, useCurrentSubscription } from "@/hooks/use-subscriptions";
import { useAuthStore } from "@/stores/auth-store";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

function formatPrice(cents: number) {
  return `$${(cents / 100).toFixed(2)}`;
}

export default function PlansPage() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const { data: plans, isLoading, isError } = usePlans();
  const { data: currentSubscription } = useCurrentSubscription();
  const { mutate: checkout, isPending } = useCheckout();
  const [selectedPlanId, setSelectedPlanId] = useState<number | null>(null);
  const [switchTarget, setSwitchTarget] = useState<{ id: number; name: string } | null>(null);

  const startCheckout = (planId: number) => {
    setSelectedPlanId(planId);
    checkout(planId);
  };

  const handleSubscribe = (planId: number, planName: string) => {
    if (!user) {
      router.push(`/login?redirect=/plans`);
      return;
    }

    if (currentSubscription && currentSubscription.plan.id !== planId) {
      setSwitchTarget({ id: planId, name: planName });
      return;
    }

    startCheckout(planId);
  };

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="mb-8 text-center text-2xl font-semibold sm:text-3xl">Choose your plan</h1>

      {isLoading && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-56 w-full rounded-lg" />
          ))}
        </div>
      )}

      {isError && <p className="text-center text-destructive">Couldn't load plans. Please try again shortly.</p>}

      {!isLoading && !isError && plans && (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
          {plans.map((plan) => {
            const isCurrent = currentSubscription?.plan.id === plan.id;
            return (
              <Card key={plan.id} className={`flex flex-col ${isCurrent ? "border-primary" : ""}`}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-lg">{plan.name}</CardTitle>
                    {isCurrent && <Badge>Current plan</Badge>}
                  </div>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col justify-between gap-6">
                  <div>
                    <p className="text-3xl font-bold">{formatPrice(plan.price_cents)}</p>
                    <p className="text-sm text-muted-foreground">{plan.term_days} days of access</p>
                  </div>
                  <Button
                    className="w-full"
                    variant={isCurrent ? "outline" : "default"}
                    disabled={isCurrent || (isPending && selectedPlanId === plan.id)}
                    onClick={() => handleSubscribe(plan.id, plan.name)}
                  >
                    {isCurrent
                      ? "Current plan"
                      : isPending && selectedPlanId === plan.id
                        ? "Redirecting..."
                        : currentSubscription
                          ? "Switch to this plan"
                          : "Subscribe"}
                  </Button>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}

      <AlertDialog open={!!switchTarget} onOpenChange={(open) => !open && setSwitchTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Switch to {switchTarget?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              Your current plan ({currentSubscription?.plan.name}) will be canceled immediately. Any remaining
              unused days on it are not refunded or carried over to the new plan.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (switchTarget) startCheckout(switchTarget.id);
                setSwitchTarget(null);
              }}
            >
              Switch plan
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}