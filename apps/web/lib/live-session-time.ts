// The API sends ISO 8601 instants. Formatting happens in the viewer's own time zone.

export function formatSessionStart(iso: string): string {
  return new Intl.DateTimeFormat(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    timeZoneName: "short",
  }).format(new Date(iso));
}

export function formatTimeOnly(iso: string): string {
  return new Intl.DateTimeFormat(undefined, { hour: "numeric", minute: "2-digit" }).format(new Date(iso));
}

/** "Starts in 2 days", "Starts in 3 h 10 min", "Starts in 12 min", "Starting now". */
export function describeCountdown(startIso: string, nowMs: number): string {
  const minutes = Math.ceil((Date.parse(startIso) - nowMs) / 60_000);

  if (minutes <= 0) return "Starting now";
  if (minutes < 60) return `Starts in ${minutes} min`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    const rest = minutes % 60;
    return rest ? `Starts in ${hours} h ${rest} min` : `Starts in ${hours} h`;
  }

  const days = Math.floor(hours / 24);
  return `Starts in ${days} day${days === 1 ? "" : "s"}`;
}