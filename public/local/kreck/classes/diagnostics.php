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

namespace local_kreck;

/**
 * Adds KRECK page context and a support reference to LMS's normal error handling.
 *
 * @package   local_kreck
 * @copyright 2026 KRECK MicroLearn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class diagnostics {
    /** @var string */
    private static $requestid;
    /** @var string */
    private static $page;
    /** @var callable|null */
    private static $previouserrorhandler;
    /** @var callable|null */
    private static $previousexceptionhandler;
    /** @var bool */
    private static $registered = false;

    /**
     * Register handlers after LMS's config.php has initialized its handlers.
     *
     * @param string $file
     * @return void
     */
    public static function register(string $file): void {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        self::$requestid = bin2hex(random_bytes(8));
        self::$page = basename($file);
        self::$previouserrorhandler = set_error_handler([self::class, 'handle_error']);
        self::$previousexceptionhandler = set_exception_handler([self::class, 'handle_exception']);
    }

    /**
     * Log PHP warnings with the KRECK route context and preserve LMS handling.
     *
     * @param int $severity
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     */
    public static function handle_error(int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        if ($severity & (E_WARNING | E_USER_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
            self::write_log('php_warning', [
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);
        }

        if (is_callable(self::$previouserrorhandler)) {
            return (bool) call_user_func(self::$previouserrorhandler, $severity, $message, $file, $line);
        }

        return false;
    }

    /**
     * Log an uncaught exception, then delegate to LMS's original handler.
     *
     * @param \Throwable $exception
     * @return void
     */
    public static function handle_exception(\Throwable $exception): void {
        $reference = self::report_caught($exception, 'uncaught');

        if ($exception instanceof \moodle_exception && property_exists($exception, 'debuginfo')) {
            $property = new \ReflectionProperty($exception, 'debuginfo');
            if ($property->isPublic()) {
                $current = (string) $property->getValue($exception);
                $property->setValue($exception, trim($current . "\nKRECK diagnostic reference: " . $reference));
            }
        }

        if (is_callable(self::$previousexceptionhandler)) {
            call_user_func(self::$previousexceptionhandler, $exception);
            return;
        }

        if (function_exists('default_exception_handler')) {
            default_exception_handler($exception);
        }
    }

    /**
     * Log a handled page failure and return its support reference.
     *
     * @param \Throwable $exception
     * @param string $stage
     * @return string
     */
    public static function report_caught(\Throwable $exception, string $stage): string {
        $trace = [];
        foreach (array_slice($exception->getTrace(), 0, 20) as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
            ];
        }

        $reference = self::write_log('exception', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ]);

        return $reference;
    }

    /**
     * Write one structured entry to PHP's configured error log.
     *
     * @param string $event
     * @param array $details
     * @return string
     */
    private static function write_log(string $event, array $details): string {
        global $USER;

        $entry = array_merge([
            'request_id' => self::$requestid,
            'page' => self::$page,
            'path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
            'user_id' => isset($USER->id) ? (int) $USER->id : null,
            'event' => $event,
        ], $details);

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        error_log('[KRECK-DIAGNOSTIC] ' . ($json === false ? 'Unable to encode diagnostic event.' : $json));

        return self::$requestid;
    }
}
