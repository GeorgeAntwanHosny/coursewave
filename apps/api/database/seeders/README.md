# Seeding order for a fresh environment

Run in this exact order after `php artisan migrate`:

1. `php artisan db:seed --class=RoleSeeder`
   Creates `student` / `instructor` / `admin` roles.

2. Generate Shield permissions for every existing Filament resource —
   run once per panel, listing every resource currently on that panel:

```bash
   php artisan shield:generate --panel=instructor --resource=CourseResource,LessonResource,QuizResource
   php artisan shield:generate --panel=admin --resource=CourseResource,LessonResource,QuizResource,PlanResource
```

   This is the step that actually CREATES permission rows. It must be
   re-run (adding the new resource to the list) every time a new Filament
   resource is added in a later phase — the RolePermissionSyncSeeder below
   only grants permissions that already exist; it never invents them.

3. `php artisan db:seed`
   Runs everything via DatabaseSeeder, in order:
   - `RoleSeeder` — student/instructor/admin roles
   - `PlanSeeder` — Monthly/3-Month/Annual plans
   - `RolePermissionSyncSeeder` — grants Shield permissions to roles
   - `CourseContentSeeder` — 8 published courses, each with 3–4 modules,
     2–4 lessons per module (mixed video/text), some with resource links,
     some with a 2-question quiz on the last lesson. Video lessons use
     real, freely-licensed sample clips (15 seconds to ~15 minutes) with
     accurate `video_duration_seconds`, so heartbeat tracking and
     mark-as-watched testing work against real playback. Text lessons are
     ~350–450 words, long enough to produce a believable estimated
     reading-time engagement value. Creates 4 instructor accounts
     (`instructor1@coursewave.test` through `instructor4@coursewave.test`,
     password `Password123`) if they don't already exist.
   Re-running `php artisan db:seed` is safe — every seeder here uses
   `firstOrCreate`/existence checks and will not duplicate data.

## After adding a new Filament resource in any future phase

1. `php artisan shield:generate --panel=<instructor|admin> --resource=NewResource`
2. `php artisan db:seed --class=RolePermissionSyncSeeder`

Skipping step 2 is why a new resource can silently not appear in the
sidebar for existing instructor/admin accounts — their role's permission
set simply never got the new permission added to it.

## Re-seeding course content only

`php artisan db:seed --class=CourseContentSeeder` re-runs just the course
content step. It checks for existing courses/modules by slug/relation
before creating, so it won't duplicate a course that's already seeded —
but it also won't regenerate modules for a course that already has any
(see `createModulesAndLessons`'s early return), so to get a fresh set of
sample lessons for a specific course, delete that course's modules first
or `php artisan migrate:fresh --seed` for a full reset.
