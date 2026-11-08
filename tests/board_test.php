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

use block_oksigeniatembiapo\local\board;
use block_oksigeniatembiapo\local\placement;

/**
 * «Your work»: the doors and alerts of every plugin together, the urgent first, what does not fit left out; and the block
 * at the top of every dashboard, once. Example data.
 *
 * @package    block_oksigeniatembiapo
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_oksigeniatembiapo\local\board
 * @covers     \block_oksigeniatembiapo\local\placement
 */
#[\PHPUnit\Framework\Attributes\CoversClass(board::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(placement::class)]
final class board_test extends \advanced_testcase {
    /**
     * The alerts of all the services together, the urgent first; the doors by name; what does not fit, out.
     */
    public function test_build(): void {
        $board = board::build([
            'local_canteen' => [[
                'key' => 'canteen', 'title' => 'Comedor', 'url' => 'https://school.example/canteen',
                'status' => '312 menús hoy',
                'alerts' => [['text' => '3 libros vencen hoy', 'url' => 'https://school.example/a', 'level' => 'today'],
                    ['text' => 'Nivel inventado', 'level' => 'whenever']],
                'actions' => [
                    ['label' => 'Uno', 'url' => 'https://school.example/1'],
                    ['text' => 'Dos', 'url' => 'https://school.example/2'],
                    ['label' => 'Tres', 'url' => 'https://school.example/3'],
                ],
            ]],
            'local_screens' => [[
                'key' => 'screens', 'title' => 'Bandeja <b>de entrada</b>', 'url' => 'https://school.example/screens',
                'alerts' => [['text' => 'Una pantalla sin señal', 'level' => 'urgent']],
            ], ['title' => 'Sin dirección'], 'not a door'],
        ]);
        $this->assertTrue($board['hasdoors']);
        $this->assertSame(['Bandeja de entrada', 'Comedor'], array_column($board['doors'], 'title'));
        $this->assertSame(['urgent', 'today'], array_column($board['alerts'], 'level'));
        // An alert without its own address goes to the door.
        $this->assertSame('https://school.example/screens', $board['alerts'][0]['url']);
        $this->assertSame('Bandeja de entrada', $board['alerts'][0]['service']);
        $this->assertTrue($board['alerts'][0]['urgent']);
        // Two shortcuts at most, «text» taken as «label».
        $this->assertSame(['Uno', 'Dos'], array_column($board['doors'][1]['actions'], 'label'));
        $empty = board::build(['local_x' => []]);
        $this->assertFalse($empty['hasdoors']);
        $this->assertFalse($empty['hasalerts']);
        $this->assertSame(0, $empty['count']);
    }

    /**
     * On install the block went to the top of the default dashboard; it also goes to a dashboard someone made their own,
     * once.
     */
    public function test_placement(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/my/lib.php');
        $this->resetAfterTest();
        $default = my_get_page(null, MY_PAGE_PRIVATE);
        $this->assertTrue($DB->record_exists('block_instances', ['blockname' => 'oksigeniatembiapo',
            'parentcontextid' => \context_system::instance()->id, 'subpagepattern' => (string) $default->id,
            'defaultregion' => 'content']));
        $user = $this->getDataGenerator()->create_user();
        $own = my_copy_page((int) $user->id, MY_PAGE_PRIVATE);
        // A copy made now already has it, from the default.
        $this->assertSame(0, placement::everywhere());
        $DB->delete_records('block_instances', ['blockname' => 'oksigeniatembiapo',
            'parentcontextid' => \context_user::instance($user->id)->id]);
        $this->assertSame(1, placement::everywhere());
        $this->assertTrue($DB->record_exists('block_instances', ['blockname' => 'oksigeniatembiapo',
            'parentcontextid' => \context_user::instance($user->id)->id, 'subpagepattern' => (string) $own->id]));
        $this->assertSame(0, placement::everywhere());
    }

    /**
     * Without any plugin that takes part, nothing.
     */
    public function test_nobody(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $board = board::collect((int) $user->id);
        $this->assertSame([], $board['alerts']);
        foreach ($board['doors'] as $door) {
            $this->assertNotSame('', $door['title']);
        }
    }
}
