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
 * Where the block goes when it is installed: at the top of the dashboard everybody starts from, and of the dashboards
 * people have made their own, without touching anything else of theirs. It only shows to whoever runs something.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement {
    /** @var int Its weight in the region: above the blocks Moodle puts there. */
    const WEIGHT = -10;

    /**
     * Puts the block at the top of every dashboard that does not have it yet.
     *
     * @return int how many dashboards got it
     */
    public static function everywhere(): int {
        global $DB;
        $done = 0;
        $system = \context_system::instance();
        // The default dashboard, and the ones people made their own.
        $pages = $DB->get_recordset_select('my_pages', 'private = 1 AND name = :name', ['name' => '__default'], 'id', 'id, userid');
        foreach ($pages as $page) {
            if ($page->userid) {
                $context = \context_user::instance((int) $page->userid, IGNORE_MISSING);
                if (!$context) {
                    continue;
                }
            } else {
                $context = $system;
            }
            if (self::put((int) $context->id, (int) $page->id)) {
                $done++;
            }
        }
        $pages->close();
        return $done;
    }

    /**
     * The block on one dashboard, at the top of its main region, unless it is there already.
     *
     * @param int $contextid the system's for the default dashboard, the person's for their own
     * @param int $pageid
     * @return bool whether it was put
     */
    protected static function put(int $contextid, int $pageid): bool {
        global $DB;
        $already = $DB->record_exists('block_instances', ['blockname' => 'oksigeniatembiapo', 'parentcontextid' => $contextid,
            'pagetypepattern' => 'my-index', 'subpagepattern' => (string) $pageid]);
        if ($already) {
            return false;
        }
        $now = time();
        $id = $DB->insert_record('block_instances', (object) [
            'blockname' => 'oksigeniatembiapo',
            'parentcontextid' => $contextid,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => (string) $pageid,
            'defaultregion' => 'content',
            'defaultweight' => self::WEIGHT,
            'configdata' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        \context_block::instance($id);
        return true;
    }
}
