import { useQuery } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";

type CourseListItem = {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  thumbnail_url: string | null;
  instructor_name: string;
  modules_count: number;
};

type CourseListData = {
  items: CourseListItem[];
  meta: { current_page: number; last_page: number; total: number };
};

type CourseLesson = { id: number; title: string; type: "video" | "text"; has_quiz: boolean };
type CourseModule = { id: number; title: string; lessons: CourseLesson[] };

type CourseDetail = {
  id: number;
  title: string;
  slug: string;
  description: string | null;
  thumbnail_url: string | null;
  instructor_name: string;
  modules: CourseModule[];
};

export function useCourses(search?: string) {
  return useQuery({
    queryKey: ["courses", search ?? ""],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<CourseListData>>("/courses", {
        params: { search },
      });
      return data.data;
    },
  });
}

export function useCourse(slug: string) {
  return useQuery({
    queryKey: ["course", slug],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<CourseDetail>>(`/courses/${slug}`);
      return data.data;
    },
    enabled: !!slug,
  });
}