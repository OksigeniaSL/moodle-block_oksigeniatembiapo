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
use block_oksigeniatembiapo\local\memory;
use block_oksigeniatembiapo\local\services;
use block_oksigeniatembiapo\privacy\provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * A service that does not answer: its doors stay for whoever had them, saying since when, and it never reads as nothing
 * waiting. Pretend services and example data.
 *
 * @package    block_oksigeniatembiapo
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_oksigeniatembiapo\local\board
 * @covers     \block_oksigeniatembiapo\local\memory
 * @covers     \block_oksigeniatembiapo\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(board::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(memory::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class silent_test extends \advanced_testcase {
    /**
     * A pretend service with one door.
     *
     * @param string $title
     * @param string[] $levels the levels of its alerts
     * @return callable
     */
    protected function answering(string $title, array $levels = []): callable {
        return fn(int $userid): array => [['key' => 'k' . $title, 'title' => $title, 'status' => '3 cosas',
            'url' => (new \moodle_url('/local/pretend/index.php', ['t' => $title]))->out(false),
            'alerts' => array_map(fn($level) => ['text' => 'Aviso de ' . $title, 'level' => $level], $levels)]];
    }

    /**
     * The ways a service fails.
     *
     * @return array[]
     */
    public static function failing_provider(): array {
        return [
            'it throws' => [function () {
                throw new \dml_exception('error');
            }],
            'an error of PHP' => [function () {
                return intdiv(1, 0);
            }],
            'not a list' => [fn() => 'nothing'],
            'null' => [fn() => null],
            'nothing that fits' => [fn() => [['title' => 'Sin dirección'], 'not a door']],
        ];
    }

    /**
     * It keeps its door, with its logo, in its place, saying since when; its alerts are not zero but unknown; the others
     * show the same.
     *
     * @dataProvider failing_provider
     * @param callable $failing
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('failing_provider')]
    public function test_the_door_stays(callable $failing): void {
        $this->resetAfterTest();
        $person = (int) $this->getDataGenerator()->create_user()->id;
        $canteen = $this->answering('Comedor', ['urgent', 'today']);
        $library = $this->answering('Biblioteca', ['urgent', 'today']);
        $asked = board::ask($person, [], ['local_canteen' => $canteen, 'local_library' => $library]);
        $this->assertSame(['local_canteen', 'local_library'], array_keys($asked['raw']));
        $this->assertSame([], $asked['silent']);

        $asked = board::ask($person, [], ['local_canteen' => $failing, 'local_library' => $library]);
        $this->assertDebuggingCalled();
        $this->assertSame(['local_library'], array_keys($asked['raw']));
        $this->assertSame(['local_canteen'], array_keys($asked['silent']));
        $board = board::build(
            $asked['raw'],
            ['local_canteen'],
            fn($component) => 'https://school.example/' . $component . '.svg',
            $asked['silent']
        );
        $this->assertSame(['Comedor', 'Biblioteca'], array_column($board['doors'], 'title'));
        $door = $board['doors'][0];
        $this->assertTrue($door['silent']);
        $this->assertFalse($board['doors'][1]['silent']);
        $this->assertSame('https://school.example/local_canteen.svg', $door['icon']);
        $this->assertSame((new \moodle_url('/local/pretend/index.php', ['t' => 'Comedor']))->out(false), $door['url']);
        $since = get_string(
            'silent_today',
            'block_oksigeniatembiapo',
            userdate($asked['silent']['local_canteen']['since'], get_string('strftimetime', 'langconfig'))
        );
        $this->assertSame($since, $door['status']);
        // Not a number: how many it has is not known.
        $this->assertSame([0, false, false], [$door['alertcount'], $door['hasalerts'], $door['urgent']]);
        $this->assertSame([], $door['actions']);
        // In the list, after the urgent of the others and before what is for today.
        $this->assertSame(['urgent', 'unknown', 'today'], array_column($board['alerts'], 'level'));
        $this->assertSame([false, true, false], array_column($board['alerts'], 'unknown'));
        $this->assertSame('Comedor', $board['alerts'][1]['service']);
        $this->assertSame($door['url'], $board['alerts'][1]['url']);
        $this->assertStringStartsWith($since, $board['alerts'][1]['text']);

        // It answers again: as always.
        $asked = board::ask($person, [], ['local_canteen' => $canteen, 'local_library' => $library]);
        $this->assertSame([], $asked['silent']);
    }

    /**
     * Only for whoever had a door there, and with nothing else on the dashboard too; a service the person no longer runs is
     * forgotten; one hidden or left out is not asked.
     */
    public function test_only_for_whoever_had_it(): void {
        $this->resetAfterTest();
        $person = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;
        $good = ['local_canteen' => $this->answering('Comedor')];
        $bad = ['local_canteen' => self::failing_provider()['it throws'][0]];
        board::ask($person, [], $good);
        board::ask($other, [], ['local_canteen' => fn() => []]);

        $asked = board::ask($other, [], $bad);
        $this->assertDebuggingCalled();
        $this->assertSame(['raw' => [], 'silent' => []], $asked);
        $this->assertFalse(board::build($asked['raw'], [], null, $asked['silent'])['hasdoors']);

        $asked = board::ask($person, [], $bad);
        $this->assertDebuggingCalled();
        $board = board::build($asked['raw'], [], null, $asked['silent']);
        $this->assertTrue($board['hasdoors']);
        $this->assertTrue($board['hasalerts']);
        $this->assertSame('one', $board['layout']);

        $this->assertSame(['raw' => [], 'silent' => []], board::ask($person, ['local_canteen'], $bad));
        services::save([], ['local_canteen']);
        $this->assertSame(['raw' => [], 'silent' => []], board::ask($person, [], $bad));
        services::save([], []);

        board::ask($person, [], ['local_canteen' => fn() => []]);
        $this->assertNull(memory::recall($person, 'local_canteen'));
        board::ask($person, [], $bad);
        $this->assertDebuggingCalled();
        $this->assertNull(memory::recall($person, 'local_canteen'));
    }

    /**
     * However many urgent there are, a service that does not answer is in sight, not behind «and N more».
     */
    public function test_always_in_sight(): void {
        $this->resetAfterTest();
        $board = board::build(
            ['local_library' => $this->answering('Biblioteca', array_fill(0, 7, 'urgent'))(1)],
            [],
            null,
            ['local_canteen' => ['doors' => [['key' => 'c', 'title' => 'Comedor', 'url' => 'https://school.example/c']],
                'since' => time() - 3 * DAYSECS]]
        );
        $this->assertSame(['urgent', 'urgent', 'urgent', 'urgent', 'urgent', 'unknown'], array_column($board['shown'], 'level'));
        $this->assertSame(2, $board['morecount']);
        $time = time() - 3 * DAYSECS;
        $this->assertSame(get_string('silent_day', 'block_oksigeniatembiapo', (object) [
            'day' => userdate($time, get_string('strftimedateshort', 'langconfig')),
            'time' => userdate($time, get_string('strftimetime', 'langconfig'))]), $board['doors'][1]['status']);
    }

    /**
     * What is kept: written only when it changes, or every ten minutes at most; with the addresses of the site without its
     * name, and never longer than a preference takes.
     */
    public function test_memory(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $person = (int) $this->getDataGenerator()->create_user()->id;
        $door = ['key' => 'local_canteen', 'title' => 'Comedor', 'url' => $CFG->wwwroot . '/local/canteen/index.php?c=1',
            'status' => 'ignored'];
        $now = 1800000000;
        memory::keep($person, 'local_canteen', [$door], $now);
        $value = $DB->get_field('user_preferences', 'value', ['userid' => $person, 'name' => memory::PREFIX . 'local_canteen']);
        $this->assertStringNotContainsString($CFG->wwwroot, $value);
        $this->assertStringNotContainsString('ignored', $value);
        memory::keep($person, 'local_canteen', [$door], $now + memory::REFRESH - 1);
        $this->assertSame($now, memory::recall($person, 'local_canteen')['since']);
        memory::keep($person, 'local_canteen', [$door], $now + memory::REFRESH);
        $kept = memory::recall($person, 'local_canteen');
        $this->assertSame($now + memory::REFRESH, $kept['since']);
        $this->assertSame($door['url'], $kept['doors'][0]['url']);
        memory::keep($person, 'local_canteen', [['title' => 'Cocina'] + $door], $now + memory::REFRESH + 1);
        $this->assertSame('Cocina', memory::recall($person, 'local_canteen')['doors'][0]['title']);
        // The one who is looking, from the session.
        $this->setUser($person);
        $this->assertSame('Cocina', memory::recall($person, 'local_canteen')['doors'][0]['title']);

        $many = [];
        for ($i = 1; $i <= 40; $i++) {
            $many[] = ['key' => 'k' . $i, 'title' => 'Sede número ' . $i, 'url' => 'https://school.example/sede/' . $i];
        }
        memory::keep($person, 'local_many', $many, $now);
        $kept = memory::recall($person, 'local_many')['doors'];
        $this->assertGreaterThan(10, count($kept));
        $this->assertSame('Sede número 1', $kept[0]['title']);
        // What cannot be read is as nothing kept.
        set_user_preference(memory::PREFIX . 'local_many', '{"t": "x", "d": 3}');
        $this->assertNull(memory::recall($person, 'local_many'));
        memory::keep($person, 'local_many', [], $now);
        memory::keep($person, 'local_canteen', [], $now);
        $this->assertNull(memory::recall($person, 'local_canteen'));
    }

    /**
     * What the panel says, rendered: a question mark instead of a number and the way in, and no number of its own.
     */
    public function test_rendered(): void {
        global $OUTPUT, $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/my/');
        $html = $OUTPUT->render_from_template('block_oksigeniatembiapo/board', board::build(
            ['local_library' => $this->answering('Biblioteca', ['today'])(1)],
            [],
            [board::class, 'icon'],
            ['local_canteen' => ['doors' => [['key' => 'c', 'title' => 'Comedor', 'url' => 'https://school.example/c']],
                'since' => time()]]
        ));
        $this->assertSame(1, substr_count($html, 'is-silent'));
        $this->assertSame(1, substr_count($html, 'tbp-mark-unknown'));
        $this->assertSame(1, substr_count($html, 'tbp-mark-today'));
        $this->assertStringContainsString('<span class="tbp-count" aria-hidden="true">?</span>', $html);
        $this->assertStringContainsString('<span class="tbp-count" aria-hidden="true">1</span>', $html);
        $this->assertStringContainsString(get_string('open', 'block_oksigeniatembiapo'), $html);
        $this->assertStringContainsString(get_string('silent_count', 'block_oksigeniatembiapo'), $html);
    }

    /**
     * The privacy of what is kept.
     */
    public function test_privacy(): void {
        $this->resetAfterTest();
        $person = (int) $this->getDataGenerator()->create_user()->id;
        $this->assertCount(1, provider::get_metadata(new collection('block_oksigeniatembiapo'))->get_collection());
        board::ask($person, [], ['local_canteen' => $this->answering('Comedor')]);
        set_user_preference('something_else', 'x', $person);
        provider::export_user_preferences($person);
        $exported = (array) writer::with_context(\context_system::instance())
            ->get_user_preferences('block_oksigeniatembiapo');
        $this->assertSame([memory::PREFIX . 'local_canteen'], array_keys($exported));
    }
}
