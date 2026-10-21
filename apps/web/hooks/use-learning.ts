import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { toast } from "sonner";

type LessonSummary = {
  id: number;
  title: string;
  type: "video" | "text";
  order: number;
  has_quiz: boolean;
  quiz_id: number | null;
  status: "not_started" | "in_progress" | "completed";
};

type ModuleSummary = { id: number; title: string; lessons: LessonSummary[] };
type CourseLearn = { id: number; title: string; slug: string; instructor_name: string; modules: ModuleSummary[] };
type CourseLearnData = { course: CourseLearn; completion_percent: number };

type LessonDetail = {
  id: number;
  title: string;
  type: "video" | "text";
  video_url: string | null;
  video_duration_seconds: number | null;
  body: string | null;
  order: number;
  course: { id: number; title: string; slug: string };
  module: { id: number; title: string };
  resource_links: { id: number; title: string; url: string }[];
  has_quiz: boolean;
  quiz_id: number | null;
  progress: { status: "not_started" | "in_progress" | "completed"; watched_seconds: number };
  previous_lesson_id: number | null;
  next_lesson_id: number | null;
};

export function useCourseLearn(slug: string) {
  return useQuery({
    queryKey: ["learn-course", slug],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<CourseLearnData>>(`/learning/courses/${slug}`);
      return data.data;
    },
    enabled: !!slug,
  });
}

export function useLesson(lessonId: number | null) {
  return useQuery({
    queryKey: ["learn-lesson", lessonId],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<LessonDetail>>(`/learning/lessons/${lessonId}`);
      return data.data;
    },
    enabled: !!lessonId,
  });
}

export function useRecordProgress(lessonId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (payload: { watched_seconds?: number; completed?: boolean }) => {
      const { data } = await apiClient.post<
        ApiEnvelope<{ status: string; watched_seconds: number; certificate_issued?: boolean }>
      >(`/learning/lessons/${lessonId}/progress`, payload);
      return data.data;
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["learn-lesson", lessonId] });
      queryClient.invalidateQueries({ queryKey: ["learn-course"] });

      if (data?.certificate_issued) {
        queryClient.invalidateQueries({ queryKey: ["certificates"] });
        toast.success("🎉 Course completed! Your certificate is being prepared — find it under Certificates.");
      }
    },
  });
}

export type { LessonSummary, ModuleSummary, CourseLearn };