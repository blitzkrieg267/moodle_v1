# block_kreck_streak — KRECK learner snapshot

A dashboard block that shows a learner their **streak**, **attendance**,
**fee status** and **hardest topic** at a glance — the visible payoff that ties
`theme_microlearn` (the look) to `local_kreck` (the data).

## How it works

- `block_kreck_streak::get_content()` calls
  `\local_kreck\api::get_learner_summary($USER->id)` — it never touches the
  database directly.
- It flattens that summary into a template context and renders
  `templates/card.mustache`.
- `styles.css` uses `theme_microlearn`'s brand custom properties
  (`--kreck-*`, `--mds-border-radius-*`) when present, with KRECK fallbacks so
  the card still looks right under stock Boost.

## Behaviour

- Shows only to logged-in, non-guest users.
- If `local_kreck` is not installed, it shows a quiet info notice instead of
  erroring (and `version.php` declares the dependency so Moodle won't let you
  install the block without it).
- A learner with no KRECK data yet sees a zeroed-but-valid card (the API
  degrades gracefully).

## Install & use

1. Install `local_kreck` first.
2. Upload this zip via **Site admin → Plugins → Install plugins** (type:
   *Block*), then run **Notifications**.
3. On the **Dashboard** (`/my`), turn on **Edit mode** → **Add a block** →
   **KRECK learning streak**.

## Not included yet

- Per-instance config (e.g. choose which stats to show).
- A sibling `block_kreck_fees` for the parent view.
- Deep-link buttons ("see full attendance", "pay fees") once those pages exist.
