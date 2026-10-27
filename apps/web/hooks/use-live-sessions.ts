import { useQuery } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { useAuthStore } from "@/stores/auth-store";

export type LiveSessionItem = {
  id: number;
  title: string;
  description: string | null;
  course: { id: number; title: string; slug: string };
  instructor_name: string;
  starts_at: string;
  ends_at: string;
  duration_minutes: number;
  status: "upcoming" | "live";
  join_opens_at: string;
  can_join: boolean;
  join_url: string | null; // null until the join window opens
};

// Polled so the Join button appears on its own when the window opens. A 403 (no subscription) is not retried.
const retryUnlessForbidden = (count: number, error: any) => error?.response?.status !== 403 && count < 2;

export function useLiveSessions() {
  const token = useAuthStore((s) => s.token);
  return useQuery({
    queryKey: ["live-sessions"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<{ items: LiveSessionItem[] }>>("/learning/live-sessions");
      return data.data?.items ?? [];
    },
    enabled: !!token,
    refetchInterval: 30_000,
    retry: retryUnlessForbidden,
  });
}

export function useCourseLiveSessions(slug: string) {
  const token = useAuthStore((s) => s.token);
  return useQuery({
    queryKey: ["live-sessions", slug],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<{ items: LiveSessionItem[] }>>(
        `/learning/courses/${slug}/live-sessions`
      );
      return data.data?.items ?? [];
    },
    enabled: !!token && !!slug,
    refetchInterval: 30_000,
    retry: retryUnlessForbidden,
  });
}