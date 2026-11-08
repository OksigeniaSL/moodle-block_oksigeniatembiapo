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
 * The dashboard: the doors of every plugin that has something for this person, and their alerts together, the urgent first.
 *
 * A plugin takes part with a function in its lib.php, `<component>_oksigenia_work(int $userid): array`, that returns its
 * doors (see README.md): nothing when the person has no permission there. What a plugin returns that does not fit is left
 * out, and a plugin that fails never breaks the dashboard.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class board {
    /** @var int The shortcuts of a door, at most. */
    const ACTIONS = 2;

    /** @var string[] The levels of an alert, the most pressing first. */
    const LEVELS = ['urgent', 'today'];

    /**
     * Asks every plugin for its doors.
     *
     * @param int $userid
     * @return array the context of the template
     */
    public static function collect(int $userid): array {
        $raw = [];
        foreach (get_plugins_with_function('oksigenia_work', 'lib.php') as $type => $plugins) {
            foreach ($plugins as $name => $function) {
                try {
                    $doors = $function($userid);
                } catch (\Throwable $e) {
                    debugging('«Your work», ' . $type . '_' . $name . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
                if (is_array($doors)) {
                    $raw[$type . '_' . $name] = $doors;
                }
            }
        }
        return self::build($raw);
    }

    /**
     * The dashboard from what the plugins gave.
     *
     * @param array $raw component => its doors
     * @return array {alerts, hasalerts, doors, hasdoors, count}
     */
    public static function build(array $raw): array {
        $doors = [];
        $alerts = [];
        foreach ($raw as $component => $list) {
            foreach ($list as $door) {
                $door = self::door((string) $component, $door);
                if (!$door) {
                    continue;
                }
                foreach ($door['alerts'] as $alert) {
                    $alerts[] = $alert + ['service' => $door['title'], 'order' => count($alerts)];
                }
                unset($door['alerts']);
                $doors[] = $door;
            }
        }
        // The urgent first; within a level, as the plugins gave them.
        usort($alerts, function ($a, $b) {
            return [array_search($a['level'], self::LEVELS, true), $a['order']] <=>
                [array_search($b['level'], self::LEVELS, true), $b['order']];
        });
        $alerts = array_map(function ($alert) {
            unset($alert['order']);
            $alert['urgent'] = $alert['level'] === 'urgent';
            $alert['levellabel'] = get_string('level_' . $alert['level'], 'block_oksigeniatembiapo');
            return $alert;
        }, $alerts);
        // The doors by their name, as anyone would look for them.
        $collator = new \Collator(current_language());
        usort($doors, function ($a, $b) use ($collator) {
            return $collator->compare($a['title'], $b['title']);
        });
        return ['alerts' => $alerts, 'hasalerts' => (bool) $alerts, 'doors' => $doors, 'hasdoors' => (bool) $doors,
            'count' => count($doors)];
    }

    /**
     * A door as the template takes it, or null when it does not fit: it needs a title and an address.
     *
     * @param string $component the plugin that gave it
     * @param mixed $door
     * @return array|null
     */
    protected static function door(string $component, $door): ?array {
        if (!is_array($door)) {
            return null;
        }
        $title = self::text($door['title'] ?? '');
        $url = self::url($door['url'] ?? '');
        if ($title === '' || $url === '') {
            return null;
        }
        $alerts = [];
        foreach (is_array($door['alerts'] ?? null) ? $door['alerts'] : [] as $alert) {
            $text = is_array($alert) ? self::text($alert['text'] ?? '') : '';
            $level = is_array($alert) ? (string) ($alert['level'] ?? '') : '';
            if ($text !== '' && in_array($level, self::LEVELS, true)) {
                $alerts[] = ['text' => $text, 'url' => self::url($alert['url'] ?? '') ?: $url, 'level' => $level];
            }
        }
        $actions = [];
        foreach (is_array($door['actions'] ?? null) ? $door['actions'] : [] as $action) {
            // The contract says «label»; «text» is taken too.
            $label = is_array($action) ? self::text($action['label'] ?? ($action['text'] ?? '')) : '';
            $to = is_array($action) ? self::url($action['url'] ?? '') : '';
            if ($label !== '' && $to !== '' && count($actions) < self::ACTIONS) {
                $actions[] = ['label' => $label, 'url' => $to];
            }
        }
        $key = self::text($door['key'] ?? '') ?: $component;
        return [
            'key' => $key,
            'id' => 'tembiapo-' . preg_replace('/[^a-z0-9_-]+/', '-', \core_text::strtolower($key)),
            'component' => $component,
            'title' => $title,
            'url' => $url,
            'status' => self::text($door['status'] ?? ''),
            'alerts' => $alerts,
            'actions' => $actions,
            'hasactions' => (bool) $actions,
            'icon' => self::url($door['icon'] ?? ''),
            'initial' => \core_text::strtoupper(\core_text::substr($title, 0, 1)),
        ];
    }

    /**
     * A line of plain text.
     *
     * @param mixed $value
     * @return string
     */
    protected static function text($value): string {
        if (!is_scalar($value)) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
    }

    /**
     * An address of this site or of the web, or ''.
     *
     * @param mixed $value
     * @return string
     */
    protected static function url($value): string {
        if ($value instanceof \moodle_url) {
            return $value->out(false);
        }
        $value = is_scalar($value) ? trim((string) $value) : '';
        return $value !== '' && clean_param($value, PARAM_URL) === $value ? $value : '';
    }
}
