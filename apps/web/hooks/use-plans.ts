import { useQuery } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";

type Plan = {
  id: number;
  name: string;
  slug: string;
  billing_interval: "monthly" | "quarterly" | "annual";
  term_days: number;
  price_cents: number;
};

export function usePlans() {
  return useQuery({
    queryKey: ["plans"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<Plan[]>>("/plans");
      return data.data ?? [];
    },
  });
}