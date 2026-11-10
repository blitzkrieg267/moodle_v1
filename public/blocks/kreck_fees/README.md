# block_kreck_fees — KRECK fee status

A parent-facing dashboard card showing a learner's **fee balance and status**,
with a reminder when a payment is due. Reads the KRECK record through
`local_kreck`'s API facade; stores no data itself.

- The hero colour follows status — teal when up to date, amber when overdue,
  red on a failed mandate.
- Shows attendance and the student ID for context.
- If `local_kreck` is absent it shows a quiet notice; a learner with no fee
  account yet sees a clear empty state.

Same build/install pattern as `block_kreck_streak`. Install `local_kreck`
first, then add **KRECK fees** on the Dashboard via **Edit mode → Add a block**.

Future: scope to a parent's child via the mentor role (pass the child's user id
to `api::get_learner_summary()`), and add a statement date / pay link once those
pages exist.
