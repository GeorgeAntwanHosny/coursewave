"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Award, Banknote, Bell, CheckCheck, MessageSquare, Receipt } from "lucide-react";
import { useAuthStore } from "@/stores/auth-store";
import {
  NotificationItem,
  useMarkAllRead,
  useMarkRead,
  useNotifications,
  useUnreadCount,
} from "@/hooks/use-notifications";
import { openNotificationUrl } from "@/lib/notification-links";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

function KindIcon({ kind }: { kind: string }) {
  const className = "h-4 w-4";
  switch (kind) {
    case "payout_processed":
      return <Banknote className={className} />;
    case "refund_processed":
      return <Receipt className={className} />;
    case "comment_reply":
      return <MessageSquare className={className} />;
    case "certificate_issued":
      return <Award className={className} />;
    default:
      return <Bell className={className} />;
  }
}

export function NotificationBell() {
  const user = useAuthStore((s) => s.user);
  const router = useRouter();
  const [open, setOpen] = useState(false);

  const { data: unreadCount = 0 } = useUnreadCount();
  const list = useNotifications(open);
  const { mutate: markRead } = useMarkRead();
  const { mutate: markAllRead, isPending: isMarkingAll } = useMarkAllRead();

  if (!user) return null;

  const items = list.data?.pages.flatMap((page) => page.items) ?? [];

  const handleClick = (notification: NotificationItem) => {
    if (!notification.is_read) markRead(notification.id);
    setOpen(false);
    if (notification.url) openNotificationUrl(notification.url, router.push);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative h-9 w-9"
          aria-label={unreadCount > 0 ? `Notifications, ${unreadCount} unread` : "Notifications"}
        >
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground">
              {unreadCount > 99 ? "99+" : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>

      <PopoverContent align="end" className="w-[min(92vw,24rem)] p-0">
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <p className="text-sm font-semibold">Notifications</p>
          <Button
            variant="ghost"
            size="sm"
            className="h-7 px-2 text-xs"
            disabled={unreadCount === 0 || isMarkingAll}
            onClick={() => markAllRead()}
          >
            <CheckCheck className="mr-1 h-3.5 w-3.5" />
            {isMarkingAll ? "Marking..." : "Mark all read"}
          </Button>
        </div>

        <div className="max-h-96 overflow-y-auto">
          {list.isLoading && (
            <div className="space-y-4 p-4">
              {Array.from({ length: 3 }).map((_, i) => (
                <div key={i} className="flex gap-3">
                  <Skeleton className="h-8 w-8 shrink-0 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-2/3" />
                    <Skeleton className="h-3 w-full" />
                  </div>
                </div>
              ))}
            </div>
          )}

          {list.isError && (
            <div className="space-y-2 p-6 text-center">
              <p className="text-sm text-destructive">Couldn't load your notifications.</p>
              <Button variant="outline" size="sm" onClick={() => list.refetch()}>
                Try again
              </Button>
            </div>
          )}

          {!list.isLoading && !list.isError && items.length === 0 && (
            <div className="space-y-2 p-8 text-center">
              <Bell className="mx-auto h-8 w-8 text-muted-foreground" />
              <p className="text-sm text-muted-foreground">You're all caught up.</p>
            </div>
          )}

          {items.map((notification) => (
            <button
              key={notification.id}
              type="button"
              onClick={() => handleClick(notification)}
              className={cn(
                "flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-muted",
                !notification.is_read && "bg-primary/5"
              )}
            >
              <span
                className={cn(
                  "mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
                  notification.is_read ? "bg-muted text-muted-foreground" : "bg-primary/10 text-primary"
                )}
              >
                <KindIcon kind={notification.kind} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-start justify-between gap-2">
                  <span className={cn("text-sm", !notification.is_read && "font-semibold")}>{notification.title}</span>
                  {!notification.is_read && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" />}
                </span>
                {notification.body && (
                  <span className="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">{notification.body}</span>
                )}
                <span className="mt-1 block text-[11px] text-muted-foreground">{notification.created_at_human}</span>
              </span>
            </button>
          ))}

          {list.hasNextPage && (
            <div className="p-3">
              <Button
                variant="outline"
                size="sm"
                className="w-full"
                onClick={() => list.fetchNextPage()}
                disabled={list.isFetchingNextPage}
              >
                {list.isFetchingNextPage ? "Loading..." : "Load more"}
              </Button>
            </div>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}