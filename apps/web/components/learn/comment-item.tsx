"use client";

import { useState } from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
import { CommentForm } from "@/components/learn/comment-form";
import { CommentItem as Comment, useCreateComment, useDeleteComment, useUpdateComment } from "@/hooks/use-comments";
import { cn } from "@/lib/utils";

export function CommentItem({ comment, lessonId, isReply = false }: { comment: Comment; lessonId: number; isReply?: boolean }) {
  const [replying, setReplying] = useState(false);
  const [editing, setEditing] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const { mutate: createReply, isPending: isReplying } = useCreateComment(lessonId);
  const { mutate: updateComment, isPending: isUpdating } = useUpdateComment(lessonId);
  const { mutate: deleteComment, isPending: isDeleting } = useDeleteComment(lessonId);

  const replies = comment.replies ?? [];

  return (
    <div className="space-y-3">
      {comment.is_deleted ? (
        <div className="rounded-lg border border-dashed border-border p-3 text-sm italic text-muted-foreground">
          This comment was deleted.
        </div>
      ) : (
        <div
          className={cn(
            "rounded-lg border p-3 sm:p-4",
            comment.is_instructor ? "border-primary/40 bg-primary/5" : "border-border bg-card"
          )}
        >
          <div className="flex items-start gap-3">
            <Avatar className="h-8 w-8 shrink-0">
              {comment.author?.avatar_url && <AvatarImage src={comment.author.avatar_url} alt={comment.author.name} />}
              <AvatarFallback className="text-xs">{comment.author?.name.slice(0, 2).toUpperCase()}</AvatarFallback>
            </Avatar>

            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="text-sm font-medium">{comment.author?.name}</span>
                {comment.is_instructor && <Badge>Instructor</Badge>}
                <span className="text-xs text-muted-foreground">
                  {comment.created_at_human}
                  {comment.edited && " · edited"}
                </span>
              </div>

              {editing ? (
                <div className="mt-2">
                  <CommentForm
                    initialBody={comment.body ?? ""}
                    submitLabel="Save"
                    pendingLabel="Saving..."
                    isPending={isUpdating}
                    autoFocus
                    onCancel={() => setEditing(false)}
                    onSubmit={(body, done) =>
                      updateComment(
                        { id: comment.id, body },
                        {
                          onSuccess: () => {
                            done();
                            setEditing(false);
                          },
                        }
                      )
                    }
                  />
                </div>
              ) : (
                // Rendered as escaped plain text — never as HTML.
                <p className="mt-1 whitespace-pre-wrap break-words text-sm">{comment.body}</p>
              )}

              {!editing && (
                <div className="mt-2 flex flex-wrap gap-1">
                  {!isReply && (
                    <Button variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => setReplying((v) => !v)}>
                      Reply
                    </Button>
                  )}
                  {comment.can_edit && (
                    <Button variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => setEditing(true)}>
                      Edit
                    </Button>
                  )}
                  {comment.can_delete && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-7 px-2 text-xs text-destructive hover:text-destructive"
                      onClick={() => setConfirmOpen(true)}
                    >
                      Delete
                    </Button>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {replying && !comment.is_deleted && (
        <div className="ml-4 sm:ml-11">
          <CommentForm
            placeholder={`Reply to ${comment.author?.name ?? "this comment"}...`}
            submitLabel="Reply"
            pendingLabel="Posting..."
            isPending={isReplying}
            autoFocus
            onCancel={() => setReplying(false)}
            onSubmit={(body, done) =>
              createReply(
                { body, parent_id: comment.id },
                {
                  onSuccess: () => {
                    done();
                    setReplying(false);
                  },
                }
              )
            }
          />
        </div>
      )}

      {replies.length > 0 && (
        <div className="ml-4 space-y-3 border-l border-border pl-3 sm:ml-11 sm:pl-4">
          {replies.map((reply) => (
            <CommentItem key={reply.id} comment={reply} lessonId={lessonId} isReply />
          ))}
        </div>
      )}

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this comment?</AlertDialogTitle>
            <AlertDialogDescription>
              {replies.length > 0
                ? "It will stay in the thread as “deleted” so the replies keep their context."
                : "This can't be undone."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeleting}>Keep it</AlertDialogCancel>
            <AlertDialogAction
              disabled={isDeleting}
              onClick={(e) => {
                e.preventDefault(); // stay open until the request finishes
                deleteComment(comment.id, { onSuccess: () => setConfirmOpen(false) });
              }}
            >
              {isDeleting ? "Deleting..." : "Delete"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}