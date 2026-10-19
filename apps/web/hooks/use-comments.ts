import { useInfiniteQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { toast } from "sonner";

export type CommentItem = {
  id: number;
  parent_id: number | null;
  is_deleted: boolean;
  body: string | null;
  author: { id: number; name: string; avatar_url: string | null } | null;
  is_instructor: boolean;
  is_mine: boolean;
  can_edit: boolean;
  can_delete: boolean;
  edited: boolean;
  created_at: string;
  created_at_human: string;
  replies?: CommentItem[];
};

type CommentPage = {
  items: CommentItem[];
  meta: { current_page: number; last_page: number; total: number };
};

function errorMessage(err: any, fallback: string) {
  return err?.response?.data?.errors?.body?.[0] ?? err?.response?.data?.message ?? fallback;
}

export function useComments(lessonId: number | null) {
  return useInfiniteQuery({
    queryKey: ["lesson-comments", lessonId],
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      const { data } = await apiClient.get<ApiEnvelope<CommentPage>>(`/learning/lessons/${lessonId}/comments`, {
        params: { page: pageParam },
      });
      return data.data as CommentPage;
    },
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    enabled: !!lessonId,
  });
}

export function useCreateComment(lessonId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: { body: string; parent_id?: number | null }) => {
      const { data } = await apiClient.post<ApiEnvelope<CommentItem>>(`/learning/lessons/${lessonId}/comments`, payload);
      return data.data;
    },
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ["lesson-comments", lessonId] });
      toast.success(variables.parent_id ? "Reply posted." : "Comment posted.");
    },
    onError: (err: any) => toast.error(errorMessage(err, "Couldn't post your comment.")),
  });
}

export function useUpdateComment(lessonId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: { id: number; body: string }) => {
      const { data } = await apiClient.patch<ApiEnvelope<CommentItem>>(`/learning/comments/${payload.id}`, {
        body: payload.body,
      });
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["lesson-comments", lessonId] });
      toast.success("Comment updated.");
    },
    onError: (err: any) => toast.error(errorMessage(err, "Couldn't update your comment.")),
  });
}

export function useDeleteComment(lessonId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/learning/comments/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["lesson-comments", lessonId] });
      toast.success("Comment deleted.");
    },
    onError: (err: any) => toast.error(errorMessage(err, "Couldn't delete the comment.")),
  });
}