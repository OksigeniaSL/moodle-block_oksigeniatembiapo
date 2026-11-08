# Your work (Oksigenia Tembiapo)

A Moodle dashboard block for the people who run something: the canteen, the library, the office, the screens. At the
top of their dashboard they see first what is waiting to be sorted out across every service, the urgent first, each
alert straight to where it is sorted out; and below, a door into each service, with a line of its state. Whoever runs
nothing sees nothing: the block does not show.

*Tembiapo* is «work» in Guarani.

- Moodle 4.5 to 6.0, PHP 8.1 or later.
- On install it goes to the top of the default dashboard and of the dashboards people have made their own, without
  touching anything else of theirs.
- Optional, on by default: «My courses» leaves the main bar for whoever is enrolled in no course, who would find an
  empty page there; it comes back as soon as they have one.
- It stores no personal data.

## How a plugin takes part

Any plugin can add its door. It writes one function in its `lib.php`; without this block installed nobody calls it,
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
            ['text' => '4 pupils without a menu for tomorrow; it closes at 10:30', 'url' => '…', 'level' => 'urgent'],
            ['text' => '3 books are due today', 'url' => '…', 'level' => 'today'],
        ],
        'actions' => [['label' => 'Today\'s menu', 'url' => '…']],
        'icon' => '…',
    ]];
}
```

| Field | | What it is |
|---|---|---|
| `key` | yes | Unique across the site: the component, or the component and something of its own (`local_example-3`) when a plugin gives several doors. |
| `title` | yes | The short name of the service, as the person knows it. |
| `url` | yes | The door: the main page of the service for this person. Once they go in, they are inside the service. |
| `status` | | One line with figures: what the service is like now. |
| `alerts` | | What is waiting to be sorted out, each `['text', 'url', 'level']`: `urgent` (it cannot wait) or `today` (it is for today). Empty when there is nothing; never «all fine». Without its own `url`, an alert goes to the door. |
| `actions` | | Up to two shortcuts inside the service, each `['label', 'url']`. |
| `icon` | | The address of an image, square, read at 40 px (an SVG is best). Without it, the first letter of the title. |

The rules, the same for every plugin:

- **Only with permission.** A person who runs nothing in the plugin gets `[]`.
- **Cheap.** It runs on every load of the dashboard: under 50 ms, with a cache of its own (a minute is enough).
- **Generic texts**, by what the permission lets the person do, without the name of any centre.
- **Plain text** in every field: the block takes out any markup. Dates and figures already written for the person.
- A function that throws is left out and logged for developers: it never breaks the dashboard.

The block gathers the alerts of every door at the top, the urgent first and then those of today, in the order each
plugin gave them; and the doors below, by title.

## Development

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_oksigeniatembiapo_testsuite
```

## Licence

GNU GPL v3 or later. © 2026 Oksigenia SL.
