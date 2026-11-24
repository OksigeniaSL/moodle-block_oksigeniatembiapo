<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_oksigeniatembiapo\local;

/**
 * The doors a person had the last time each service answered: what keeps them in their place when a service fails, since
 * nobody else can say then whether that person runs anything in it. One preference per person and service, only its name,
 * its address and when; whoever runs nothing has none. A preference and not a cache, because a service most often fails
 * right after an upgrade, which empties the caches.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memory {
    /** @var string The preferences, before the component. */
    const PREFIX = 'block_oksigeniatembiapo_door_';

    /** @var int When it last answered is written at most this often, in seconds: never later than it was. */
    const REFRESH = 10 * MINSECS;

    /** @var int The longest value a preference takes. */
    const LONGEST = 1333;

    /**
     * A service answered: its doors are kept, or forgotten when it has none for this person any more. A problem writing
     * them never breaks the dashboard.
     *
     * @param int $userid
     * @param string $component
     * @param array[] $doors as the board takes them: key, title and url
     * @param int $now
     * @return void
     */
    public static function keep(int $userid, string $component, array $doors, int $now): void {
        try {
            $name = self::PREFIX . $component;
            $kept = self::read($userid, $component);
            if (!$doors) {
                if ($kept) {
                    unset_user_preference($name, self::who($userid));
                }
                return;
            }
            $doors = array_map(fn($door) => [$door['key'], $door['title'], self::short($door['url'])], array_values($doors));
            if ($kept && $kept['d'] === $doors && $kept['t'] <= $now && $now - $kept['t'] < self::REFRESH) {
                return;
            }
            do {
                $value = json_encode(['t' => $now, 'd' => $doors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } while ($value !== false && \core_text::strlen($value) > self::LONGEST && array_pop($doors));
            if ($value !== false && $doors) {
                set_user_preference($name, $value, self::who($userid));
            }
        } catch (\Throwable $e) {
            debugging('«Your work», the doors of ' . $component . ' are not kept: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The doors a person had, or null when there were none.
     *
     * @param int $userid
     * @param string $component
     * @return array|null doors (key, title and url of each) and since (when the service last answered)
     */
    public static function recall(int $userid, string $component): ?array {
        global $CFG;
        try {
            $kept = self::read($userid, $component);
        } catch (\Throwable $e) {
            debugging('«Your work», the doors of ' . $component . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
        if (!$kept) {
            return null;
        }
        $doors = array_map(fn($door) => ['key' => $door[0], 'title' => $door[1],
            'url' => strpos($door[2], '/') === 0 ? $CFG->wwwroot . $door[2] : $door[2]], $kept['d']);
        return ['doors' => $doors, 'since' => $kept['t']];
    }

    /**
     * What is kept, checked.
     *
     * @param int $userid
     * @param string $component
     * @return array|null t (when) and d (the doors, each a list of key, title and address)
     */
    protected static function read(int $userid, string $component): ?array {
        $value = get_user_preferences(self::PREFIX . $component, null, self::who($userid));
        $kept = $value === null ? null : json_decode((string) $value, true);
        if (!is_array($kept) || !is_int($kept['t'] ?? null) || !is_array($kept['d'] ?? null)) {
            return null;
        }
        $doors = array_filter($kept['d'], fn($door) => is_array($door) && count($door) === 3
            && count(array_filter($door, 'is_string')) === 3);
        return $doors ? ['t' => $kept['t'], 'd' => array_values($doors)] : null;
    }

    /**
     * The person as the preferences take them: null for who is looking, whose preferences are already in the session.
     *
     * @param int $userid
     * @return int|null
     */
    protected static function who(int $userid): ?int {
        global $USER;
        return $userid === (int) ($USER->id ?? 0) ? null : $userid;
    }

    /**
     * An address without the site's, so it is still good if the site changes its own.
     *
     * @param string $url
     * @return string
     */
    protected static function short(string $url): string {
        global $CFG;
        return strpos($url, $CFG->wwwroot . '/') === 0 ? substr($url, strlen($CFG->wwwroot)) : $url;
    }
}
