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

/**
 * «Your work»: at the top of the dashboard of whoever runs something (the canteen, the library, the screens…), what is
 * waiting to be sorted out across all of them, and a door into each. Nothing for whoever runs nothing.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_oksigeniatembiapo extends block_base {
    /**
     * Its title.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('title', 'block_oksigeniatembiapo');
    }

    /**
     * Only on the dashboard.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'all' => false];
    }

    /**
     * One is enough.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * It has its site settings.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * The dashboard of the person, or nothing: an empty block is not shown.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        $board = \block_oksigeniatembiapo\local\board::collect((int) $USER->id);
        if ($board['hasdoors']) {
            $this->content->text = $OUTPUT->render_from_template('block_oksigeniatembiapo/board', $board);
            // The figures of each state in bold: a plain script, because Firefox 98 does not start AMD modules in Moodle 5.3.
            // Without it the panel is the same, in one weight.
            $version = ['v' => (string) get_config('block_oksigeniatembiapo', 'version')];
            $this->page->requires->js(new \moodle_url('/blocks/oksigeniatembiapo/js/tembiapo.js', $version));
        }
        return $this->content;
    }
}
