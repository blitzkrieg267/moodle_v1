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
 * Mirrors SMS events into Moodle calendar events, one SMS event to one calendar event per audience.
 *
 * Every mirrored event is owned by this plugin (event.component = local_kreck) and identified by a
 * stable uuid derived from the SMS source id, so a repeat push updates in place, a changed SMS event
 * changes the same calendar event, and a removed/cancelled SMS event removes it. Nothing here edits
 * events teachers or students created themselves.
 *
 * Audience mapping (the SMS decides the audience, this class maps it onto Moodle's event model):
 *   scope "site"   -> eventtype site   (courseid = SITEID)                         visible to everyone
 *   scope "course" -> eventtype course (one event per listed course shortname)     visible to the course's participants
 *   scope "user"   -> eventtype user   (one event per listed username)             visible to that person only
 *
 * Moodle has no "teachers only" course event (visible = 0 hides an event from everyone in the calendar views), so
 * an audience narrower than the whole course (for example a lesson the SMS hides from students, or a copy for
 * parents) is expressed by the SMS as a "user" scope listing exactly who may see it.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kreck\events;

defined('MOODLE_INTERNAL') || die();

/**
 * Applies a batch of SMS events (upsert / delete) to the Moodle calendar.
 */
class mirror {

    /** Owning component stored on every mirrored event. */
    const COMPONENT = 'local_kreck';

    /** Prefix of every mirrored event uuid. */
    const UUID_PREFIX = 'kreck:';

    /** Hard cap on events per call (keeps a request short and the lock brief). */
    const MAX_EVENTS = 500;

    /** @var string[] Allowed audiences. */
    const SCOPES = ['site', 'course', 'user'];

    /** @var array<string,\stdClass|null> shortname => course (or null) lookups, per instance. */
    protected $courses = [];

    /** @var array<string,int> username => user id (0 when unknown), per instance. */
    protected $users = [];

    /**
     * Apply events.
     *
     * @param array $events decoded SMS events
     * @param string $defaulttz IANA timezone used when an event has none
     * @param bool $dryrun when true nothing is written; the result shows what would happen
     * @return array ['counts' => [...], 'results' => [['source_id','result','message'], ...]]
     */
    public function apply(array $events, string $defaulttz, bool $dryrun = false): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];
        $results = [];

        foreach ($events as $event) {
            $sourceid = (string) ($event['source_id'] ?? '');
            try {
                $one = $this->apply_one((array) $event, $defaulttz, $dryrun);
            } catch (\Throwable $e) {
                $one = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 1,
                    'message' => $e->getMessage()];
            }
            foreach ($counts as $key => $unused) {
                $counts[$key] += (int) ($one[$key] ?? 0);
            }
            $results[] = ['source_id' => $sourceid, 'result' => $this->summarise($one), 'message' => (string) ($one['message'] ?? '')];
        }
        return ['counts' => $counts, 'results' => $results];
    }

    /**
     * One-word outcome for the SMS ledger.
     *
     * @param array $one counters for a single SMS event
     * @return string
     */
    protected function summarise(array $one): string {
        if (!empty($one['errors'])) {
            return 'error';
        }
        if (!empty($one['created'])) {
            return 'created';
        }
        if (!empty($one['updated'])) {
            return 'updated';
        }
        if (!empty($one['deleted'])) {
            return 'deleted';
        }
        if (!empty($one['skipped']) && empty($one['unchanged'])) {
            return 'skipped';
        }
        return 'unchanged';
    }

    /**
     * Apply one SMS event.
     *
     * @param array $e the event
     * @param string $defaulttz
     * @param bool $dryrun
     * @return array counters + message
     */
    protected function apply_one(array $e, string $defaulttz, bool $dryrun): array {
        global $DB;

        $out = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0, 'message' => ''];

        $sourceid = (string) ($e['source_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_:.\-]{1,160}$/', $sourceid)) {
            $out['errors'] = 1;
            $out['message'] = 'Invalid source_id.';
            return $out;
        }
        $op = (string) ($e['op'] ?? 'upsert');
        $status = strtolower((string) ($e['status'] ?? 'confirmed'));

        // Delete (explicit, or the SMS event was cancelled).
        if ($op === 'delete' || $status === 'cancelled') {
            $out['deleted'] = $this->delete_source($sourceid, [], $dryrun);
            return $out;
        }
        if ($op !== 'upsert') {
            $out['errors'] = 1;
            $out['message'] = 'Unknown op.';
            return $out;
        }

        $name = trim((string) ($e['name'] ?? ''));
        $scope = (string) ($e['scope'] ?? '');
        if ($name === '' || !in_array($scope, self::SCOPES, true)) {
            $out['errors'] = 1;
            $out['message'] = 'An event needs a name and a scope of site, course or user.';
            return $out;
        }

        $tz = (string) ($e['timezone'] ?? '') ?: $defaulttz;
        [$timestart, $duration] = $this->times($e, $tz);

        $base = [
            'name' => \core_text::substr($name, 0, 255),
            'description' => $this->describe($e),
            'location' => \core_text::substr((string) ($e['location'] ?? ''), 0, 255),
            'timestart' => $timestart,
            'timeduration' => $duration,
            'visible' => 1,
        ];

        // Work out every (uuid => audience) this event must exist for.
        $targets = [];
        $missing = [];
        if ($scope === 'site') {
            $targets[self::UUID_PREFIX . $sourceid] = ['eventtype' => 'site', 'courseid' => SITEID, 'userid' => 0, 'visible' => 1];
        } else if ($scope === 'course') {
            $list = array_values(array_unique(array_filter(array_map('strval', (array) ($e['courses'] ?? ($e['course'] ?? []))))));
            foreach ($list as $shortname) {
                $course = $this->course($shortname);
                if (!$course) {
                    $missing[] = $shortname;
                    continue;
                }
                $targets[self::UUID_PREFIX . $sourceid . '@c:' . $shortname] =
                    ['eventtype' => 'course', 'courseid' => (int) $course->id, 'userid' => 0, 'visible' => 1];
            }
        } else {
            $list = array_values(array_unique(array_filter(array_map('strval', (array) ($e['users'] ?? [])))));
            foreach ($list as $username) {
                $uid = $this->user($username);
                if (!$uid) {
                    $missing[] = $username;
                    continue;
                }
                $targets[self::UUID_PREFIX . $sourceid . '@u:' . \core_text::strtolower($username)] =
                    ['eventtype' => 'user', 'courseid' => 0, 'userid' => $uid, 'visible' => 1];
            }
        }

        foreach ($targets as $uuid => $audience) {
            $desired = $base + ['uuid' => $uuid] + $audience;
            $state = $this->upsert($desired, !empty($e['adopt']), $dryrun);
            $out[$state]++;
        }
        // Remove copies that no longer belong (audience shrank, course dropped, ...).
        $out['deleted'] += $this->delete_source($sourceid, array_keys($targets), $dryrun);

        if ($missing) {
            $out['skipped'] += count($missing);
            $out['message'] = 'Not found on the LMS yet: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? '…' : '');
        }
        if (!$targets && !$missing) {
            $out['skipped'] = 1;
            $out['message'] = 'No audience.';
        }
        return $out;
    }

    /**
     * Create, update or leave a calendar event.
     *
     * @param array $desired the full desired record (including uuid, eventtype, courseid, userid)
     * @param bool $adopt whether a legacy uuid-less course event at the same time may be taken over
     * @param bool $dryrun
     * @return string 'created' | 'updated' | 'unchanged'
     */
    protected function upsert(array $desired, bool $adopt, bool $dryrun): string {
        global $DB;

        $existing = $DB->get_record('event', ['uuid' => $desired['uuid'], 'component' => self::COMPONENT], '*', IGNORE_MULTIPLE);

        // Take over an event the older timetable export created for the same class and time (no uuid yet),
        // so the move to uuid-based sync never doubles up the calendar.
        if (!$existing && $adopt && $desired['eventtype'] === 'course') {
            $existing = $DB->get_record_select('event',
                "courseid = :c AND eventtype = 'course' AND (uuid = '' OR uuid IS NULL) AND (component = '' OR component IS NULL)
                 AND modulename = '' AND timestart = :ts AND timeduration = :td",
                ['c' => $desired['courseid'], 'ts' => $desired['timestart'], 'td' => $desired['timeduration']],
                '*', IGNORE_MULTIPLE) ?: null;
        }

        if ($existing) {
            $changed = [];
            // Moodle stamps the creating user on course/site events, so userid is only meaningful (and compared) for user events.
            $fields = ['name', 'description', 'location', 'timestart', 'timeduration', 'visible', 'eventtype', 'courseid', 'uuid'];
            if ($desired['eventtype'] === 'user') {
                $fields[] = 'userid';
            }
            foreach ($fields as $f) {
                if ((string) $existing->$f !== (string) $desired[$f]) {
                    $changed[$f] = $desired[$f];
                }
            }
            if ((string) $existing->component !== self::COMPONENT) {
                $changed['component'] = self::COMPONENT;
            }
            if (!$changed) {
                return 'unchanged';
            }
            if (!$dryrun) {
                $changed['timesort'] = $desired['timestart'];
                \calendar_event::load((int) $existing->id)->update((object) $changed, false);
            }
            return 'updated';
        }

        if (!$dryrun) {
            \calendar_event::create((object) [
                'name' => $desired['name'],
                'description' => $desired['description'],
                'format' => FORMAT_HTML,
                'categoryid' => 0,
                'courseid' => $desired['courseid'],
                'groupid' => 0,
                'userid' => $desired['userid'],
                'repeatid' => 0,
                'component' => self::COMPONENT,
                'modulename' => '',
                'instance' => 0,
                'type' => CALENDAR_EVENT_TYPE_STANDARD,
                'eventtype' => $desired['eventtype'],
                'timestart' => $desired['timestart'],
                'timeduration' => $desired['timeduration'],
                'timesort' => $desired['timestart'],
                'visible' => $desired['visible'],
                'uuid' => $desired['uuid'],
                'sequence' => 1,
                'location' => $desired['location'],
            ], false);
        }
        return 'created';
    }

    /**
     * Delete every mirrored copy of an SMS event, except the uuids listed in $keep.
     *
     * @param string $sourceid
     * @param string[] $keep uuids to leave alone
     * @param bool $dryrun
     * @return int number deleted
     */
    protected function delete_source(string $sourceid, array $keep, bool $dryrun): int {
        global $DB;
        $like = $DB->sql_like('uuid', ':pat', true, true, false, '\\');
        $pattern = $DB->sql_like_escape(self::UUID_PREFIX . $sourceid, '\\') . '@%';
        $rows = $DB->get_records_select('event', "component = :comp AND (uuid = :exact OR {$like})",
            ['comp' => self::COMPONENT, 'exact' => self::UUID_PREFIX . $sourceid, 'pat' => $pattern], '', 'id, uuid');
        $deleted = 0;
        foreach ($rows as $row) {
            if (in_array($row->uuid, $keep, true)) {
                continue;
            }
            if (!$dryrun) {
                \calendar_event::load((int) $row->id)->delete();
            }
            $deleted++;
        }
        return $deleted;
    }

    /**
     * Turn an event's start/end into [timestart, timeduration] in the event's timezone.
     *
     * @param array $e
     * @param string $tz
     * @return int[]
     */
    protected function times(array $e, string $tz): array {
        try {
            $zone = new \DateTimeZone($tz);
        } catch (\Throwable $ex) {
            throw new \invalid_parameter_exception('Unknown timezone: ' . $tz);
        }
        $parse = static function (?string $value) use ($zone): ?\DateTimeImmutable {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value, $zone)
                ?: \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone)
                ?: \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone)
                ?: \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, $zone)
                ?: \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
            if (!$dt) {
                throw new \invalid_parameter_exception('Unreadable date/time: ' . $value);
            }
            return $dt;
        };
        $start = $parse($e['start'] ?? null);
        if (!$start) {
            throw new \invalid_parameter_exception('An event needs a start.');
        }
        $end = $parse($e['end'] ?? null);
        if (!empty($e['all_day'])) {
            // An all-day event is a calendar DATE, not an instant. Anchor it at midnight in the LMS's own default
            // timezone so everyone on the site default sees it on that date, whatever timezone the SMS uses.
            $site = new \DateTimeZone(\core_date::get_default_php_timezone());
            $startday = new \DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00', $site);
            $endday = $end ? new \DateTimeImmutable($end->format('Y-m-d') . ' 00:00:00', $site) : $startday;
            return [$startday->getTimestamp(), max(0, $endday->getTimestamp() - $startday->getTimestamp())];
        }
        $duration = $end ? $end->getTimestamp() - $start->getTimestamp() : 0;
        if ($duration < 0) {
            throw new \invalid_parameter_exception('An event cannot end before it starts.');
        }
        return [$start->getTimestamp(), $duration];
    }

    /**
     * The description shown in the Moodle calendar: the SMS text plus its label and a link back to the SMS.
     *
     * @param array $e
     * @return string HTML
     */
    protected function describe(array $e): string {
        $html = '';
        $text = trim((string) ($e['description'] ?? ''));
        if ($text !== '') {
            $html .= '<p>' . nl2br(s($text), false) . '</p>';
        }
        $meta = [];
        if (!empty($e['label'])) {
            $meta[] = s((string) $e['label']);
        }
        $base = trim((string) get_config('local_kreck', 'gibbonbaseurl'));
        $url = trim((string) ($e['url'] ?? ''));
        if ($base !== '' && $url !== '' && $url[0] === '/') {
            $meta[] = '<a href="' . s(rtrim($base, '/') . $url) . '">' . s(get_string('viewinsms', 'local_kreck')) . '</a>';
        }
        if ($meta) {
            $html .= '<p><small>' . implode(' · ', $meta) . '</small></p>';
        }
        return $html;
    }

    /**
     * Course by shortname (cached for this call).
     *
     * @param string $shortname
     * @return \stdClass|null
     */
    protected function course(string $shortname): ?\stdClass {
        global $DB;
        if (!array_key_exists($shortname, $this->courses)) {
            $this->courses[$shortname] = $DB->get_record('course', ['shortname' => $shortname], 'id, shortname') ?: null;
        }
        return $this->courses[$shortname];
    }

    /**
     * User id by username (cached for this call), 0 when unknown.
     *
     * @param string $username
     * @return int
     */
    protected function user(string $username): int {
        global $DB;
        $key = \core_text::strtolower($username);
        if (!array_key_exists($key, $this->users)) {
            $this->users[$key] = (int) $DB->get_field('user', 'id', ['username' => $key, 'deleted' => 0], IGNORE_MULTIPLE);
        }
        return $this->users[$key];
    }
}
