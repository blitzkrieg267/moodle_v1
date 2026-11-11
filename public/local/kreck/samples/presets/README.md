# KRECK exam-body starter templates

Ready-to-load onboarding manifests, one per exam body. Each holds that body's
standard subject set (with syllabus codes where they exist) under an academic-
year category, using the default weekly course blueprint. They carry **no**
teachers, cohorts or learners — load one as a default during requirements
gathering, then add the institution's specifics.

| File | Exam body | Courses |
|------|-----------|---------|
| `igcse.json`  | Cambridge IGCSE | 14 |
| `alevel.json` | Cambridge International A Level | 13 |
| `olevel.json` | Cambridge O Level | 11 |
| `bgcse.json`  | BGCSE (Botswana, BEC) | 16 |
| `psle.json`   | PSLE (Botswana, Standard 7) | 8 |

## How to use

In **Site admin → Plugins → Local plugins → KRECK onboarding & provisioning**,
use the **Starter templates by exam body** links to download one, paste it into
the manifest box, **Dry run**, then **Provision**. Roll back any time by batch id.

## Notes

- `numsections` defaults to **6** (a light starter scaffold). Increase it to
  your term or year length before a full rollout.
- Cambridge bodies use real syllabus codes in the course name and short name
  (e.g. `IGCSE-0580`). BGCSE and PSLE use the national subject names with a
  short code slug (e.g. `BGCSE-MATH`), since those bodies assign their own
  centre codes.
- Short names are unique per body, so a school offering several bodies can load
  more than one template without collisions.
- Edit the category path (the academic year) and course details to match the
  institution.
