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
 * Nothing and not knowing are not the same: a service that fails keeps the doors the person had the last time it answered,
 * which say since when it does not answer, and «To sort out» has a line of it, so nobody reads it as nothing waiting.
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

    /** @var string[] The levels of an alert a service can give, the most pressing first. */
    const LEVELS = ['urgent', 'today'];

    /** @var string[] The order of the list: a service that did not answer, after the urgent of those that did. */
    const ORDER = ['urgent', 'unknown', 'today'];

    /** @var int The alerts in sight; the rest, behind «and N more». A service that did not answer is always in sight. */
    const SHOWN = 5;

    /** @var float[] The column of the service's name in «To sort out», in rem: at least, at most, and per letter. */
    const TAG = [8.5, 13, .5];

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
        $asked = self::ask($userid);
        return self::build($asked['raw'], services::settings()['order'], [self::class, 'icon'], $asked['silent']);
    }

    /**
     * Asks every service the site has not hidden for the doors of a person, and keeps apart those that did not answer: the
     * ones that throw, and the ones whose answer has nothing that fits. Both go to build().
     *
     * @param int $userid
     * @param string[] $skip more services to leave out, by component (an app with one of them turned off)
     * @param callable[]|null $services component => its function; those of the site when null
     * @return array raw (component => its doors) and silent (component => ['doors' => the ones the person had, 'since' => when
     *     it last answered them]); a service the person never had a door of is in neither
     */
    public static function ask(int $userid, array $skip = [], ?array $services = null): array {
        $skip = array_merge(services::settings()['hidden'], $skip);
        $now = time();
        $out = ['raw' => [], 'silent' => []];
        foreach ($services ?? self::services() as $component => $function) {
            if (in_array($component, $skip, true)) {
                continue;
            }
            $start = microtime(true);
            $problem = null;
            $doors = null;
            $fit = [];
            try {
                $doors = call_user_func($function, $userid);
                $fit = is_array($doors) ? self::fitting($component, $doors) : [];
                if (!is_array($doors) || ($doors && !$fit)) {
                    $problem = 'its answer has no door that fits the contract';
                }
            } catch (\Throwable $e) {
                $problem = get_class($e) . ': ' . $e->getMessage();
            }
            if ($problem !== null) {
                debugging('«Your work», ' . $component . ': ' . $problem, DEBUG_DEVELOPER);
                $kept = memory::recall($userid, $component);
                if ($kept) {
                    $out['silent'][$component] = $kept;
                }
                continue;
            }
            $took = (int) round((microtime(true) - $start) * 1000);
            if ($took > self::SLOW) {
                debugging('«Your work», ' . $component . ' took ' . $took . ' ms', DEBUG_DEVELOPER);
            }
            $out['raw'][$component] = $doors;
            memory::keep($userid, $component, $fit, $now);
        }
        return $out;
    }

    /**
     * The doors of an answer that fit the contract.
     *
     * @param string $component
     * @param array $doors
     * @return array[]
     */
    protected static function fitting(string $component, array $doors): array {
        return array_values(array_filter(array_map(fn($door) => self::door($component, $door), $doors)));
    }

    /**
     * The dashboard from what the plugins gave.
     *
     * @param array $raw component => its doors
     * @param string[] $order the components in the order the site chose; the rest after them, by title
     * @param callable|null $icons component => the address of its logo; none when null
     * @param array $silent component => what ask() gives of the ones that did not answer
     * @return array the context of the template
     */
    public static function build(array $raw, array $order = [], ?callable $icons = null, array $silent = []): array {
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
                    $door['silent'] = false;
                    $doors[] = $door;
                }
            }
        }
        foreach ($silent as $component => $kept) {
            $component = (string) $component;
            $icon = $icons ? (string) call_user_func($icons, $component) : '';
            $since = self::since((int) ($kept['since'] ?? 0));
            foreach (is_array($kept['doors'] ?? null) ? $kept['doors'] : [] as $door) {
                $door = self::door($component, $door);
                if ($door) {
                    $door['icon'] = $icon;
                    $door['silent'] = true;
                    $door['status'] = $since;
                    $door['alerts'] = [['text' => get_string('silent_alert', 'block_oksigeniatembiapo', $since),
                        'url' => $door['url'], 'level' => 'unknown']];
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
            // Of one that did not answer, its line is not a count: how many it has is not known.
            $door['alertcount'] = $door['silent'] ? 0 : count($door['alerts']);
            $door['hasalerts'] = $door['alertcount'] > 0;
            $door['urgent'] = $urgent;
            unset($door['alerts']);
        }
        unset($door);
        usort($alerts, function ($a, $b) {
            return [array_search($a['level'], self::ORDER, true), $a['due'] ?? PHP_INT_MAX, $a['place'], $a['seq']] <=>
                [array_search($b['level'], self::ORDER, true), $b['due'] ?? PHP_INT_MAX, $b['place'], $b['seq']];
        });
        $alerts = array_map(function ($alert) {
            unset($alert['place'], $alert['seq']);
            $alert['urgent'] = $alert['level'] === 'urgent';
            $alert['unknown'] = $alert['level'] === 'unknown';
            $alert['levellabel'] = get_string('level_' . $alert['level'], 'block_oksigeniatembiapo');
            return $alert;
        }, $alerts);
        $shown = [];
        $more = [];
        foreach ($alerts as $place => $alert) {
            if ($place < self::SHOWN || $alert['unknown']) {
                $shown[] = $alert;
            } else {
                $more[] = $alert;
            }
        }
        $longest = 0;
        foreach ($alerts as $alert) {
            $longest = max($longest, \core_text::strlen($alert['service']));
        }
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
            'tagwidth' => self::tag_width($longest),
            'shown' => $shown,
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
     * Since when a service does not answer, as a person says it: a time today, or a day and a time.
     *
     * @param int $time when it last answered
     * @return string
     */
    protected static function since(int $time): string {
        $hour = userdate($time, get_string('strftimetime', 'langconfig'));
        if (userdate($time, '%Y%m%d') === userdate(time(), '%Y%m%d')) {
            return get_string('silent_today', 'block_oksigeniatembiapo', $hour);
        }
        $day = userdate($time, get_string('strftimedateshort', 'langconfig'));
        return get_string('silent_day', 'block_oksigeniatembiapo', (object) ['day' => $day, 'time' => $hour]);
    }

    /**
     * How wide the column of the service's name is, so the longest in the list is read whole: its letters roughly
     * measured, plus the logo and the gap; never narrower than the design's column, and past the widest it allows the
     * name wraps to a second line instead of being cut.
     *
     * @param int $letters The letters of the longest name
     * @return string The width in rem, with a dot whatever the language
     */
    public static function tag_width(int $letters): string {
        [$least, $most, $each] = self::TAG;
        $width = min($most, max($least, 2 + $letters * $each));
        return rtrim(rtrim(sprintf('%.2F', $width), '0'), '.');
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
