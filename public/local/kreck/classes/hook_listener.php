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
     * Keep light dropdown surfaces readable when the active theme supplies
     * light menus but inherits white link colors from the dark navbar.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function add_theme_contrast_styles(
        \core\hook\output\before_standard_top_of_body_html_generation $hook
    ): void {
        $hook->add_html(<<<'HTML'
<style id="local-kreck-theme-contrast">
.dropdown-menu,
#usernavigation .dropdown-menu,
.navbar .dropdown-menu {
    --bs-dropdown-color: #10150d;
    --bs-dropdown-bg: #ffffff;
    --bs-dropdown-link-color: #10150d;
    --bs-dropdown-link-hover-color: #10150d;
    --bs-dropdown-link-hover-bg: #edf1e6;
    --bs-dropdown-link-active-color: #ffffff;
    --bs-dropdown-link-active-bg: #42571d;
    --bs-dropdown-link-disabled-color: #667783;
    --bs-dropdown-header-color: #536574;
    color: #10150d !important;
    background-color: #ffffff !important;
    border-color: #d5dfe6 !important;
}
.dropdown-menu .dropdown-item,
.dropdown-menu a:not(.btn) {
    color: #10150d !important;
}
.dropdown-menu .dropdown-item:hover,
.dropdown-menu .dropdown-item:focus,
.dropdown-menu a:not(.btn):hover,
.dropdown-menu a:not(.btn):focus {
    color: #10150d !important;
    background-color: #edf1e6 !important;
}
.dropdown-menu .dropdown-item.active,
.dropdown-menu .dropdown-item:active {
    color: #ffffff !important;
    background-color: #42571d !important;
}
.dropdown-menu .dropdown-item.disabled,
.dropdown-menu .dropdown-item:disabled {
    color: #667783 !important;
}
.dropdown-menu .dropdown-header {
    color: #536574 !important;
}
.dropdown-menu .dropdown-divider {
    border-top-color: #d5dfe6;
    opacity: 1;
}
[data-bs-theme="dark"] .dropdown-menu,
[data-bs-theme="dark"] #usernavigation .dropdown-menu,
[data-bs-theme="dark"] .navbar .dropdown-menu {
    --bs-dropdown-color: #eef1e8;
    --bs-dropdown-bg: #10150d;
    --bs-dropdown-link-color: #eef1e8;
    --bs-dropdown-link-hover-color: #ffffff;
    --bs-dropdown-link-hover-bg: #1c3e4f;
    --bs-dropdown-link-active-color: #ffffff;
    --bs-dropdown-link-active-bg: #42571d;
    --bs-dropdown-header-color: #b8c5ce;
    color: #eef1e8 !important;
    background-color: #10150d !important;
    border-color: #365166 !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-item,
[data-bs-theme="dark"] .dropdown-menu a:not(.btn) {
    color: #eef1e8 !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-menu .dropdown-item:focus,
[data-bs-theme="dark"] .dropdown-menu a:not(.btn):hover,
[data-bs-theme="dark"] .dropdown-menu a:not(.btn):focus {
    color: #ffffff !important;
    background-color: #1c3e4f !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-header {
    color: #b8c5ce !important;
}
[data-bs-theme="dark"] .dropdown-menu .dropdown-divider {
    border-top-color: #365166;
}
/* KRECK Overstory accent on Moodle core controls (buttons, links) so the LMS
   chrome matches the KRECK SMS: leaf-green primary, lime/gold accents. */
.btn-primary {
    --bs-btn-bg: #52701f; --bs-btn-border-color: #52701f;
    --bs-btn-hover-bg: #42571d; --bs-btn-hover-border-color: #42571d;
    --bs-btn-active-bg: #3c5618; --bs-btn-active-border-color: #3c5618;
    --bs-btn-disabled-bg: #6f8a3e; --bs-btn-disabled-border-color: #6f8a3e;
}
.btn-success {
    --bs-btn-bg: #52701f; --bs-btn-border-color: #52701f;
    --bs-btn-hover-bg: #42571d; --bs-btn-hover-border-color: #42571d;
}
.btn-outline-primary {
    --bs-btn-color: #42571d; --bs-btn-border-color: #52701f;
    --bs-btn-hover-bg: #52701f; --bs-btn-hover-border-color: #52701f;
}
a { color: #42571d; }
a:hover { color: #3c5618; }
.text-primary { color: #42571d !important; }
.bg-primary { background-color: #52701f !important; }
</style>
HTML
        );
    }

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
            . 'style="display:inline-block;background:#52701f;color:#fff;text-decoration:none;'
            . 'border-radius:6px;padding:5px 12px;font-weight:600;font-size:13px;">'
            . $label . ' &#8599;</a></div>';
        $hook->add_html($html);
    }

    /**
     * Point the navbar logo/brand at the KRECK welcome page.
     *
     * The brand is a plain static anchor whose href is the site "home" URL —
     * which, when the site's default home page is the Dashboard, is /my/, not
     * the KRECK landing page. Rewrite it to welcome.php. (The primary navigation
     * is a React component, so the "Home" item is added server-side in
     * extend_primary_navigation(); only the static brand is handled here.)
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function retarget_logo(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        if (!get_config('local_kreck', 'landingenabled')) {
            return;
        }
        $url = (new \moodle_url('/local/kreck/welcome.php'))->out(false);
        $js = '<script>(function(){var w=' . json_encode($url) . ';'
            . 'function r(){var b=document.querySelector("a.navbar-brand");if(b&&b.getAttribute("href")!==w){b.setAttribute("href",w);}}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",r);}else{r();}})();</script>';
        $hook->add_html($js);
    }

    /**
     * Add a "Home" item to the primary navigation that opens the KRECK welcome
     * page, placed before the Dashboard node. Runs server-side so it is part of
     * the (React-rendered) primary-nav data and survives client re-render.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        if (!get_config('local_kreck', 'landingenabled')) {
            return;
        }
        $primary = $hook->get_primaryview();
        if ($primary->get('kreckhome', \navigation_node::TYPE_CUSTOM)) {
            return;
        }
        $node = \navigation_node::create(
            get_string('home'),
            new \moodle_url('/local/kreck/welcome.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'kreckhome',
            new \pix_icon('i/home', '')
        );
        $node->showinflatnavigation = true;
        // Insert before Dashboard ('myhome'); append if that node is absent.
        $primary->add_node($node, 'myhome');
    }
}
