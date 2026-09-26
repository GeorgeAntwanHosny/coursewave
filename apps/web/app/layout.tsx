import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "LearnLedger",
  description: "Subscription-based learning platform with fair, transparent instructor payouts.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col font-sans">{children}</body>
    </html>
  );
}
