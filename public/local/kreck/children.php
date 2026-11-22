<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Parent mirror: a parent/guardian's list of linked children.
 *
 * Shows the learners linked to the current user (via local_kreck_parentlink),
 * each linking to that child's KRECK summary. Linking itself is managed in the
 * SMS and mirrored during sync; this page is read-only.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
\local_kreck\diagnostics::register(__FILE__);

require_login();

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kreck/children.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mychildren_heading', 'local_kreck'));
$PAGE->set_heading(get_string('mychildren_heading', 'local_kreck'));

// The children linked to this parent, with the child's name. Ordered by the
// mirrored contact priority then name.
$sql = "SELECT pl.id, pl.childuserid, pl.contactpriority, u.*
          FROM {local_kreck_parentlink} pl
          JOIN {user} u ON u.id = pl.childuserid AND u.deleted = 0
         WHERE pl.parentuserid = :uid
      ORDER BY pl.contactpriority ASC, u.lastname ASC, u.firstname ASC";
$children = $DB->get_records_sql($sql, ['uid' => (int) $USER->id]);

echo $OUTPUT->header();
?>
<style>
.kl-children{max-width:760px;margin:1rem auto;}
.kl-children h2{font-family:Georgia,"Times New Roman",Times,serif;color:#0f2233;margin:0 0 .3rem;}
.kl-children .kl-sub{color:#475569;margin:0 0 1.2rem;}
.kl-child-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;
  background:#fff;border:1px solid #e2e8f0;border-left:4px solid #0e9f9a;border-radius:12px;
  padding:.9rem 1.1rem;margin:.6rem 0;box-shadow:0 1px 3px rgba(15,30,46,.06);}
.kl-child-card .kl-name{font-weight:600;color:#0f2233;font-size:1.05rem;}
.kl-child-card a.kl-view{display:inline-block;background:#0e9f9a;color:#fff;text-decoration:none;
  border-radius:8px;padding:.5rem .9rem;font-weight:600;white-space:nowrap;}
.kl-children .kl-empty{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;
  padding:1.2rem;color:#475569;}
</style>
<div class="kl-children">
  <h2><?php echo s(get_string('mychildren_heading', 'local_kreck')); ?></h2>
  <p class="kl-sub"><?php echo s(get_string('mychildren_intro', 'local_kreck')); ?></p>
<?php if (empty($children)) { ?>
  <div class="kl-empty"><?php echo s(get_string('mychildren_none', 'local_kreck')); ?></div>
<?php } else {
    foreach ($children as $child) {
        $url = new moodle_url('/local/kreck/child_summary.php', ['userid' => (int) $child->childuserid]);
        ?>
  <div class="kl-child-card">
    <span class="kl-name"><?php echo s(fullname($child)); ?></span>
    <a class="kl-view" href="<?php echo $url->out(false); ?>"><?php echo s(get_string('mychildren_view', 'local_kreck')); ?></a>
  </div>
<?php }
} ?>
</div>
<?php
echo $OUTPUT->footer();
