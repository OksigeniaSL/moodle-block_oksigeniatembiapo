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

namespace block_oksigeniatembiapo\privacy;

use block_oksigeniatembiapo\local\memory;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * «Your work» keeps, of whoever runs something, the doors they had the last time each service answered, as preferences of
 * theirs; Moodle deletes them with the person.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * What is kept.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(memory::PREFIX . '*', 'privacy:metadata:preference:door');
        return $collection;
    }

    /**
     * The preferences of a person.
     *
     * @param int $userid
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        foreach (get_user_preferences(null, null, $userid) as $name => $value) {
            if (strpos($name, memory::PREFIX) === 0) {
                writer::export_user_preference(
                    'block_oksigeniatembiapo',
                    $name,
                    (string) $value,
                    get_string('privacy:metadata:preference:door', 'block_oksigeniatembiapo')
                );
            }
        }
    }
}
