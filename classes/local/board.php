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
 * The dashboard: one panel for every service a person runs, whichever and however many the site has, the ones that do not
 * exist yet too. Above, «To sort out»: the alerts of all of them in one list, the urgent first, then by when they are due;
 * below, «Your services»: a door into each, all the same size, in a fixed order.
 *
 * The block knows no product. A plugin takes part with a function in its lib.php, `<component>_oksigenia_work(int $userid):
 * array`, that returns its doors (the contract, in README.md); its logo is its own icon, `pix/icon`. What a plugin returns
 * that does not fit is left out, fields it does not know are ignored, and a plugin that fails never breaks the dashboard.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class board {
    /** @var int The version of the contract between the block and the plugins (README.md). */
    const CONTRACT = 2;

    /** @var int The shortcuts of a door, at most. */
    const ACTIONS = 2;

    /** @var string[] The levels of an alert, the most pressing first. */
    const LEVELS = ['urgent', 'today'];

    /** @var int The alerts in sight; the rest, behind «and N more». */
    const SHOWN = 5;

    /** @var int More doors than this, and they go compact. */
    const COMPACT_AFTER = 8;

    /** @var int A plugin slower than this, in milliseconds, is noted for developers. */
    const SLOW = 50;

    /**
     * The plugins that take part, by component.
     *
     * @return callable[] component => its function
     */
    public static function services(): array {
        $out = [];
        foreach (get_plugins_with_function('oksigenia_work', 'lib.php') as $type => $plugins) {
            foreach ($plugins as $name => $function) {
                $out[$type . '_' . $name] = $function;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Asks every service the site has not hidden for its doors, and builds the dashboard.
     *
     * @param int $userid
     * @return array the context of the template
     */
    public static function collect(int $userid): array {
        $settings = services::settings();
        $raw = [];
        foreach (self::services() as $component => $function) {
            if (in_array($component, $settings['hidden'], true)) {
                continue;
            }
            $start = microtime(true);
            try {
                $doors = $function($userid);
            } catch (\Throwable $e) {
                debugging('«Your work», ' . $component . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            $took = (int) round((microtime(true) - $start) * 1000);
            if ($took > self::SLOW) {
                debugging('«Your work», ' . $component . ' took ' . $took . ' ms', DEBUG_DEVELOPER);
            }
            if (is_array($doors)) {
                $raw[$component] = $doors;
            }
        }
        return self::build($raw, $settings['order'], [self::class, 'icon']);
    }

    /**
     * The dashboard from what the plugins gave.
     *
     * @param array $raw component => its doors
     * @param string[] $order the components in the order the site chose; the rest after them, by title
     * @param callable|null $icons component => the address of its logo; none when null
     * @return array the context of the template
     */
    public static function build(array $raw, array $order = [], ?callable $icons = null): array {
        $doors = [];
        foreach ($raw as $component => $list) {
            $component = (string) $component;
            if (!is_array($list)) {
                continue;
            }
            $icon = $icons ? (string) call_user_func($icons, $component) : '';
            foreach ($list as $door) {
                $door = self::door($component, $door);
                if ($door) {
                    $door['icon'] = $icon;
                    $doors[] = $door;
                }
            }
        }
        // A fixed order, never the one they came in: the site's first, then by title.
        $collator = new \Collator(current_language());
        $rank = array_flip(array_values($order));
        usort($doors, function ($a, $b) use ($rank, $collator) {
            $ra = $rank[$a['component']] ?? PHP_INT_MAX;
            $rb = $rank[$b['component']] ?? PHP_INT_MAX;
            return $ra <=> $rb ?: $collator->compare($a['title'], $b['title']);
        });
        // The alerts of every door in one list: the urgent first, then by when they are due, then by the order of the doors.
        $alerts = [];
        foreach ($doors as $place => &$door) {
            $urgent = false;
            foreach ($door['alerts'] as $alert) {
                $urgent = $urgent || $alert['level'] === 'urgent';
                $alerts[] = $alert + ['service' => $door['title'], 'servicekey' => $door['key'], 'serviceicon' => $door['icon'],
                    'place' => $place, 'seq' => count($alerts)];
            }
            $door['alertcount'] = count($door['alerts']);
            $door['hasalerts'] = $door['alertcount'] > 0;
            $door['urgent'] = $urgent;
            unset($door['alerts']);
        }
        unset($door);
        usort($alerts, function ($a, $b) {
            return [array_search($a['level'], self::LEVELS, true), $a['due'] ?? PHP_INT_MAX, $a['place'], $a['seq']] <=>
                [array_search($b['level'], self::LEVELS, true), $b['due'] ?? PHP_INT_MAX, $b['place'], $b['seq']];
        });
        $alerts = array_map(function ($alert) {
            unset($alert['place'], $alert['seq']);
            $alert['urgent'] = $alert['level'] === 'urgent';
            $alert['levellabel'] = get_string('level_' . $alert['level'], 'block_oksigeniatembiapo');
            return $alert;
        }, $alerts);
        $more = array_slice($alerts, self::SHOWN);
        $count = count($doors);
        if ($count <= 1) {
            $layout = 'one';
        } else if ($count <= 4) {
            $layout = 'row';
        } else if ($count <= self::COMPACT_AFTER) {
            $layout = 'grid';
        } else {
            $layout = 'compact';
        }
        return [
            'alerts' => $alerts,
            'hasalerts' => (bool) $alerts,
            'shown' => array_slice($alerts, 0, self::SHOWN),
            'more' => $more,
            'hasmore' => (bool) $more,
            'morecount' => count($more),
            'morelabel' => $more ? get_string('more', 'block_oksigeniatembiapo', count($more)) : '',
            'doors' => $doors,
            'hasdoors' => (bool) $doors,
            'count' => $count,
            'cols' => $layout === 'one' ? 1 : ($layout === 'row' ? $count : 4),
            'layout' => $layout,
        ];
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
            if ($text === '' || !in_array($level, self::LEVELS, true)) {
                continue;
            }
            $one = ['text' => $text, 'url' => self::url($alert['url'] ?? '') ?: $url, 'level' => $level];
            $due = $alert['due'] ?? null;
            if (is_numeric($due) && (int) $due > 0) {
                $one['due'] = (int) $due;
            }
            $alerts[] = $one;
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
            'id' => 'tembiapo-' . trim(preg_replace('/[^a-z0-9_-]+/', '-', \core_text::strtolower($key)), '-'),
            'component' => $component,
            'title' => $title,
            'url' => $url,
            'status' => self::text($door['status'] ?? ''),
            'alerts' => $alerts,
            'actions' => $actions,
            'hasactions' => (bool) $actions,
            'initial' => \core_text::strtoupper(\core_text::substr($title, 0, 1)),
        ];
    }

    /**
     * The logo of a service: its plugin's own icon (pix/icon), in the colours of its brand; the block's neutral one when the
     * plugin has none.
     *
     * @param string $component
     * @return string its address
     */
    public static function icon(string $component): string {
        global $OUTPUT;
        static $kept = [];
        if (isset($kept[$component])) {
            return $kept[$component];
        }
        $dir = \core_component::get_component_directory($component);
        $has = $dir && (is_readable($dir . '/pix/icon.svg') || is_readable($dir . '/pix/icon.png'));
        $url = $has ? $OUTPUT->image_url('icon', $component) : $OUTPUT->image_url('generic', 'block_oksigeniatembiapo');
        return $kept[$component] = $url->out(false);
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
