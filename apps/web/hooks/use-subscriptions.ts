import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
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
  return useQuery({
    queryKey: ["current-subscription"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<Subscription | null>>("/subscriptions/current");
      return data.data;
    },
  });
}