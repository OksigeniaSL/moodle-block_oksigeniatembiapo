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
use block_oksigeniatembiapo\local\services;

/**
 * «Your work»: one panel for any number of services, the alerts of all of them together, a fixed order, what does not fit
 * left out; the settings that fill themselves; and the block at the top of every dashboard, once. Pretend services and
 * example data.
 *
 * @package    block_oksigeniatembiapo
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_oksigeniatembiapo\local\board
 * @covers     \block_oksigeniatembiapo\local\placement
 * @covers     \block_oksigeniatembiapo\local\services
 * @covers     \block_oksigeniatembiapo\admin\setting_services
 */
#[\PHPUnit\Framework\Attributes\CoversClass(board::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(placement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(services::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\block_oksigeniatembiapo\admin\setting_services::class)]
final class board_test extends \advanced_testcase {
    /**
     * Pretend services: n plugins with one door each, the first two with alerts.
     *
     * @param int $n
     * @return array component => doors
     */
    protected function pretend(int $n): array {
        $raw = [];
        for ($i = 1; $i <= $n; $i++) {
            $component = 'local_pretend' . chr(96 + $i);
            $door = ['key' => $component, 'title' => 'Servicio ' . chr(64 + $i), 'url' => 'https://school.example/' . $i,
                'status' => $i . ' cosas', 'unknown' => ['ignored' => true]];
            if ($i <= 2) {
                $door['alerts'] = [['text' => 'Aviso de ' . $i, 'level' => $i === 1 ? 'today' : 'urgent']];
            }
            $raw[$component] = [$door];
        }
        return $raw;
    }

    /**
     * Any number of services: nothing, one wide door, a row, rows of four, compact.
     */
    public function test_any_number(): void {
        $expected = [0 => ['one', 1], 1 => ['one', 1], 2 => ['row', 2], 4 => ['row', 4], 7 => ['grid', 4],
            10 => ['compact', 4]];
        foreach ($expected as $n => [$layout, $cols]) {
            $board = board::build($this->pretend($n));
            $this->assertSame($n, $board['count'], "$n services");
            $this->assertSame($n > 0, $board['hasdoors']);
            $this->assertSame($layout, $board['layout'], "$n services");
            $this->assertSame($cols, $board['cols'], "$n services");
            $this->assertSame(min($n, 2), count($board['alerts']), "$n services");
        }
        // The alerts are told in the doors only as a number.
        $board = board::build($this->pretend(2));
        $this->assertSame([1, 1], array_column($board['doors'], 'alertcount'));
        $this->assertSame([false, true], array_column($board['doors'], 'urgent'));
        $this->assertArrayNotHasKey('alerts', $board['doors'][0]);
    }

    /**
     * To sort out: the urgent first, then by when they are due, then by the order of the doors; five in sight.
     */
    public function test_to_sort_out(): void {
        $board = board::build([
            'local_b' => [['title' => 'Biblioteca', 'url' => 'https://school.example/b', 'alerts' => [
                ['text' => 'Hoy sin plazo', 'level' => 'today'],
                ['text' => 'Urgente sin plazo', 'level' => 'urgent'],
                ['text' => 'Nivel inventado', 'level' => 'whenever'],
            ]]],
            'local_a' => [['title' => 'Admisiones', 'url' => 'https://school.example/a', 'alerts' => [
                ['text' => 'Urgente a las 12', 'level' => 'urgent', 'due' => 1200],
                ['text' => 'Urgente a las 10', 'level' => 'urgent', 'due' => 1000, 'url' => 'https://school.example/x'],
                ['text' => 'Hoy a las 9', 'level' => 'today', 'due' => 900],
                ['text' => 'Hoy sin plazo, A', 'level' => 'today'],
            ]]],
        ]);
        $this->assertSame(['Urgente a las 10', 'Urgente a las 12', 'Urgente sin plazo', 'Hoy a las 9', 'Hoy sin plazo, A',
            'Hoy sin plazo'], array_column($board['alerts'], 'text'));
        $this->assertSame('https://school.example/x', $board['alerts'][0]['url']);
        $this->assertSame('https://school.example/b', $board['alerts'][2]['url']);
        $this->assertSame('Biblioteca', $board['alerts'][2]['service']);
        $this->assertCount(5, $board['shown']);
        $this->assertTrue($board['hasmore']);
        $this->assertSame(1, $board['morecount']);
        $this->assertSame(get_string('more', 'block_oksigeniatembiapo', 1), $board['morelabel']);
        $this->assertSame('8.5', $board['tagwidth']);
    }

    /**
     * The column of the service's name: the design's width for short names, wider for a long one, and a ceiling past
     * which the name wraps.
     */
    public function test_tag_width(): void {
        $this->assertSame('8.5', board::tag_width(0));
        $this->assertSame('8.5', board::tag_width(10));
        $this->assertSame('10', board::tag_width(16));
        $this->assertSame('10.5', board::tag_width(17));
        $this->assertSame('13', board::tag_width(40));
        $board = board::build(['local_h' => [['title' => 'Horas de soporte', 'url' => 'https://school.example/h',
            'alerts' => [['text' => 'Una intervención por aprobar', 'level' => 'today']]]]]);
        $this->assertSame('10', $board['tagwidth']);
    }

    /**
     * A fixed order: the site's first, then by name; never the order they came in. What does not fit is left out.
     */
    public function test_order_and_tolerance(): void {
        $raw = [
            'local_z' => [['title' => 'Zeta', 'url' => 'https://school.example/z'], ['title' => 'Sin dirección'], 'not a door'],
            'local_m' => [['title' => '<b>Menú</b>', 'url' => 'https://school.example/m', 'actions' => [
                ['label' => 'Uno', 'url' => 'https://school.example/1'],
                ['text' => 'Dos', 'url' => 'https://school.example/2'],
                ['label' => 'Tres', 'url' => 'https://school.example/3'],
            ]]],
            'local_a' => [['title' => 'Avisos', 'url' => 'https://school.example/a']],
            'local_x' => 'nothing',
        ];
        $this->assertSame(['Avisos', 'Menú', 'Zeta'], array_column(board::build($raw)['doors'], 'title'));
        $this->assertSame(['Zeta', 'Avisos', 'Menú'], array_column(board::build($raw, ['local_z'])['doors'], 'title'));
        $menu = board::build($raw)['doors'][1];
        $this->assertSame(['Uno', 'Dos'], array_column($menu['actions'], 'label'));
        // The logo, from what the block was given.
        $board = board::build($raw, [], function ($component) {
            return 'https://school.example/' . $component . '.svg';
        });
        $this->assertSame('https://school.example/local_a.svg', $board['doors'][0]['icon']);
    }

    /**
     * The settings: the services hidden by the site do not take part; a plugin's logo is its own icon, or the block's.
     */
    public function test_settings_and_logo(): void {
        $this->resetAfterTest();
        services::save(['local_b', 'local_a'], ['local_c']);
        $this->assertSame(['order' => ['local_b', 'local_a'], 'hidden' => ['local_c']], services::settings());
        set_config('services', '{"order": ["Not a component", "local_a"], "hidden": 3}', 'block_oksigeniatembiapo');
        $this->assertSame(['order' => ['local_a'], 'hidden' => []], services::settings());
        // A block without services of its own: the list of the site is whatever takes part, each with its name.
        foreach (services::listed() as $service) {
            $this->assertNotSame('', $service['name']);
        }
        $this->assertStringContainsString('generic', board::icon('local_nothingthatexists'));
        $this->assertStringContainsString('qtype_truefalse/1/icon', board::icon('qtype_truefalse'));
    }

    /**
     * The setting of the services is written when Moodle applies its default, so it never comes back as a new setting;
     * what the site chose is kept.
     */
    public function test_setting_saved(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $setting = new \block_oksigeniatembiapo\admin\setting_services();
        unset_config('services', 'block_oksigeniatembiapo');
        $this->assertNull($setting->get_setting());
        $this->assertSame('', $setting->write_setting(''));
        $this->assertSame('', $setting->get_setting());
        services::save(['local_b'], []);
        $this->assertSame('', $setting->write_setting(''));
        $this->assertSame(['local_b'], services::settings()['order']);
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
        $this->assertSame(0, placement::everywhere());
        $DB->delete_records('block_instances', ['blockname' => 'oksigeniatembiapo',
            'parentcontextid' => \context_user::instance($user->id)->id]);
        $this->assertSame(1, placement::everywhere());
        $this->assertTrue($DB->record_exists('block_instances', ['blockname' => 'oksigeniatembiapo',
            'parentcontextid' => \context_user::instance($user->id)->id, 'subpagepattern' => (string) $own->id]));
        $this->assertSame(0, placement::everywhere());
    }
}
