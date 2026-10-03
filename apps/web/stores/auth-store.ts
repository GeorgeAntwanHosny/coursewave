import { create } from "zustand";
import { persist } from "zustand/middleware";
import { setCookie, deleteCookie } from "@/lib/cookies";

type User = { id: number; name: string; email: string; role: string; avatar_url: string | null };

type AuthState = {
  user: User | null;
  token: string | null;
  setAuth: (user: User, token: string) => void;
  clear: () => void;
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      setAuth: (user, token) => {
        setCookie("cw_role", user.role);
        set({ user, token });
      },
      clear: () => {
        deleteCookie("cw_role");
        set({ user: null, token: null });
      },
    }),
    { name: "LearnLedger-auth" }
  )
);