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
 * Parses an onboarding manifest and applies the default course blueprint.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\onboarding;

defined('MOODLE_INTERNAL') || die();

/**
 * A normalised view over the onboarding JSON manifest.
 */
class manifest {

    /** @var array Decoded manifest. */
    protected $data;

    /**
     * @param array $data Decoded JSON manifest.
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Build from a JSON string.
     *
     * @param string $json
     * @return manifest
     */
    public static function from_json(string $json): manifest {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \moodle_exception('onb_badjson', 'local_kreck');
        }
        return new self($data);
    }

    /**
     * Explicit category definitions, plus any implied by course paths.
     *
     * @return string[] Unique category paths, parents before children.
     */
    public function category_paths(): array {
        $paths = [];
        foreach ($this->data['categories'] ?? [] as $cat) {
            if (!empty($cat['category_path'])) {
                $paths[] = $cat['category_path'];
            } else if (!empty($cat['name'])) {
                $paths[] = $cat['name'];
            }
        }
        foreach ($this->data['courses'] ?? [] as $course) {
            if (!empty($course['category_path'])) {
                $paths[] = $course['category_path'];
            }
        }
        // Expand each path into its ancestors so parents are created first.
        $expanded = [];
        foreach ($paths as $path) {
            $parts = array_map('trim', explode('/', $path));
            $acc = [];
            foreach ($parts as $part) {
                $acc[] = $part;
                $expanded[implode(' / ', $acc)] = true;
            }
        }
        $result = array_keys($expanded);
        // Shorter paths (parents) first.
        usort($result, static fn($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));
        return $result;
    }

    /**
     * Users to create.
     *
     * @return array
     */
    public function users(): array {
        return $this->data['users'] ?? [];
    }

    /**
     * Courses.
     *
     * @return array
     */
    public function courses(): array {
        return $this->data['courses'] ?? [];
    }

    /**
     * Institution/batch metadata.
     *
     * @return array
     */
    public function meta(): array {
        return $this->data['batch_metadata'] ?? [];
    }

    /**
     * The effective section/module plan for a course: the author's sections if
     * given, otherwise the default "complete" blueprint, always with an
     * announcements forum in the general section.
     *
     * @param array $course One course entry.
     * @return array List of ['section_num','title','modules'=>[...]]
     */
    public function effective_sections(array $course): array {
        $weeks = max(1, (int) ($course['numsections'] ?? 8));

        if (!empty($course['sections'])) {
            $sections = $course['sections'];
            if (!$this->has_general_forum($sections)) {
                array_unshift($sections, $this->general_section());
            }
            return $sections;
        }

        // No sections supplied -> generate the full blueprint.
        $sections = [$this->general_section()];
        for ($w = 1; $w <= $weeks; $w++) {
            $sections[] = [
                'section_num' => $w,
                'title' => get_string('onb_weektitle', 'local_kreck', $w),
                'modules' => $this->week_blueprint($w),
            ];
        }
        return $sections;
    }

    /**
     * The general (section 0) administration block with an announcements forum.
     *
     * @return array
     */
    protected function general_section(): array {
        return [
            'section_num' => 0,
            'title' => get_string('onb_generaltitle', 'local_kreck'),
            'modules' => [
                [
                    'type' => 'forum',
                    'forumtype' => 'news',
                    'name' => get_string('onb_announcements', 'local_kreck'),
                    'intro' => '<p>' . get_string('onb_announcements_intro', 'local_kreck') . '</p>',
                ],
            ],
        ];
    }

    /**
     * One of every core asset for a week, media as text placeholders.
     *
     * @param int $week
     * @return array
     */
    protected function week_blueprint(int $week): array {
        $media = '<p>[INSERT LESSON VIDEO HERE] — paste a link or, after setup, edit this page '
            . 'and use the editor toolbar to attach a file. No media is added during onboarding.</p>';
        return [
            ['type' => 'page', 'name' => get_string('onb_mod_notes', 'local_kreck', $week),
                'content' => $media . '<p>[INSERT WEEK ' . $week . ' NOTES HERE]</p>'],
            ['type' => 'assign', 'name' => get_string('onb_mod_assign', 'local_kreck', $week),
                'intro' => '<p>[INSERT TASK BRIEF HERE]</p>'],
            ['type' => 'quiz', 'name' => get_string('onb_mod_quiz', 'local_kreck', $week),
                'intro' => '<p>[ADD QUESTIONS AFTER SETUP]</p>'],
            ['type' => 'forum', 'name' => get_string('onb_mod_forum', 'local_kreck', $week),
                'intro' => '<p>[DISCUSSION PROMPT HERE]</p>'],
            ['type' => 'url', 'name' => get_string('onb_mod_path', 'local_kreck', $week),
                'externalurl' => 'https://example.com/replace-this-link'],
        ];
    }

    /**
     * Does a section list already contain a general-section forum?
     *
     * @param array $sections
     * @return bool
     */
    protected function has_general_forum(array $sections): bool {
        foreach ($sections as $section) {
            if ((int) ($section['section_num'] ?? -1) !== 0) {
                continue;
            }
            foreach ($section['modules'] ?? [] as $module) {
                if (module_factory::normalise_type($module['type'] ?? '') === 'forum') {
                    return true;
                }
            }
        }
        return false;
    }
}
