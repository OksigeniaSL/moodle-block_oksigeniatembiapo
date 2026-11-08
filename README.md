# Your work (Oksigenia Tembiapo)

A Moodle dashboard block for the people who run something: the canteen, the library, the office, the screens, whatever
the site has. One panel, whichever services and however many: above, **To sort out**, the alerts of all of them in one
list, the urgent first, each straight to where it is sorted out; below, **Your services**, a door into each, all the same
size. Whoever runs nothing sees nothing: the block does not show.

*Tembiapo* is «work» in Guarani.

- Moodle 4.5 to 6.0, PHP 8.1 or later.
- The block knows no product: it finds the plugins that take part. A new one fits in by adding one function, without a
  new version of the block.
- On install it goes to the top of the default dashboard and of the dashboards people have made their own, without
  touching anything else of theirs; across the whole width, also on a theme that lays the dashboard out in columns.
- Settings: the order of the services and the ones to hide (the list fills itself with what is installed; one that
  arrives later goes at the end), and «My courses» out of the main bar for whoever is enrolled in no course (on by
  default; it comes back as soon as they have one).
- It stores no personal data.

## The panel

- **To sort out** shows only when there is something. The urgent first, then those of today; within a level, the ones
  that are due sooner (`due`), then the order of the services. Each line says its service, with its logo. Five in sight,
  the rest behind «and N more».
- **Your services**: one door per service, with its logo, its name, a line of its state and how many alerts it has (red
  if one is urgent); its shortcuts below. An alert is never repeated as text in its door.
- **A fixed order**, never the one the plugins answer in: the one the site chose, and the rest by name.
- **The grid fits the number**: one wide door; two to four in a row; five to eight, rows of four; more, compact doors.
  Narrower screens, two per row and then one.

## How a plugin takes part — contract version 2

Any plugin can add its doors. It writes one function in its `lib.php`; without this block installed nobody calls it,
and the plugin works the same.

```php
/**
 * The doors of this plugin in «Your work», for one person.
 *
 * @param int $userid
 * @return array[] its doors; [] when the person runs nothing here
 */
function local_example_oksigenia_work(int $userid): array {
    return [[
        'key' => 'local_example',
        'title' => 'Canteen',
        'url' => (new moodle_url('/local/example/index.php'))->out(false),
        'status' => '312 menus today · 4 without a choice',
        'alerts' => [
            ['text' => '4 pupils without a menu for tomorrow; it closes at 10:30', 'url' => '…', 'level' => 'urgent',
                'due' => $closes],
            ['text' => '3 books are due today', 'url' => '…', 'level' => 'today'],
        ],
        'actions' => [['label' => 'Today\'s menu', 'url' => '…']],
    ]];
}
```

| Field | | What it is |
|---|---|---|
| `key` | yes | Unique across the site: the component, or the component and something of its own (`local_example-3`) when a plugin gives several doors. |
| `title` | yes | The short name of the service, as the person knows it. |
| `url` | yes | The door: the main page of the service for this person. Once they go in, they are inside the service. |
| `status` | | One line with figures: what the service is like now. |
| `alerts` | | What is waiting to be sorted out, each `['text', 'url', 'level', 'due']`: `level` is `urgent` (it cannot wait) or `today` (it is for today); `due`, optional, the Unix time it is due by, to put the sooner first. Empty when there is nothing; never «all fine». Without its own `url`, an alert goes to the door. |
| `actions` | | Up to two shortcuts inside the service, each `['label', 'url']`. |

**The logo** of each door is the plugin's own icon, `pix/icon.svg` (or `pix/icon.png`), the one Moodle shows for it, in
the colours of its brand: the block does not paint it over. A plugin without one gets the block's neutral one. The `icon`
field of contract version 1 is ignored.

The rules, the same for every plugin:

- **Only with permission.** A person who runs nothing in the plugin gets `[]`.
- **Cheap.** It runs on every load of the dashboard: under 50 ms, with a cache of its own (a minute is enough). A slower
  one is noted for developers.
- **Generic texts**, by what the permission lets the person do, without the name of any centre.
- **Plain text** in every field: the block takes out any markup. Dates and figures already written for the person.
- **Tolerant both ways.** The block ignores the fields it does not know and copes with the optional ones missing; a door
  without a title or an address is left out; a function that throws is left out and logged for developers, and the other
  services show the same.

### Trying it

The block builds the panel from what the plugins return, so a plugin can check its doors in a PHPUnit test of its own:

```php
$board = \block_oksigeniatembiapo\local\board::build(['local_example' => local_example_oksigenia_work($userid)]);
$this->assertTrue($board['hasdoors']);
```

On a site, the block's settings list every plugin that takes part.

### Versions of the contract

| Version | Since | Change |
|---|---|---|
| 2 | 0.2.0 | `due` in the alerts; the logo is the plugin's `pix/icon`, and the `icon` field is ignored. |
| 1 | 0.1.0 | `key`, `title`, `url`, `status`, `alerts` (`text`, `url`, `level`), `actions`, `icon`. |

A change that a plugin of the previous version would not survive gets a new version number and is agreed first.

## Development

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_oksigeniatembiapo_testsuite
```

## Licence

GNU GPL v3 or later. © 2026 Oksigenia SL.
