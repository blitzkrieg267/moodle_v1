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
 * KRECK MicroLearn theme callbacks.
 *
 * The SCSS is assembled in three ordered segments so the cascade behaves
 * predictably:
 *   1. pre   : KRECK brand *SCSS variables* (e.g. $primary) injected BEFORE
 *              Bootstrap/MDS compile, so derived colours inherit the palette.
 *   2. main  : Boost's full preset (Bootstrap + Moodle Design System + Moodle).
 *   3. extra : KRECK *CSS custom-property* overrides appended AFTER everything,
 *              so our :root / [data-bs-theme=dark] token values win the cascade.
 *
 * @package   theme_microlearn
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content: Boost's preset is reused verbatim as our base.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_microlearn_get_main_scss_content($theme) {
    global $CFG;
    require_once($CFG->dirroot . '/theme/boost/lib.php');

    // Reuse Boost's preset handling (honours any preset set on this theme,
    // otherwise falls back to Boost's default preset).
    return theme_boost_get_main_scss_content($theme);
}

/**
 * SCSS injected before the main content (brand SCSS variables).
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_microlearn_get_pre_scss($theme) {
    global $CFG;
    require_once($CFG->dirroot . '/theme/boost/lib.php');

    $scss = '';

    // 1. KRECK brand variables first (feed Bootstrap + MDS tokens that read
    //    var(--bs-*)).
    $prefile = __DIR__ . '/scss/_pre.scss';
    if (is_readable($prefile)) {
        $scss .= file_get_contents($prefile) . "\n";
    }

    // 2. Then Boost's own pre-scss, so an admin-set brand colour / custom
    //    pre-SCSS on this theme still takes precedence over our defaults.
    $scss .= theme_boost_get_pre_scss($theme);

    return $scss;
}

/**
 * SCSS appended after the main content (KRECK design-token + component overrides).
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_microlearn_get_extra_scss($theme) {
    global $CFG;
    require_once($CFG->dirroot . '/theme/boost/lib.php');

    $scss = '';

    // 1. KRECK partials first, concatenated directly (not via @import) so
    //    inclusion never depends on SASS import-path resolution.
    $partials = ['tokens', 'typography', 'shell', 'components', 'dashboards'];
    foreach ($partials as $partial) {
        $file = __DIR__ . "/scss/_{$partial}.scss";
        if (is_readable($file)) {
            $scss .= "\n" . file_get_contents($file) . "\n";
        }
    }

    // 2. Then Boost's extra SCSS last. This carries the background-image CSS
    //    and the admin "raw SCSS" (customscss) setting, so an administrator's
    //    final overrides take precedence over the theme's own partials.
    $scss .= "\n" . theme_boost_get_extra_scss($theme);

    return $scss;
}

/**
 * Serves theme setting files (e.g. the login background image).
 *
 * Required because this theme registers a file-type setting (backgroundimage);
 * without a pluginfile handler those uploads would not be served.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_microlearn_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $allowedareas = ['backgroundimage', 'loginbackgroundimage'];
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $allowedareas, true)) {
        $theme = theme_config::load('microlearn');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
