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
 * Public KRECK landing page: a read-only shopfront for the institution.
 *
 * Deliberately does not require login, so anonymous visitors can see the
 * institution, its categories and the courses offered before signing in.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

global $DB, $OUTPUT, $PAGE, $SITE, $USER;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/kreck/welcome.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($SITE->fullname));
$PAGE->set_heading('');

// Public, read-only counts.
$catcount = $DB->count_records('course_categories', ['visible' => 1]);
$coursecount = $DB->count_records_select('course', 'visible = 1 AND id <> :site', ['site' => SITEID]);
$activitycount = $DB->count_records_select('course_modules', 'visible = 1 AND deletioninprogress = 0');
$learnercount = (int) $DB->count_records_sql(
    "SELECT COUNT(DISTINCT ra.userid)
       FROM {role_assignments} ra
       JOIN {role} r ON r.id = ra.roleid
      WHERE r.archetype = :a", ['a' => 'student']);

// Visible categories that actually hold visible courses.
$cats = $DB->get_records('course_categories', ['visible' => 1], 'sortorder ASC', 'id, name');
$catblocks = [];
foreach ($cats as $cat) {
    $courses = $DB->get_records_select('course',
        'category = :c AND visible = 1 AND id <> :site',
        ['c' => $cat->id, 'site' => SITEID], 'fullname ASC', 'id, fullname');
    if ($courses) {
        $catblocks[] = (object) [
            'name' => format_string($cat->name),
            'courses' => array_map(static fn($c) => format_string($c->fullname), $courses),
        ];
    }
}

$intro = (string) get_config('local_kreck', 'landingintro');
if ($intro === '') {
    $intro = $SITE->summary ? content_to_text($SITE->summary, FORMAT_HTML)
        : get_string('landing_defaultintro', 'local_kreck');
}

$tiles = [
    ['v' => $coursecount, 'l' => get_string('landing_stat_subjects', 'local_kreck')],
    ['v' => $catcount, 'l' => get_string('landing_stat_categories', 'local_kreck')],
    ['v' => $activitycount, 'l' => get_string('landing_stat_activities', 'local_kreck')],
    ['v' => $learnercount, 'l' => get_string('landing_stat_learners', 'local_kreck')],
];

echo $OUTPUT->header();
?>
<style>
.kreck-landing { --kl-slate:#0f1e2e; --kl-teal:#1e8a74; --kl-amber:#f2a43c; --kl-ink:#13212e; --kl-muted:#5a6b7b; }
.kreck-landing .kl-hero {
    color:#fff; border-radius:var(--mds-border-radius-xl,20px); padding:clamp(1.6rem,4vw,2.8rem);
    background:radial-gradient(900px 420px at 12% -20%,rgba(30,138,116,.30),transparent 60%),
               radial-gradient(700px 360px at 95% 0,rgba(242,164,60,.14),transparent 55%),var(--kreck-slate-900,var(--kl-slate));
}
.kreck-landing .kl-eyebrow { font-size:.74rem; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#aeb9c4; margin:0; }
.kreck-landing .kl-title { font-size:clamp(1.8rem,5vw,3rem); font-weight:800; letter-spacing:-.02em; color:#fff; margin:.5rem 0 0; }
.kreck-landing .kl-lede { max-width:60ch; color:#c3cdd6; margin:.9rem 0 0; font-size:clamp(1rem,2.2vw,1.12rem); }
.kreck-landing .kl-cta { margin-top:1.4rem; }
.kreck-landing .kl-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin:1.4rem 0; }
@media (max-width:720px){ .kreck-landing .kl-stats{ grid-template-columns:repeat(2,1fr);} }
.kreck-landing .kl-tile { background:var(--kreck-surface,#fff); border:1px solid rgba(15,30,46,.08);
    border-radius:var(--mds-border-radius-lg,16px); padding:1rem 1.15rem; box-shadow:0 1px 2px rgba(15,30,46,.04),0 6px 18px rgba(15,30,46,.05); }
.kreck-landing .kl-tile-v { font-size:2rem; font-weight:800; letter-spacing:-.02em; color:var(--kreck-ink,var(--kl-ink)); }
.kreck-landing .kl-tile-l { font-size:.78rem; letter-spacing:.04em; text-transform:uppercase; color:var(--kreck-ink-muted,var(--kl-muted)); }
.kreck-landing .kl-h2 { font-size:clamp(1.4rem,3.2vw,1.9rem); font-weight:700; margin:1.6rem 0 1rem; }
.kreck-landing .kl-cats { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
.kreck-landing .kl-cat { background:var(--kreck-surface,#fff); border:1px solid rgba(15,30,46,.08);
    border-radius:var(--mds-border-radius-lg,16px); padding:1.1rem 1.2rem; box-shadow:0 1px 2px rgba(15,30,46,.04),0 6px 18px rgba(15,30,46,.05); }
.kreck-landing .kl-cat-name { font-size:1.05rem; font-weight:700; margin:0 0 .6rem; color:var(--kreck-ink,var(--kl-ink)); }
.kreck-landing .kl-courselist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.4rem; }
.kreck-landing .kl-courselist li { padding-left:1.1rem; position:relative; color:var(--kreck-ink,var(--kl-ink)); }
.kreck-landing .kl-courselist li::before { content:""; position:absolute; left:0; top:.55em; width:7px; height:7px; border-radius:50%; background:var(--kreck-teal,var(--kl-teal)); }
[data-bs-theme="dark"] .kreck-landing .kl-tile,[data-bs-theme="dark"] .kreck-landing .kl-cat { background:#13222f; border-color:rgba(255,255,255,.08); }
[data-bs-theme="dark"] .kreck-landing .kl-tile-v,[data-bs-theme="dark"] .kreck-landing .kl-cat-name,[data-bs-theme="dark"] .kreck-landing .kl-courselist li { color:#e7edf3; }
</style>

<div class="kreck-landing">
    <div class="kl-hero">
        <p class="kl-eyebrow"><?php echo get_string('landing_eyebrow', 'local_kreck'); ?></p>
        <h1 class="kl-title"><?php echo format_string($SITE->fullname); ?></h1>
        <p class="kl-lede"><?php echo s($intro); ?></p>
        <?php if (!isloggedin() || isguestuser()) { ?>
            <a class="btn btn-primary kl-cta" href="<?php echo (new moodle_url('/login/index.php'))->out(); ?>">
                <?php echo get_string('login'); ?>
            </a>
        <?php } ?>
    </div>

    <div class="kl-stats">
        <?php foreach ($tiles as $t) { ?>
            <div class="kl-tile">
                <div class="kl-tile-v"><?php echo (int) $t['v']; ?></div>
                <div class="kl-tile-l"><?php echo $t['l']; ?></div>
            </div>
        <?php } ?>
    </div>

    <h2 class="kl-h2"><?php echo get_string('landing_courses', 'local_kreck'); ?></h2>
    <?php if (empty($catblocks)) { ?>
        <p class="text-muted"><?php echo get_string('landing_nocourses', 'local_kreck'); ?></p>
    <?php } else { ?>
        <div class="kl-cats">
            <?php foreach ($catblocks as $b) { ?>
                <div class="kl-cat">
                    <h3 class="kl-cat-name"><?php echo $b->name; ?></h3>
                    <ul class="kl-courselist">
                        <?php foreach ($b->courses as $cn) { echo html_writer::tag('li', s($cn)); } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>
<?php
echo $OUTPUT->footer();
