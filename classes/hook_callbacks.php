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

namespace block_oksigeniatembiapo;

/**
 * «My courses» out of the main bar for whoever has no course (the canteen, the office, the library): Moodle would show
 * them an empty page. When the site wants it, which it does unless it says otherwise.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Takes «My courses» out of the main bar for whoever is enrolled in no course.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        if (get_config('block_oksigeniatembiapo', 'hidemycourses') === '0') {
            return;
        }
        if (enrol_get_my_courses('id', null, 1)) {
            return;
        }
        $node = $hook->get_primaryview()->find('mycourses', \navigation_node::TYPE_ROOTNODE);
        if ($node) {
            $node->remove();
        }
    }
}
