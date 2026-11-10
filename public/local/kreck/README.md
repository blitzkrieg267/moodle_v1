# local_kreck — KRECK MicroLearn data backbone

The data layer for KRECK MicroLearn. It holds the **Layer-1 (KRECK SMS)**
information core Moodle does not — the lifelong student record, attendance,
fee accounts, learning streaks and topic-struggle analytics — and exposes a
clean API and web services so the school system, the mobile app and the
`theme_microlearn` dashboards all read and write **one shared student record**.

## What it provides

- **Database tables** (`db/install.xml`): `local_kreck_student`, `_attendance`,
  `_feeaccount`, `_streak`, `_topicstat`.
- **Persistent classes** (`classes/persistent/`): typed, validated CRUD on each
  table via `core\persistent`.
- **API facade** (`local_kreck\api`): `get_learner_summary($userid)` returns a
  stable shape — identity, attendance rate, fee balance, streak and the
  learner's hardest topics — degrading gracefully to zeros when there's no data
  yet. This is the one contract the theme and blocks consume; they never touch
  the database directly.
- **Web services** (`db/services.php`, `classes/external/`):
  - `local_kreck_get_learner_summary` (read) — for the app/dashboards.
  - `local_kreck_ingest_attendance` (write) — for the KRECK register sync.
  Both are attached to the `local_kreck_service` web service (disabled by
  default; enable it and issue a token under *Site admin → Server → Web
  services* when you're ready to connect SMS).
- **Capabilities** (`db/access.php`): `local/kreck:view`, `:manage`,
  `:viewinstitution`.
- **Settings** (`settings.php`): enable toggle, default currency, attendance
  window, and a school-level "fee status may gate app access" switch (off by
  default).
- **Privacy** (`classes/privacy/provider.php`): full metadata, export and delete
  implementation — it stores personal data, so it is GDPR-aware, not a null
  provider.

## Install

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

## Not included yet (next steps)

- `ingest_fees` / `ingest_streak` web services (mirror of `ingest_attendance`).
- A `block_kreck_streak` / `block_kreck_fees` dashboard card that renders the
  summary.
- An admin page to browse/seed records for testing.
- Scheduled task to roll attendance into streaks.
