"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useAuthStore } from "@/stores/auth-store";
import { createEcho, RealtimeClient } from "@/lib/echo";
import { openNotificationUrl } from "@/lib/notification-links";

export function RealtimeNotificationsListener() {
  const token = useAuthStore((s) => s.token);
  const userId = useAuthStore((s) => s.user?.id);
  const queryClient = useQueryClient();
  const router = useRouter();

  useEffect(() => {
    if (!token || !userId) return;

    let cancelled = false;
    let client: RealtimeClient | null = null;
    const channelName = `App.Models.User.${userId}`;

    const refresh = () => {
      queryClient.invalidateQueries({ queryKey: ["notifications"] });
      queryClient.invalidateQueries({ queryKey: ["notifications-unread-count"] });
    };

    createEcho(token)
      .then((echo) => {
        if (cancelled) {
          echo.disconnect();
          return;
        }
        client = echo;

        echo.private(channelName).notification((payload: { title?: string; body?: string; url?: string }) => {
          refresh();
          toast(payload.title ?? "New notification", {
            description: payload.body,
            action: payload.url
              ? { label: "View", onClick: () => openNotificationUrl(payload.url as string, router.push) }
              : undefined,
          });
        });

        // After a reconnect, catch up on anything broadcast while the socket was down.
        echo.connector?.pusher?.connection?.bind("connected", refresh);
      })
      .catch(() => {
        // Realtime is best-effort: the list, the focus refetch and the 60s poll still keep the bell correct.
      });

    return () => {
      cancelled = true;
      if (client) {
        client.leave(channelName);
        client.disconnect();
      }
    };
  }, [token, userId, queryClient, router]);

  return null;
}