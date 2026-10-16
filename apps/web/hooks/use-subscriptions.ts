import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { useAuthStore } from "@/stores/auth-store";
import { toast } from "sonner";

type Subscription = {
  id: number;
  status: "pending" | "active" | "canceled" | "expired";
  provider: string;
  plan: { id: number; name: string; term_days: number };
  total_amount_cents: number;
  term_days: number;
  starts_at: string | null;
  ends_at: string | null;
  starts_at_human: string | null;
  ends_at_human: string | null;
  // Only on /subscriptions/current: what cancelling right now would refund.
  refundable_cents?: number;
  refundable_human?: string;
};

type Refund = {
  id: number;
  amount_cents: number;
  amount_human: string;
  status: "pending" | "submitted" | "succeeded" | "failed" | "uncertain" | "reconciling";
  reason: string;
};

export function useCheckout() {
  return useMutation({
    mutationFn: async (planId: number) => {
      const { data } = await apiClient.post<ApiEnvelope<{ checkout_url: string }>>(
        "/subscriptions/checkout",
        { plan_id: planId }
      );
      return data.data;
    },
    onSuccess: (data) => {
      if (data?.checkout_url) window.location.href = data.checkout_url;
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? "Couldn't start checkout.");
    },
  });
}

export function useConfirmSubscription() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (sessionId: string) => {
      const { data } = await apiClient.post<ApiEnvelope<Subscription>>(
        "/subscriptions/confirm",
        { session_id: sessionId }
      );
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["current-subscription"] });
      queryClient.invalidateQueries({ queryKey: ["my-subscriptions"] });
    },
  });
}

export function useMySubscriptions() {
  return useQuery({
    queryKey: ["my-subscriptions"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<Subscription[]>>("/subscriptions");
      return data.data ?? [];
    },
  });
}

export function useCurrentSubscription() {
  const token = useAuthStore((s) => s.token);
  return useQuery({
    queryKey: ["current-subscription"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<Subscription | null>>("/subscriptions/current");
      return data.data;
    },
    enabled: !!token,
  });
}

export function useCancelSubscription() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<
        ApiEnvelope<{ subscription: Subscription; refund: Refund | null }>
      >("/subscriptions/current/cancel");
      return data.data;
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["current-subscription"] });
      queryClient.invalidateQueries({ queryKey: ["my-subscriptions"] });
      // Course content is gated on an active subscription, so drop cached lessons too.
      queryClient.invalidateQueries({ queryKey: ["learn-course"] });
      toast.success(
        data?.refund
          ? `Subscription canceled. A refund of ${data.refund.amount_human} is being processed.`
          : "Subscription canceled. There were no unused days to refund."
      );
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? "Couldn't cancel your subscription.");
    },
  });
}