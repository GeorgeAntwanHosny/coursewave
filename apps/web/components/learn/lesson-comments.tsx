"use client";

import { useComments, useCreateComment } from "@/hooks/use-comments";
import { CommentForm } from "@/components/learn/comment-form";
import { CommentItem } from "@/components/learn/comment-item";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";

export function LessonComments({ lessonId }: { lessonId: number }) {
  const { data, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage } = useComments(lessonId);
  const { mutate: createComment, isPending } = useCreateComment(lessonId);

  const items = data?.pages.flatMap((page) => page.items) ?? [];
  const total = data?.pages[0]?.meta.total ?? 0;

  return (
    <Card className="mt-6">
      <CardHeader>
        <CardTitle className="text-base">Discussion{total > 0 && ` (${total})`}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        <CommentForm
          placeholder="Ask a question or share a thought..."
          submitLabel="Post comment"
          pendingLabel="Posting..."
          isPending={isPending}
          onSubmit={(body, done) => createComment({ body }, { onSuccess: done })}
        />

        {isLoading && (
          <div className="space-y-4">
            {Array.from({ length: 3 }).map((_, i) => (
              <div key={i} className="flex gap-3">
                <Skeleton className="h-8 w-8 shrink-0 rounded-full" />
                <div className="flex-1 space-y-2">
                  <Skeleton className="h-4 w-1/3" />
                  <Skeleton className="h-12 w-full" />
                </div>
              </div>
            ))}
          </div>
        )}

        {isError && (
          <div className="space-y-2 text-center">
            <p className="text-sm text-destructive">Couldn't load the discussion.</p>
            <Button variant="outline" size="sm" onClick={() => refetch()}>
              Try again
            </Button>
          </div>
        )}

        {!isLoading && !isError && items.length === 0 && (
          <p className="text-center text-sm text-muted-foreground">No comments yet. Start the discussion.</p>
        )}

        {items.length > 0 && (
          <div className="space-y-5">
            {items.map((comment) => (
              <CommentItem key={comment.id} comment={comment} lessonId={lessonId} />
            ))}
          </div>
        )}

        {hasNextPage && (
          <Button variant="outline" className="w-full" onClick={() => fetchNextPage()} disabled={isFetchingNextPage}>
            {isFetchingNextPage ? "Loading..." : "Load more comments"}
          </Button>
        )}
      </CardContent>
    </Card>
  );
}