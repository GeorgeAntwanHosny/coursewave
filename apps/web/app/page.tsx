"use client";

import { useEffect, useState } from "react";

type PingResponse = {
  message: string;
  app: string;
  time: string;
};

export default function Home() {
  const [status, setStatus] = useState<"loading" | "ok" | "error">("loading");
  const [data, setData] = useState<PingResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

  useEffect(() => {
    fetch(`${apiUrl}/ping`)
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then((json: PingResponse) => {
        setData(json);
        setStatus("ok");
      })
      .catch((err: Error) => {
        setError(err.message);
        setStatus("error");
      });
  }, [apiUrl]);

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-6 font-sans dark:bg-black">
      <main className="flex w-full max-w-lg flex-col items-center gap-6 rounded-2xl border border-black/10 bg-white p-10 text-center shadow-sm dark:border-white/10 dark:bg-zinc-900">
        <h1 className="text-2xl font-semibold tracking-tight text-black dark:text-zinc-50">
          CourseWave
        </h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400">
          Next.js ↔ Laravel connection test
        </p>

        {status === "loading" && (
          <p className="text-sm text-zinc-500">Pinging the API…</p>
        )}

        {status === "ok" && data && (
          <div className="w-full rounded-lg bg-green-50 p-4 text-left text-sm text-green-800 dark:bg-green-950 dark:text-green-300">
            <p className="font-medium">✅ Connected</p>
            <p className="mt-1">{data.message}</p>
            <p className="mt-1 text-xs opacity-70">app: {data.app}</p>
            <p className="text-xs opacity-70">server time: {data.time}</p>
          </div>
        )}

        {status === "error" && (
          <div className="w-full rounded-lg bg-red-50 p-4 text-left text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
            <p className="font-medium">❌ Could not reach the API</p>
            <p className="mt-1">{error}</p>
            <p className="mt-2 text-xs opacity-70">
              Checked: {apiUrl}/api/ping — make sure `php artisan serve` is
              running and CORS allows {`http://localhost:3000`}.
            </p>
          </div>
        )}
      </main>
    </div>
  );
}
