<div align="center">

# 📮 CourseWave API · Postman Collection

**Every endpoint the student app uses, documented, example-backed and self-testing, in one file.**

![Postman](https://img.shields.io/badge/Postman-Collection_v2.1-FF6C37?logo=postman&logoColor=white)
![Requests](https://img.shields.io/badge/Folders-10-4CAF50)
![Auth](https://img.shields.io/badge/Auth-Sanctum_Bearer-635BFF)

</div>

---

## ⚡ Quick start (1 minute)

1. **Start the API.** From the repo root: `.\scripts\setup.ps1 -Mode demo` (see [`../DOCKER.md`](../DOCKER.md)).
2. **Import one file:** `postman/CourseWave.postman_collection.json`.
   That is all you need. It already points at `http://localhost:8000`, the Docker address.
3. **Send `1. Authentication → Login`.** Its test script stores the bearer token, so every other request is authenticated automatically.
4. **Send `2. Catalog → List published courses`.** It stores `course_slug` for the requests that follow.
5. **Run a whole folder** with the Collection Runner. Requests chain through variables.

> No environment file is required. To use another server, edit the `base_url` collection variable (Collection → Variables → *Current value*).

---

## 🗂️ What's inside

| # | Folder | What it covers | Access |
|--:|---|---|---|
| 1 | **Authentication** | Register, login, current user, logout | 🌐 / 🔑 |
| 2 | **Catalog** | List, search and open published courses | 🌐 |
| 3 | **Plans & Subscriptions** | Plans, Stripe Checkout, confirm, current, cancel with refund | 🌐 / 🔑 |
| 4 | **Learning** | Course tree, lessons, progress heartbeats, quizzes, live sessions | 💳 |
| 5 | **Discussion** | Threaded comments, replies, edit, delete | 💳 |
| 6 | **Certificates** | List, PDF download, public verification | 🔑 / 🌐 |
| 7 | **Notifications & Realtime** | Stored list, unread count, mark read, channel auth | 🔑 |
| 8 | **Webhooks** | Stripe webhook (documentation) | 🌐 (signed) |
| 9 | **Developer tools** | Complete courses, reset progress | 🧪 |
| 10 | **Operations** | Liveness probe | 🌐 |

| Badge | Meaning |
|---|---|
| 🌐 | Public, no token |
| 🔑 | Any signed-in user |
| 💳 | Signed in **and** an active subscription |
| 🧪 | Local/demo only. Returns 404 in production |

---

## 🔐 Test accounts

Created by the demo setup (`.\scripts\setup.ps1 -Mode demo`).

| Account | Password | Notes |
|---|---|---|
| `student@llm.com` | `student@12334` | Student **without** a subscription |
| `instructor@llm.com` | `instructor@12334` | Can reply to comments on their own lessons |
| `admin@llm.com` | `admin@12334` | Uses the Filament panel, not this API |
| `demo.student01@llm.com` … `demo.student26@llm.com` | `Password123` | **Active subscriptions** and learning history |

Switch accounts by changing the `login_email` and `login_password` collection variables, then re-send **Login**.

> Production has no default accounts. Create the admin with `coursewave:create-admin`, and register students through the app.

---

## 💳 Paying in Stripe test mode

Folders 4 and 5 need an active subscription. Either log in as a demo student, or:

1. Send **3 → Initiate checkout**. The checkout URL is printed in the Postman Console.
2. Open the URL and pay with `4242 4242 4242 4242` (any future date, any CVC).
3. Send **3 → Confirm subscription**. It is idempotent, and safe even if the webhook got there first.

---

## 🧠 How the collection works

| Feature | Detail |
|---|---|
| **Auto-auth** | A bearer token is set once on the collection. Public requests override it with *No Auth* |
| **Variable chaining** | Test scripts store `token`, `user_id`, `course_slug`, `plan_id`, `lesson_id`, `quiz_id`, `comment_id`, `notification_id`, `certificate_id` and more |
| **Built-in tests** | Every request asserts status and response shape. The collection also asserts no 5xx and a response time under 3 s |
| **Saved examples** | Key requests include 200, 403 and 422 example responses |
| **Secret safety** | Tests check that the raw `meeting_url` and any `email` are never exposed |

### Response envelope

Every JSON response has the same shape:

```json
{ "success": true, "message": "...", "data": {}, "errors": null }
```

### Status codes

| Code | Meaning |
|---|---|
| 200 / 201 | Success / created |
| 401 | Missing or invalid token |
| 403 | No active subscription, or not allowed |
| 404 | Not found (also used for records that belong to someone else) |
| 409 | Certificate PDF is still being generated |
| 422 | Validation failed, see `errors` |
| 429 | Rate limited |

Money is always integer cents (`*_cents`). Dates are ISO 8601.

---

## 🔌 Realtime

Notifications are also pushed over Laravel Reverb (WebSocket, port 8080) on the private channel `private-App.Models.User.{id}`. Folder 7 documents the stored list and the channel-auth call that Laravel Echo makes.

## 🔔 Webhooks

Stripe calls `POST /api/webhooks/stripe` and verifies the signature, so a hand-made Postman request is rejected on purpose. To test it locally:

```powershell
stripe listen --forward-to localhost:8000/api/webhooks/stripe
stripe trigger checkout.session.completed
```

Put the printed `whsec_...` secret in `STRIPE_WEBHOOK_SECRET`.

---

## 🩺 Troubleshooting

| Symptom | Fix |
|---|---|
| Connection refused | `docker compose ps`. `web` must be Up on port 8000 |
| 401 on every request | Send **Login** first. Make sure no Postman **environment** defines an empty `token` |
| 403 in folders 4 and 5 | The account has no active subscription. Use a demo student, or pay in test mode |
| Folder 9 returns 404 | The developer tools are off in production mode. Use `-Mode demo` |
| `/healthz` returns 404 | It exists only in the Docker image, not under Herd |
| Certificate download returns 409 | The PDF is still being generated. Retry in a moment |

> If you create your own Postman environment, define **only** `base_url` in it. Environment values override collection values, so an empty `token` there would hide the token the Login script stores.