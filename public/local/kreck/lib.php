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
 * KRECK MicroLearn data plugin library callbacks.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Send anonymous visitors from the site front page to the public KRECK landing
 * page. Runs early (before the front page's force-login check), so it works
 * even when the site forces login — logged-in users are never redirected.
 *
 * @return void
 */
function local_kreck_after_config(): void {
    global $CFG, $USER;

    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || (defined('WS_SERVER') && WS_SERVER)) {
        return;
    }
    if (during_initial_install()) {
        return;
    }
    if (!get_config('local_kreck', 'landingenabled')) {
        return;
    }
    // Only anonymous visitors; logged-in users keep their normal front page.
    if (isloggedin() && !isguestuser()) {
        return;
    }

    $me = qualified_me();
    if (!$me) {
        return;
    }
    $path = strtok($me, '?');
    $isfrontpage = in_array($path, [
        $CFG->wwwroot,
        $CFG->wwwroot . '/',
        $CFG->wwwroot . '/index.php',
    ], true);
    if (!$isfrontpage) {
        return;
    }
    // Never get in the way of an upgrade.
    if (moodle_needs_upgrading()) {
        return;
    }

    redirect(new moodle_url('/local/kreck/welcome.php'));
}

/**
 * Return the changelog text for a given plugin version, or the latest changelog
 * if no version is supplied. Used to surface "what changed" awareness after an
 * upgrade. Returns '' when there is no changelog entry.
 *
 * @param int|null $version Plugin version to look up (null = latest).
 * @return string HTML-formatted changelog excerpt (may be empty).
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function local_kreck_get_changelog(?int $version = null): string {
    global $CFG;

    $changelogfile = $CFG->dirroot . '/local/kreck/CHANGELOG.md';
    if (!is_file($changelogfile)) {
        return '';
    }

    $content = file_get_contents($changelogfile);
    if ($content === false) {
        return '';
    }

    // Split on version headings (## vX.Y.Z or ## YYYYMMDD).
    preg_match_all('/^##\s+(.+?)\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE);
    $headings = $matches[1];

    if (empty($headings)) {
        return '';
    }

    if ($version === null) {
        // Latest = first heading.
        $target = $headings[0][0];
        $idx = 0;
        $start = $matches[0][$idx][1] + strlen($matches[0][$idx][0]);
        $end = isset($matches[0][$idx + 1][1]) ? $matches[0][$idx + 1][1] : strlen($content);
        $excerpt = trim(substr($content, $start, $end - $start));
    } else {
        // Find the heading matching this version.
        $target = null;
        $targetidx = null;
        foreach ($headings as $i => $h) {
            if (stripos($h[0], (string) $version) !== false || stripos($h[0], 'v' . (string) $version) !== false) {
                $target = $h[0];
                $targetidx = $i;
                break;
            }
        }
        if ($target === null || $targetidx === null) {
            return '';
        }
        $start = $matches[0][$targetidx][1] + strlen($matches[0][$targetidx][0]);
        $next = $targetidx + 1;
        $end = isset($matches[0][$next][1]) ? $matches[0][$next][1] : strlen($content);
        $excerpt = trim(substr($content, $start, $end - $start));
    }

    return nl2br(htmlspecialchars($excerpt));
}

/**
 * Return the version number whose changelog the admin last acknowledged.
 * @return int
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function local_kreck_get_changelog_seen_version(): int {
    return (int) get_config('local_kreck', 'changelogseen') ?: 0;
}

/**
 * Mark a changelog version as acknowledged by the admin.
 * @param int $version
 * @return void
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function local_kreck_mark_changelog_seen(int $version): void {
    set_config('local_kreck/changelogseen', $version);
}

/**
 * Get every published course for the public landing page, with live stats.
 *
 * Returns courses in visible categories (excluding the site course), ordered
 * by sort order and name. Each course is annotated with its category, view URL,
 * formatted fullname and summary, search text, and stats from Moodle core tables:
 *   activities  - count of visible course modules
 *   lessons     - count of lesson modules
 *   discussions - count of forums
 *
 * @return array of stdClass
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function local_kreck_get_landing_courses(): array {
    global $DB;

    // Never let a stats query break the landing page — return empty on any DB error.
    try {
        $params = ['site' => SITEID];
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat,
                    c.category, cc.name AS categoryname
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE c.visible = 1 AND c.id <> :site AND cc.visible = 1
           ORDER BY c.sortorder ASC, c.fullname ASC",
            $params
        );
        if (empty($courses)) {
            return [];
        }

        // Aggregate visible activity counts across the same published courses.
        $stats = [];
        $sql = "SELECT cm.course AS id,
                           COUNT(*) AS activities,
                           SUM(CASE WHEN m.name = 'lesson' THEN 1 ELSE 0 END) AS lessons,
                           SUM(CASE WHEN m.name = 'forum' THEN 1 ELSE 0 END) AS discussions
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {course} c ON c.id = cm.course
                      JOIN {course_categories} cc ON cc.id = c.category
                 LEFT JOIN {course_sections} cs ON cs.id = cm.section
                     WHERE c.visible = 1 AND c.id <> :site AND cc.visible = 1
                       AND cm.visible = 1 AND cm.deletioninprogress = 0
                       AND (cs.visible = 1 OR cs.visible IS NULL)
                     GROUP BY cm.course";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $s) {
            $stats[(int) $s->id] = (object) [
                'activities' => (int) $s->activities,
                'lessons' => (int) $s->lessons,
                'discussions' => (int) $s->discussions,
            ];
        }
        $rs->close();

        $landingcourses = [];
        foreach ($courses as $id => $course) {
            $item = new stdClass();
            $item->id = (int) $id;
            $item->categoryid = (int) $course->category;
            $item->fullname = format_string($course->fullname);
            $item->shortname = (string) $course->shortname;
            $item->monogram = core_text::strtoupper(core_text::substr(
                trim($course->shortname) !== '' ? trim($course->shortname) : $course->fullname,
                0,
                2
            ));
            $item->categoryname = format_string($course->categoryname);
            $item->url = (new moodle_url('/course/view.php', ['id' => $item->id]))->out(false);
            $summary = format_text($course->summary, $course->summaryformat, ['para' => true, 'overflowdiv' => true]);
            $summarytext = content_to_text($course->summary, $course->summaryformat);
            $item->hasdescription = trim($summarytext) !== '';
            $item->summary = $summary !== '' ? $summary : get_string('landing_card_nodesc', 'local_kreck');
            $item->searchtext = core_text::strtolower(
                $course->fullname . ' ' . $course->shortname . ' ' . $course->categoryname . ' ' . $summarytext
            );
            $item->stats = $stats[$item->id] ?? (object) ['activities' => 0, 'lessons' => 0, 'discussions' => 0];
            $landingcourses[] = $item;
        }

        return $landingcourses;
    } catch (Exception $e) {
        error_log(
            '[KRECK landing] course wall DB error: ' .
            $e->getMessage() .
            ' in ' . $e->getFile() . ':' . $e->getLine()
        );
        return [];
    }
}
