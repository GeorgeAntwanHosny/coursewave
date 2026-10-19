"use client";

import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { commentSchema, CommentFormValues } from "@/schemas/comment";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

type Props = {
  initialBody?: string;
  placeholder?: string;
  submitLabel: string;
  pendingLabel: string;
  isPending: boolean;
  autoFocus?: boolean;
  // `done` clears the form; the caller decides what else happens on success.
  onSubmit: (body: string, done: () => void) => void;
  onCancel?: () => void;
};

export function CommentForm({
  initialBody = "",
  placeholder,
  submitLabel,
  pendingLabel,
  isPending,
  autoFocus,
  onSubmit,
  onCancel,
}: Props) {
  const {
    register,
    handleSubmit,
    reset,
    watch,
    setFocus,
    formState: { errors },
  } = useForm<CommentFormValues>({
    resolver: zodResolver(commentSchema),
    defaultValues: { body: initialBody },
  });

  const length = watch("body")?.length ?? 0;

  useEffect(() => {
    if (autoFocus) setFocus("body");
  }, [autoFocus, setFocus]);

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit(values.body, () => reset({ body: "" })))}
      className="space-y-2"
      noValidate
    >
      <Textarea
        {...register("body")}
        rows={3}
        placeholder={placeholder}
        disabled={isPending}
        aria-invalid={!!errors.body}
        className="resize-y"
      />
      {errors.body && <p className="text-sm text-destructive">{errors.body.message}</p>}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className={cn("text-xs text-muted-foreground", length > 2000 && "text-destructive")}>{length}/2000</span>
        <div className="flex gap-2">
          {onCancel && (
            <Button type="button" variant="ghost" size="sm" onClick={onCancel} disabled={isPending}>
              Cancel
            </Button>
          )}
          <Button type="submit" size="sm" disabled={isPending}>
            {isPending ? pendingLabel : submitLabel}
          </Button>
        </div>
      </div>
    </form>
  );
}