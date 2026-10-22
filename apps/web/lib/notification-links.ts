/** Frontend paths use the router; absolute URLs (e.g. the Filament payouts page) leave the app. */
export function openNotificationUrl(url: string, push: (path: string) => void) {
  if (/^https?:\/\//i.test(url)) {
    window.location.href = url;
  } else {
    push(url);
  }
}