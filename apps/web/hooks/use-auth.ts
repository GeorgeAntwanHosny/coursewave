import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { apiClient, ApiEnvelope } from "@/lib/api-client";
import { useAuthStore } from "@/stores/auth-store";
import { toast } from "sonner";

type User = { id: number; name: string; email: string; role: string; avatar_url: string | null };

export function useRegister() {
  return useMutation({
    mutationFn: async (payload: {
      name: string;
      email: string;
      password: string;
      password_confirmation: string;
      role: "student" | "instructor";
    }) => {
      const { data } = await apiClient.post<ApiEnvelope<User>>("/register", payload);
      return data;
    },
    onSuccess: (data) => toast.success(data.message),
    onError: (err: any) => {
      const msg = err?.response?.data?.message ?? "Registration failed.";
      toast.error(msg);
    },
  });
}

export function useLogin() {
  const setAuth = useAuthStore((s) => s.setAuth);
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: { email: string; password: string }) => {
      const { data } = await apiClient.post<ApiEnvelope<{ user: User; token: string }>>("/login", payload);
      return data;
    },
    onSuccess: (data) => {
      if (data.data) setAuth(data.data.user, data.data.token);
      queryClient.clear();
      toast.success(data.message);
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? "Login failed.");
    },
  });
}

export function useLogout() {
  const clear = useAuthStore((s) => s.clear);
  const queryClient = useQueryClient();
  const router = useRouter();

  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<ApiEnvelope<null>>("/logout");
      return data;
    },
    onSuccess: (data) => {
      clear();
      queryClient.clear();
      toast.success(data.message);
      router.push("/login");
    },
    onError: () => {
      clear();
      queryClient.clear();
      router.push("/login");
    },
  });
}

export function useMe() {
  const token = useAuthStore((s) => s.token);
  return useQuery({
    queryKey: ["me"],
    queryFn: async () => {
      const { data } = await apiClient.get<ApiEnvelope<User>>("/me");
      return data.data;
    },
    enabled: !!token,
  });
}