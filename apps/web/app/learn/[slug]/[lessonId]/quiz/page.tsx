"use client";

import { useState } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import { useLesson } from "@/hooks/use-learning";
import { useQuiz, useSubmitQuiz } from "@/hooks/use-quiz";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";
import { CheckCircle2, XCircle, ArrowLeft } from "lucide-react";
import { toast } from "sonner";

export default function QuizPage() {
  const params = useParams<{ slug: string; lessonId: string }>();
  const lessonId = Number(params.lessonId);
  const { data: lesson, isLoading: isLessonLoading } = useLesson(lessonId);

  if (isLessonLoading) {
    return (
      <div className="mx-auto max-w-2xl space-y-4 px-4 py-10">
        <Skeleton className="h-6 w-1/3" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (!lesson?.quiz_id) {
    return (
      <div className="mx-auto max-w-md px-4 py-16 text-center">
        <p className="text-muted-foreground">This lesson has no quiz.</p>
      </div>
    );
  }

  return (
    <QuizForm
      slug={params.slug}
      lessonId={lessonId}
      quizId={lesson.quiz_id}
      courseTitle={lesson.course.title}
    />
  );
}

function QuizForm({
  slug,
  lessonId,
  quizId,
  courseTitle,
}: {
  slug: string;
  lessonId: number;
  quizId: number;
  courseTitle: string;
}) {
  const { data: quiz, isLoading, isError } = useQuiz(quizId);
  const { mutate: submit, isPending, data: result, isSuccess } = useSubmitQuiz(quizId);
  const [answers, setAnswers] = useState<Record<number, number>>({});

  if (isLoading) {
    return (
      <div className="mx-auto max-w-2xl space-y-4 px-4 py-10">
        <Skeleton className="h-6 w-1/3" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (isError || !quiz) {
    return (
      <div className="mx-auto max-w-md px-4 py-16 text-center">
        <p className="text-destructive">Couldn't load this quiz.</p>
      </div>
    );
  }

  const allAnswered = quiz.questions.every((q) => answers[q.id] != null);

  const handleSubmit = () => {
    if (!allAnswered) {
      toast.error("Please answer every question before submitting.");
      return;
    }
    submit(answers);
  };

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <Link
        href={`/learn/${slug}/${lessonId}`}
        className="flex items-center gap-1 text-sm text-muted-foreground hover:underline"
      >
        <ArrowLeft className="h-4 w-4" /> Back to lesson
      </Link>

      <h1 className="mt-2 text-xl font-semibold sm:text-2xl">{quiz.title}</h1>
      <p className="text-sm text-muted-foreground">{courseTitle}</p>

      {quiz.best_attempt && !isSuccess && (
        <p className="mt-2 text-sm text-muted-foreground">
          Best score so far: <span className="font-medium text-foreground">{quiz.best_attempt.score_percent}%</span>{" "}
          ({quiz.attempt_count} attempt{quiz.attempt_count !== 1 ? "s" : ""})
        </p>
      )}

      {isSuccess && result ? (
        <div className="mt-8 space-y-6">
          <Card>
            <CardContent className="py-6 text-center">
              <p className="text-3xl font-bold">{result.score_percent}%</p>
              <p className="text-sm text-muted-foreground">
                {result.correct_count} of {result.total_questions} correct
              </p>
            </CardContent>
          </Card>

          {result.answers.map((answer) => (
            <Card key={answer.question_id}>
              <CardHeader>
                <CardTitle className="flex items-start gap-2 text-base">
                  {answer.is_correct ? (
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                  ) : (
                    <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
                  )}
                  {answer.question}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-1 text-sm">
                {answer.options.map((option) => {
                  const wasSelected = option.id === answer.selected_option_id;
                  return (
                    <div
                      key={option.id}
                      className={`rounded-md px-3 py-2 ${
                        option.is_correct
                          ? "bg-green-500/10 text-green-700 dark:text-green-400"
                          : wasSelected
                            ? "bg-destructive/10 text-destructive"
                            : "text-muted-foreground"
                      }`}
                    >
                      {option.option_text}
                      {wasSelected && !option.is_correct && " (your answer)"}
                      {option.is_correct && " (correct answer)"}
                    </div>
                  );
                })}
              </CardContent>
            </Card>
          ))}

          <div className="flex gap-3">
            <Button variant="outline" onClick={() => window.location.reload()}>
              Retake quiz
            </Button>
            <Link href={`/learn/${slug}/${lessonId}`}>
              <Button>Back to lesson</Button>
            </Link>
          </div>
        </div>
      ) : (
        <div className="mt-8 space-y-6">
          {quiz.questions.map((question, qIndex) => (
            <Card key={question.id}>
              <CardHeader>
                <CardTitle className="text-base">
                  {qIndex + 1}. {question.question}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <RadioGroup
                  value={answers[question.id]?.toString()}
                  onValueChange={(value) => setAnswers((prev) => ({ ...prev, [question.id]: Number(value) }))}
                >
                  {question.options.map((option) => (
                    <div key={option.id} className="flex items-center space-x-2 py-1">
                      <RadioGroupItem value={option.id.toString()} id={`option-${option.id}`} />
                      <Label htmlFor={`option-${option.id}`} className="cursor-pointer font-normal">
                        {option.option_text}
                      </Label>
                    </div>
                  ))}
                </RadioGroup>
              </CardContent>
            </Card>
          ))}

          <Button onClick={handleSubmit} disabled={isPending || !allAnswered} className="w-full">
            {isPending ? "Submitting..." : "Submit quiz"}
          </Button>
        </div>
      )}
    </div>
  );
}