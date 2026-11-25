# KRECK MicroLearn (local_kreck) Changelog

## 2026112207 — SMS events mirrored 1:1 into the LMS calendar; searchable fields; BWP default

- New web service `local_kreck_sync_events` (needs `local/kreck:manage`): the SMS pushes a batch of
  events and the LMS creates, updates or removes the equivalent calendar events, idempotently
  (stable `uuid = kreck:<source_id>[@c:<course>|@u:<username>]`, `component = local_kreck`, so the
  calendar UI cannot edit or delete them). Audience maps onto Moodle's own model: site events (whole
  school), course events (a class or every class of a year group) and personal events (a named list of
  people, used for teacher-only lessons, parent copies, activities, duty and cover). Supports a dry run,
  adopts events the older timetable export created (same class and time) instead of duplicating them,
  anchors all-day events in the site timezone, skips (and reports) audiences that do not exist yet.
- Seed form, onboarding form and the "Default currency" setting now use searchable `autocomplete`
  fields (users, classes, topics, currencies, import batches). BWP is the default and first in every
  currency list; a blank currency from the SMS is stored as BWP; an unknown currency is rejected.
- Branded KRECK sign-in page is on by default after an upgrade (2026112206).
- Schema: none. Upgrade step only refreshes the web-service descriptions.

## 2026111810 / v0.3.0 — Reverse SMS→LMS link import + SMS/LMS de-brand

- Add "Import SMS links into KRECK LMS" (import_links.php): upload the SMS
  deep-links file exported from the SMS side and store the relative SMS URLs in
  local_kreck_link (direction 'togibbon'). Mirrors the existing LMS→SMS link
  export; idempotent on (entitytype, entitykey, direction) — a re-run updates in
  place and never duplicates, with per-row created/updated/skipped results.
- New engine::import_links() does the idempotent upsert (reuses the same
  matching/dedup logic as onboarding store_links); registered as the
  local_kreck_importlinks admin page with its own capability gate.
- De-brand user-facing text: say "SMS" / "LMS" instead of "Gibbon" / "Moodle"
  across language strings, labels, help and prose. Structural names
  (local_kreck, local_kreck_link, web-service/capability names, config keys,
  moodle_url and other core APIs, GPL headers) are unchanged.
- No schema change; local_kreck_link already exists.

## 2026111800 / v0.2.9 — Welcome-wall diagonal pan fix + trackpad support

- Fix the public welcome course-wall "glass scroll": the pointer/swipe release
  now requires one axis to clearly dominate (1.35x ratio dead-zone), so
  ambiguous ~45-degree diagonal gestures are ignored instead of firing an
  arbitrary single-axis pan.
- Lock out a new pan while one is in flight (early bail on state.animating), so
  a flick can no longer double-fire or stick mid-animation.
- Add a debounced trackpad/wheel path for horizontal two-finger swipes
  (left/right pan) that never traps vertical page scrolling, with the same
  dead-zone and a short cooldown. prefers-reduced-motion continues to be
  respected (no pan when reduced motion is on).

## 2026111503 / v0.2.8 — Bidirectional connection diagnostics

- Show the active LMS database name and table prefix, the local SMS
  webhook destination, and whether the secret setting row is missing, empty,
  or set (secret value remains redacted).
- Retain step-by-step heartbeat diagnostics including request ID, HTTPS
  settings, HTTP/cURL details, timings, response status, and safe message.
- Redact the shared secret and HMAC signature from response and exception text.

## 2026111502 / v0.2.7 — Exact webhook failure explanations

- Map SMS's known 503, 401, 404, and invalid-payload responses to the exact
  missing setting, signature, timestamp, route, or contract issue.
- Removed generic URL/secret advice from the specific 503 authentication
  configuration failure.

## 2026111501 / v0.2.6 — Fix connection-check initialization

- Run the connection configuration and heartbeat checker in the same page
  initialization phase. Previously, the checker could run first, see no config,
  and exit while the page remained at its initial status.

## 2026111500 / v0.2.5 — Heartbeat diagnostics and timeout feedback

- Added visible elapsed-time and waiting status while LMS sends its
  LMS-to-SMS heartbeat, with a browser-side 25-second deadline.
- Report HTTP status, cURL error code/message, and a bounded SMS response
  excerpt on failure; non-JSON LMS errors and internal exceptions now show
  actionable diagnostics instead of leaving a generic checking state.
- Changed the initial display so it does not remain "checking" if JavaScript
  fails to run.

## 2026111403 / v0.2.4 — Bidirectional connection health

- Added a restricted external heartbeat function so SMS can verify its
  authenticated connection to LMS.
- Added a LMS administrator connection-health page that sends a signed,
  read-only heartbeat to SMS and shows the latest result.
- Exposed the last successful SMS heartbeat time in LMS; both pages
  refresh their checks while open.

## 2026111402 / v0.2.3 — Safe merge into existing courses

- Added an explicit manifest policy to merge into LMS courses with exact
  shortname matches instead of blocking the entire sync.
- Merge preserves existing course settings and matching activities, adds
  missing named activities and timetable events, and adds only missing direct
  enrolments. It never deletes existing content.
- Existing activities with matching section, type, and name are left untouched,
  including assignment grades and due dates.
- Merge-created activities, events, and enrolments are tracked for batch
  rollback; strict conflict blocking remains the default.

## 2026111401 / v0.2.2 — School-wide administrator calendar

- Managers and users with institution-wide KRECK access now see calendar events
  from all visible LMS courses on the KRECK landing dashboard and its
  dedicated full calendar page.
- Teacher and learner calendars keep their enrolled-course scope; other users'
  private calendar events are not exposed.

## 2026111400 / v0.2.1 — KRECK page diagnostics

- Added request-scoped error references and structured server-log context to
  all `local_kreck` web pages.
- Uncaught LMS exceptions retain LMS's handler and include the reference
  in developer details; handled onboarding failures display the reference.

## 2026111320 / v0.2.0 — SMS timetable and assignment schedules

- Added source-timezone-aware SMS school-year start/end dates and native
  LMS course calendar events for imported SMS timetable occurrences.
- Added optional SMS assignment plans that provision native LMS
  Assignments with validated opening, due, cutoff and maximum-grade values.
- Kept the weekly course blueprint unchanged when no assignment plans exist.

## 2026111309 / v0.1.9 — SMS sync and change-event inbox

- Added the versioned SMS manifest web service with stable IDs, preflight
  checks, serialized imports, conflict results, and idempotent retry receipts.
- Added LMS course, user, activity, enrolment and grade change notifications
  to a durable, signed webhook outbox with retry backoff.
- Suppressed outbound notifications while importing a SMS-owned snapshot to
  prevent sync loops; existing LMS data is not overwritten on conflict.

## 2026111307 / v0.1.7 — Learner timetable and enrolled courses

- Logged-in learners now see LMS's built-in monthly calendar and upcoming
  event list in the hero, alongside their enrolled courses below.
- Kept the animated public landing page and public course catalog for logged-out
  visitors; learner statistics now reflect only the learner's courses.

## 2026111306 / v0.1.6 — Full-width landing page and contrast

- Made the welcome page's content area use the available viewport width with
  responsive gutters, while leaving other LMS pages unchanged.
- Added high-contrast dropdown colors for light and dark LMS surfaces to
  prevent the Kreck navbar's white link styling from making menu text unreadable.

## 2026111305 / v0.1.5 — Animated course wall

- Replaced the static course grid with a responsive animated glass wall while
  retaining course search, real summaries, course statistics, and site counters.
- Rendered at most six course cards at once and added one-course directional
  navigation with wraparound, timed panning, mouse/touch gestures, and keyboard
  controls.

## 2026111304 / v0.1.4 — Glass course wall

- Replaced the featured cards, quick links, separate counters, pathway, and
  subject explorer with one responsive, searchable glass course wall.
- Shows every published course in a visible category with its real LMS
  summary and live activities, lessons, and discussions counts.
- Moved the site counters into the glass wall and made course, category, and
  activity totals correspond to the displayed cards.
- Added subtle card entrance motion with a reduced-motion preference.

## 2026111303 / v0.1.3 — Course card UX and accessibility

- Made the course description toggle a working, keyboard-accessible control
  separate from the course navigation link.
- Replaced arbitrary course-number badges with course-code initials and removed
  a style collision between course and landing-page statistic tiles.
- Course card counts now omit deleted activities and content in hidden sections;
  empty course descriptions no longer show an irrelevant read-more control.
- Clarified that the cards list published courses rather than promising that
  every course is open for enrolment.

## 2026111301 / v0.1.1 — Landing page UX refresh

- New "Featured courses" section on the public landing page, showing each
  course as a frosted-glass card with its category and a number badge.
- Live course stats on every card: number of activities, lessons and
  discussions, queried in real time from LMS core tables.
- Course description with a "Read more / Read less" toggle so long summaries
  stay compact but expandable.
- "View course" link on each card to the actual LMS course page.
- KRECK theme colours throughout: teal #1e8a74, steel #2f6da3, amber #f2a43c,
  slate #0f2233, with dark-mode overrides.
- Admin controls: show/hide the section and set the number of featured cards
  (default 6, enabled by default).
- Upgrade awareness: this version is recorded during upgrade so admins can see
  what changed (see the settings page).

## 2026111108 / v0.1.0 — Initial release

- KRECK MicroLearn data backbone: student record, attendance, fees, streaks,
  topic-struggle analytics.
- Public landing page with hero, stats, pathway, subject explorer and cookie
  banner.
- Web services for SMS <-> LMS data sync.
- Onboarding, seeding and import/export tools.
