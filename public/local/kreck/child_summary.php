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
 * Parent mirror: one child's KRECK summary (academics + fee balance).
 *
 * Access is gated on local/kreck:viewchild IN THE CHILD'S USER CONTEXT, which a
 * parent holds only via the KRECK Parent role assigned in that context during
 * sync — so a parent sees only their own children, and no one else's.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
\local_kreck\diagnostics::register(__FILE__);

require_login();

global $CFG, $PAGE, $OUTPUT, $USER;

$childuserid = required_param('userid', PARAM_INT);

// The relationship check: the caller must hold the parent capability in THIS
// child's user context (or be institution staff / the learner themselves).
$childcontext = context_user::instance($childuserid);
if (!has_capability('local/kreck:viewchild', $childcontext)
        && !has_capability('local/kreck:viewinstitution', context_system::instance())
        && (int) $childuserid !== (int) $USER->id) {
    throw new moodle_exception('childsummary_notlinked', 'local_kreck');
}

$childuser = \core_user::get_user($childuserid, '*', MUST_EXIST);
$childname = fullname($childuser);

$PAGE->set_context($childcontext);
$PAGE->set_url(new moodle_url('/local/kreck/child_summary.php', ['userid' => $childuserid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('childsummary_heading', 'local_kreck'));
$PAGE->set_heading(get_string('childsummary_for', 'local_kreck', $childname));

$summary = \local_kreck\api::get_learner_summary($childuserid);

$feestatus = (int) ($summary['fees']['status'] ?? 0);
$feestatusstr = get_string('feestatus_' . $feestatus, 'local_kreck');
$rate = $summary['attendance']['rate'];
$attendancepct = $rate === null ? '—' : round((float) $rate * 100) . '%';

echo $OUTPUT->header();
?>
<style>
.kl-cs{max-width:820px;margin:1rem auto;}
.kl-cs h2{font-family:Georgia,"Times New Roman",Times,serif;color:#0f2233;margin:0 0 .2rem;}
.kl-cs .kl-meta{color:#475569;margin:0 0 1.2rem;}
.kl-cs .kl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;}
.kl-cs .kl-tile{background:#fff;border:1px solid #e2e8f0;border-top:4px solid #0e9f9a;border-radius:12px;
  padding:1rem 1.1rem;box-shadow:0 1px 3px rgba(15,30,46,.06);}
.kl-cs .kl-tile h3{margin:0 0 .5rem;font-size:.82rem;text-transform:uppercase;letter-spacing:.04em;color:#0e6b63;}
.kl-cs .kl-big{font-size:1.9rem;font-weight:700;color:#0f2233;line-height:1.1;}
.kl-cs .kl-unit{color:#64748b;font-size:.9rem;}
.kl-cs .kl-badge{display:inline-block;border-radius:999px;padding:.15rem .6rem;font-size:.8rem;font-weight:600;}
.kl-cs .kl-badge.s0{background:#dcfce7;color:#166534;} .kl-cs .kl-badge.s1{background:#fee2e2;color:#991b1b;}
.kl-cs .kl-badge.s2{background:#e0f2fe;color:#075985;} .kl-cs .kl-badge.s3{background:#fef3c7;color:#92400e;}
.kl-cs .kl-topics{list-style:none;margin:.3rem 0 0;padding:0;}
.kl-cs .kl-topics li{display:flex;justify-content:space-between;padding:.3rem 0;border-bottom:1px solid #f1f5f9;color:#334155;}
.kl-cs .kl-back{display:inline-block;margin-top:1.2rem;color:#0e6b63;text-decoration:none;font-weight:600;}
</style>
<div class="kl-cs">
  <h2><?php echo s($childname); ?></h2>
  <p class="kl-meta">
    <?php
    $bits = [];
    if (!empty($summary['student']['classname'])) { $bits[] = s($summary['student']['classname']); }
    if (!empty($summary['student']['studentid'])) { $bits[] = s($summary['student']['studentid']); }
    echo implode(' · ', $bits);
    ?>
  </p>

  <div class="kl-grid">
    <div class="kl-tile">
      <h3><?php echo s(get_string('childsummary_attendance', 'local_kreck', (int) $summary['attendance']['windowdays'])); ?></h3>
      <div class="kl-big"><?php echo s($attendancepct); ?></div>
      <div class="kl-unit"><?php echo (int) $summary['attendance']['present']; ?> / <?php echo (int) $summary['attendance']['sessions']; ?> <?php echo s(get_string('childsummary_present', 'local_kreck')); ?></div>
    </div>

    <div class="kl-tile">
      <h3><?php echo s(get_string('childsummary_fees', 'local_kreck')); ?></h3>
      <?php if (empty($summary['fees']['hasaccount'])) { ?>
        <div class="kl-unit"><?php echo s(get_string('childsummary_nofees', 'local_kreck')); ?></div>
      <?php } else { ?>
        <div class="kl-big"><?php echo s($summary['fees']['currency'] . ' ' . number_format((float) $summary['fees']['balance'], 2)); ?></div>
        <div class="kl-unit"><?php echo s(get_string('childsummary_feestatus', 'local_kreck')); ?>:
          <span class="kl-badge s<?php echo $feestatus; ?>"><?php echo s($feestatusstr); ?></span>
        </div>
      <?php } ?>
    </div>

    <div class="kl-tile">
      <h3><?php echo s(get_string('childsummary_streak', 'local_kreck')); ?></h3>
      <div class="kl-big"><?php echo (int) ($summary['streak']['current'] ?? 0); ?></div>
      <div class="kl-unit"><?php echo s(get_string('childsummary_streak', 'local_kreck')); ?> · <?php echo (int) ($summary['streak']['longest'] ?? 0); ?> best</div>
    </div>
  </div>

  <?php if (!empty($summary['struggletopics'])) { ?>
  <div class="kl-tile" style="margin-top:1rem;">
    <h3><?php echo s(get_string('childsummary_topics', 'local_kreck')); ?></h3>
    <ul class="kl-topics">
      <?php foreach ($summary['struggletopics'] as $t) { ?>
      <li><span><?php echo s($t['topic']); ?></span><span><?php echo round((float) $t['rate'] * 100); ?>%</span></li>
      <?php } ?>
    </ul>
  </div>
  <?php } ?>

  <a class="kl-back" href="<?php echo (new moodle_url('/local/kreck/children.php'))->out(false); ?>">&larr; <?php echo s(get_string('childsummary_back', 'local_kreck')); ?></a>
</div>
<?php
echo $OUTPUT->footer();
