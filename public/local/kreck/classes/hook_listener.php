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
 * KRECK hook listeners.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck;

/**
 * Injects "View in KRECK SMS" deep-links on course and user-profile pages.
 */
class hook_listener {

    /**
     * Render a KRECK SMS deep-link near the top of the body on course /
     * user-profile pages, when a stored link exists and a base URL is set.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function add_sms_link(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $DB;

        $base = trim((string) get_config('local_kreck', 'gibbonbaseurl'));
        if ($base === '') {
            return;
        }
        $context = $PAGE->context ?? null;
        if (!$context) {
            return;
        }

        $link = null;
        if ($context instanceof \context_course && (int) $context->instanceid !== (int) SITEID) {
            $shortname = $DB->get_field('course', 'shortname', ['id' => $context->instanceid]);
            if ($shortname) {
                $link = $DB->get_record('local_kreck_link',
                    ['entitytype' => 'course', 'entitykey' => $shortname, 'direction' => 'togibbon']);
            }
        } else {
            $path = $PAGE->url ? $PAGE->url->out_omit_querystring() : '';
            if (strpos($path, '/user/profile.php') !== false || strpos($path, '/user/view.php') !== false) {
                $userid = optional_param('id', 0, PARAM_INT);
                if ($userid) {
                    $studentid = $DB->get_field('local_kreck_student', 'studentid', ['userid' => $userid]);
                    if ($studentid) {
                        $link = $DB->get_record('local_kreck_link',
                            ['entitytype' => 'user', 'entitykey' => $studentid, 'direction' => 'togibbon']);
                    }
                }
            }
        }
        if (!$link) {
            return;
        }

        $href = s(rtrim($base, '/') . $link->relurl);
        $label = s(get_string('viewinsms', 'local_kreck'));
        $html = '<div class="local-kreck-smslink" style="text-align:right;margin:4px 0;padding:0 16px;">'
            . '<a href="' . $href . '" target="_blank" rel="noopener" '
            . 'style="display:inline-block;background:#0e9f9a;color:#fff;text-decoration:none;'
            . 'border-radius:6px;padding:5px 12px;font-weight:600;font-size:13px;">'
            . $label . ' &#8599;</a></div>';
        $hook->add_html($html);
    }
}
