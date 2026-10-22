// Minimal shape of what we use from Laravel Echo, so a library typing change can't break the build.
export type RealtimeClient = {
  private(channel: string): { notification(callback: (payload: any) => void): unknown };
  leave(channel: string): void;
  disconnect(): void;
  connector?: { pusher?: { connection?: { bind(event: string, callback: () => void): void } } };
};

/**
 * Creates an Echo client for the Reverb server. Imported dynamically so nothing touches
 * `window` during server rendering. Private channels are authorized against the Laravel
 * API with the Sanctum bearer token (the Next.js app has no session cookie).
 */
export async function createEcho(token: string): Promise<RealtimeClient> {
  const [{ default: Echo }, { default: Pusher }] = await Promise.all([import("laravel-echo"), import("pusher-js")]);
  (window as unknown as { Pusher: unknown }).Pusher = Pusher;

  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "http";
  const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080);

  const options = {
    broadcaster: "reverb",
    key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? "localhost",
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: `${process.env.NEXT_PUBLIC_API_URL}/broadcasting/auth`,
    auth: { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } },
  };

  return new Echo(options as never) as unknown as RealtimeClient;
}