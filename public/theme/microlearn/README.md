# theme_microlearn — KRECK MicroLearn

A **Boost child theme** that re-skins Moodle into the KRECK MicroLearn
platform: a mobile-first, dual-layer product (KRECK SMS + MicroLearn LMS)
for secondary schools in Botswana and South Africa.

## Design principles

- **Upgrade-safe by construction.** Nothing in core or in `theme_boost` is
  edited. The entire re-skin is additive — brand SCSS variables injected
  *before* the compile, and Moodle Design System (MDS) token overrides
  appended *after* it.
- **Retarget the design system, don't fight Bootstrap.** Moodle 6 emits
  ~1,100 `--mds-*` CSS custom properties. We re-declare the semantic ones
  (surfaces, interactive colours, borders, radii) so cards, buttons, drawers,
  forms and tables re-skin consistently from one small place.
- **Low-bandwidth first.** No web fonts are downloaded; a system/Inter stack
  is used. Icons are SVG/Font Awesome (inherited). No heavy JS is added.

## Brand palette

| Role | Value |
|------|-------|
| Primary interactive (teal) | `#1e8a74` |
| Accent / reward (amber) | `#f2a43c` |
| Structural (slate) | `#16293b` / `#0f1e2e` |
| Institutional / info (steel) | `#2f6da3` |
| Surface | `#ffffff` / `#f5f7fa` subtle |
| Ink | `#13212e` / `#5a6b7b` muted |

## File map

```
config.php            Boost child config (parents=[boost]); SCSS callbacks.
version.php           Component metadata; depends on theme_boost.
lib.php               SCSS pipeline (pre / main / extra) + pluginfile handler.
settings.php          Admin: brand colour, background image, raw SCSS.
lang/en/…             Strings.
classes/privacy/…     Null privacy provider (no personal data).
scss/_pre.scss        Brand SCSS variables (pre-Bootstrap).
scss/_tokens.scss     MDS `--mds-*` token retarget (the main re-skin surface).
scss/_typography.scss Heading rhythm / eyebrow label; no web fonts.
scss/_shell.scss      Navbar / drawers / footer surfaces.
scss/_components.scss Cards, buttons, badges, tables, forms, login.
```

## SCSS cascade order

1. **pre** (`_pre.scss`) → Bootstrap/MDS variables inherit the palette.
2. **main** → Boost's full preset (Bootstrap + MDS + Moodle).
3. **extra** → `_tokens` → `_typography` → `_shell` → `_components`, then the
   admin "raw SCSS" setting last so administrators always get the final word.

## Installation

Copy into `public/theme/microlearn/`, then visit **Site administration →
Notifications** to install, and select the theme at **Appearance → Theme
selector**. Purge caches after SCSS edits (**Development → Purge caches**).

## Roadmap (not yet in this build)

- P2: structural shell template overrides (navbar/drawers mustache).
- P3: role-differentiated dashboards (Learner / Teacher / Parent / Institution)
  via a `mydashboard` layout + renderables.
- P4: course-grid, quiz and gradebook template overrides.
- P5: KRECK SMS (Layer 1) data wiring via a companion `local_kreck` plugin —
  fees, attendance, debit orders and topic-struggle analytics.
