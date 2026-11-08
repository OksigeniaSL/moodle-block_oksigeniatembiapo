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
 * The services of the site as the administrator orders and hides them: the list fills itself with whatever is installed,
 * and one that arrives later goes at the end, by its name, without anybody adding it.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class services {
    /**
     * What the site chose: the order of the components and the hidden ones.
     *
     * @return array {order: string[], hidden: string[]}
     */
    public static function settings(): array {
        $saved = json_decode((string) get_config('block_oksigeniatembiapo', 'services'), true);
        $saved = is_array($saved) ? $saved : [];
        $list = function ($value): array {
            return array_values(array_unique(array_filter(is_array($value) ? $value : [], function ($c) {
                return is_string($c) && preg_match('/^[a-z]+_[a-z0-9_]+$/', $c);
            })));
        };
        return ['order' => $list($saved['order'] ?? []), 'hidden' => $list($saved['hidden'] ?? [])];
    }

    /**
     * The services installed, in the site's order and the new ones after them by name.
     *
     * @return array[] each {component, name, hidden}
     */
    public static function listed(): array {
        $settings = self::settings();
        $out = [];
        foreach (array_keys(board::services()) as $component) {
            $out[$component] = ['component' => $component, 'name' => self::name($component),
                'hidden' => in_array($component, $settings['hidden'], true)];
        }
        $rank = array_flip($settings['order']);
        $collator = new \Collator(current_language());
        uasort($out, function ($a, $b) use ($rank, $collator) {
            return ($rank[$a['component']] ?? PHP_INT_MAX) <=> ($rank[$b['component']] ?? PHP_INT_MAX)
                ?: $collator->compare($a['name'], $b['name']);
        });
        return array_values($out);
    }

    /**
     * The name of a plugin, as the site shows it.
     *
     * @param string $component
     * @return string
     */
    public static function name(string $component): string {
        return get_string_manager()->string_exists('pluginname', $component) ? get_string('pluginname', $component) : $component;
    }

    /**
     * Keeps what the administrator chose.
     *
     * @param string[] $order the components, in order
     * @param string[] $hidden
     * @return void
     */
    public static function save(array $order, array $hidden): void {
        $chosen = json_encode(['order' => array_values($order), 'hidden' => array_values($hidden)]);
        set_config('services', $chosen, 'block_oksigeniatembiapo');
    }
}
