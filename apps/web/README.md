<div align="center">

# 🎓 CourseWave Web

**The student app: a fast, responsive, accessible learning experience in light and dark mode.**

![Next.js](https://img.shields.io/badge/Next.js-16-000000?logo=nextdotjs&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind-CSS-06B6D4?logo=tailwindcss&logoColor=white)
![TanStack Query](https://img.shields.io/badge/TanStack-Query-FF4154)

</div>

---

## 💡 What this app does

It is where students discover courses, subscribe, learn, chat with instructors, join live sessions and collect certificates. Every screen is built to feel like a real product: responsive from phone to widescreen, themed for light and dark, and never left blank or silent while loading or failing.

**Business value:** a clear, trustworthy path from landing page to paid subscriber to certified learner, with live pricing straight from the API.

---

## ✨ What students can do

| | |
|---|---|
| 🏠 **Landing page** | Hero with an earnings-split preview, features, how it works, live pricing, FAQ |
| 🔐 **Account** | Register, log in, protected routes |
| 💳 **Subscribe** | Plan selection and Stripe Checkout, plan switching, self-service cancel |
| 📚 **Learn** | Course sidebar, video and text lessons, quizzes, resource links |
| 📈 **Progress** | Per-lesson tracking and course completion |
| 💬 **Discuss** | Threaded comments with instructor replies highlighted |
| 🏅 **Certificates** | Download a PDF and share a public verification link |
| 🎥 **Live sessions** | Countdown and a Join button that appears automatically |
| 🔔 **Notifications** | Live toasts over WebSockets and a persistent list with unread badge |

---

## 🧠 Technical highlights

| Topic | Approach |
|---|---|
| **Server state** | TanStack Query for fetching, caching and retries, with a hook per feature |
| **Client state** | Zustand for the small amount of UI and auth state |
| **Real-time** | One WebSocket connection (Laravel Echo with Reverb) in the root layout, with polling as a fallback |
| **Forms** | `react-hook-form` and `zod` on the client, mirrored by Laravel Form Requests on the server |
| **UI feedback** | Skeletons that match the final layout, toasts for errors, explicit idle, loading, error and success states |
| **Theming** | Light and dark through design tokens, no flash on load |
| **Route protection** | Middleware gate plus a role cookie, with public routes like certificate verification left open |
| **Time handling** | Server sends ISO instants and the browser formats them in the viewer's time zone |
| **Accessibility** | Semantic HTML, labelled controls, keyboard-friendly, honors reduced motion |
| **Performance** | Server components where possible, static landing page, cached plan queries |

---

## 🧱 Project structure

```
apps/web/
├── app/
│   ├── page.tsx                 # Landing page
│   ├── (auth)/                  # login, register
│   ├── learn/[slug]/            # Course player
│   ├── live/                    # Live sessions
│   └── verify/                  # Public certificate verification
├── components/
│   ├── landing/                 # Landing page sections
│   ├── learn/                   # Course sidebar, lesson views, quizzes, comments
│   ├── live/                    # Live session cards
│   ├── notifications/           # Bell, realtime listener
│   ├── layout/                  # Navbar and shell
│   └── ui/                      # shadcn/ui primitives
├── hooks/                       # TanStack Query hooks, one per feature
├── lib/                         # API client, Echo setup, formatting helpers
└── middleware.ts                # Route protection
```

Flat layout (no `src/`), with the `@/*` alias pointing at the project root.

---

## 🎨 UX standards

- ✅ Responsive on mobile, tablet and desktop
- ✅ Light and dark mode on every component
- ✅ Skeleton loading that matches the real layout
- ✅ Errors as toasts, with a retry where it makes sense
- ✅ Buttons disabled while a request is running
- ✅ Empty states that explain what to do next

---

## ▶️ Running it

**With Docker (recommended):** from the repo root, `.\scripts\setup.ps1 -Mode demo`, then open http://localhost:3000. See [`../../DOCKER.md`](../../DOCKER.md).

**Locally:**

```bash
npm install
npm run dev          # http://localhost:3000
```

```bash
npx tsc --noEmit     # type check
npm run build        # production build
npm run lint
```

### Environment variables (`.env.local`)

| Variable | Meaning |
|---|---|
| `NEXT_PUBLIC_API_URL` | The Laravel API, for example `http://localhost:8000/api` |
| `NEXT_PUBLIC_REVERB_APP_KEY` | Public Reverb app key |
| `NEXT_PUBLIC_REVERB_HOST` / `_PORT` / `_SCHEME` | Where the browser reaches the WebSocket server |

`NEXT_PUBLIC_*` values are baked in at build time, so changing one means rebuilding. Only the public key is ever exposed, never the secret.