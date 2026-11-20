# local_kreck — KRECK MicroLearn data backbone

The data layer for KRECK MicroLearn. It holds the **Layer-1 (KRECK SMS)**
information core LMS does not — the lifelong student record, attendance,
fee accounts, learning streaks and topic-struggle analytics — and exposes a
clean API and web services so the school system, the mobile app and the
`theme_microlearn` dashboards all read and write **one shared student record**.

## What it provides

- **Database tables** (`db/install.xml`): learner data, manifest sync receipts,
  and a durable LMS-to-SMS change-event outbox.
- **Persistent classes** (`classes/persistent/`): typed, validated CRUD on each
  table via `core\persistent`.
- **API facade** (`local_kreck\api`): `get_learner_summary($userid)` returns a
  stable shape — identity, attendance rate, fee balance, streak and the
  learner's hardest topics — degrading gracefully to zeros when there's no data
  yet. This is the one contract the theme and blocks consume; they never touch
  the database directly.
- **Web services** (`db/services.php`, `classes/external/`):
  - `local_kreck_get_learner_summary` (read) — for the app/dashboards.
  - `local_kreck_ingest_attendance` (write) — the KRECK register sync.
  - `local_kreck_ingest_fees` (write) — fee balance / debit-order status.
  - `local_kreck_ingest_streak` (write) — app-engagement streak (longest grows
    monotonically).
  - `local_kreck_sync_manifest` (write) — validate and provision a versioned
    SMS snapshot, returning a replay-safe receipt or explicit conflicts.
  - `local_kreck_ping` (read) — authenticated SMS-to-LMS heartbeat.
  The `local_kreck_service` web service remains disabled by default. Enable it
  and issue a least-privilege token for the sync and ping functions when
  connecting SMS.
- **Capabilities** (`db/access.php`): `local/kreck:view`, `:manage`,
  `:viewinstitution`.
- **Settings** (`settings.php`): enable toggle, default currency, attendance
  window, webhook URL and shared signing secret, and a school-level "fee status
  may gate app access" switch (off by default).
- **Privacy** (`classes/privacy/provider.php`): full metadata, export and delete
  implementation — it stores personal data, so it is GDPR-aware, not a null
  provider.

## Install

Installer label: **KRECK MicroLearn data** (`local_kreck`, release `0.2.9`).

Upload the zip via **Site administration → Plugins → Install plugins**
(type: *Local*), or drop the `kreck` folder into `‹moodleroot›/local/`, then run
**Site administration → Notifications**.

## How the theme uses it

```php
$summary = \local_kreck\api::get_learner_summary($USER->id);
// $summary['streak']['current'], $summary['fees']['balance'],
// $summary['attendance']['rate'], $summary['struggletopics'] ...
```

A dashboard block or the role dashboard template renders those values. Seeding
real data is done by the KRECK SMS sync (the `ingest_*` web services) or an
admin tool — this plugin owns the storage and the contract, not the UI.

## SMS integration setup

1. Configure LMS's `local_kreck_service` web service and a token restricted
   to `local_kreck_sync_manifest`, `local_kreck_ping`, and `local/kreck:manage`.
2. In SMS → Kreck Settings, set the HTTPS LMS base URL and token, then
   sync a school year from **Export to Kreck LMS**. The preflight defaults to
   blocking existing course shortnames. An explicit merge policy can reuse exact
   shortname matches, preserve existing course settings and matching activities,
   add missing named activities/events and add missing enrolments; it does not
   delete or edit matching LMS content.
3. Configure the matching shared webhook secret in both systems. Set LMS's
   receiver URL to
   `https://<gibbon-host>/modules/Kreck%20Bridge/kreck_webhook.php`.
4. LMS delivers signed change notifications on its scheduled-task cadence.
   SMS stores them in **Kreck LMS Classes → LMS change inbox** for review;
   webhook events do not directly mutate SMS records.
5. Use **KRECK Connection Health** under LMS's Local plugins menu and
   **Export to Kreck LMS** in SMS to see each server's latest connection
   heartbeat.

The v1 manifest contract and the v1 webhook event shape are documented in
`../../../integration/manifest-v1.schema.json` and
`../../../integration/webhook-event-v1.schema.json`. Strict conflict blocking
remains the default. Merge mode is opt-in and conservative: matching is by exact
course shortname, then section number, activity type, and activity name. Existing
course settings and matching activity details (including assignment grades and
dates) remain LMS-owned and unchanged.

SMS's whole-institution workbook supports school-year and term dates,
native timetable columns/periods/days/date mappings, rooms, and class-period
assignments. The SMS timetable itself must first be created in SMS's
Timetable Admin. Dated timetable rows are exported as LMS course calendar
events in the SMS site's timezone. Optional active rows on the LMS
Assignment Plans sheet create native LMS Assignments with opening, due, and
cutoff timestamps and the configured maximum grade. Blank assignment plans do
not alter the default course blueprint.

The separate LMS-side workbook/CSV remains a simplified direct course-seed
path; use the SMS whole-institution workbook and manifest for school
timetables and planned assignment dates.
