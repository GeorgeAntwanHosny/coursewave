import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { useAuthStore } from "@/stores/auth-store";
import { toast } from "sonner";

export type NotificationItem = {
  id: string;
  kind: "payout_processed" | "refund_processed" | "comment_reply" | "certificate_issued" | "system_test" | string;
  title: string;
  body: string;
  url: string | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string;
  created_at_human: string;
};

type NotificationPage = {
  items: NotificationItem[];
  meta: { current_page: number; last_page: number; total: number };
  unread_count: number;
};

export function useUnreadCount() {
  const token = useAuthStore((s) => s.token);
  return useQuery({
    queryKey: ["notifications-unread-count"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<{ unread_count: number }>>("/notifications/unread-count");
      return data.data?.unread_count ?? 0;
    },
    enabled: !!token,
    // Fallback so the badge still catches up if the live socket is down (hybrid delivery).
    refetchInterval: 60_000,
  });
}

/** `enabled` lets the bell fetch the list only once its popover is opened. */
export function useNotifications(enabled: boolean) {
  const token = useAuthStore((s) => s.token);
  return useInfiniteQuery({
    queryKey: ["notifications"],
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      const { data } = await apiClient.get<ApiEnvelope<NotificationPage>>("/notifications", {
        params: { page: pageParam },
      });
      return data.data as NotificationPage;
    },
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    enabled: enabled && !!token,
  });
}

export function useMarkRead() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: string) => {
      await apiClient.post(`/notifications/${id}/read`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["notifications"] });
      queryClient.invalidateQueries({ queryKey: ["notifications-unread-count"] });
    },
    onError: () => toast.error("Couldn't mark the notification as read."),
  });
}

export function useMarkAllRead() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      await apiClient.post("/notifications/read-all");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["notifications"] });
      queryClient.invalidateQueries({ queryKey: ["notifications-unread-count"] });
      toast.success("All notifications marked as read.");
    },
    onError: () => toast.error("Couldn't mark all notifications as read."),
  });
}