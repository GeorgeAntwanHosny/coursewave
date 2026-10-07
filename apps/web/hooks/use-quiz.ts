import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiClient, ApiEnvelope } from "@/lib/api-client";

type QuizOption = { id: number; option_text: string; is_correct?: boolean };
type QuizQuestion = { id: number; question: string; options: QuizOption[] };
type BestAttempt = { score_percent: number; correct_count: number; total_questions: number; submitted_at: string } | null;

type QuizTake = {
  id: number;
  title: string;
  lesson_id: number;
  questions: QuizQuestion[];
  best_attempt: BestAttempt;
  attempt_count: number;
};

type QuizResult = {
  id: number;
  score_percent: number;
  correct_count: number;
  total_questions: number;
  submitted_at: string;
  answers: {
    question_id: number;
    question: string;
    selected_option_id: number;
    is_correct: boolean;
    options: QuizOption[];
  }[];
};

export function useQuiz(quizId: number | null) {
  return useQuery({
    queryKey: ["quiz", quizId],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<QuizTake>>(`/learning/quizzes/${quizId}`);
      return data.data;
    },
    enabled: !!quizId,
  });
}

export function useSubmitQuiz(quizId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (answers: Record<number, number>) => {
      const { data } = await apiClient.post<ApiEnvelope<QuizResult>>(`/learning/quizzes/${quizId}/submit`, {
        answers,
      });
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["quiz", quizId] });
    },
  });
}