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
 * «Your work»: the figures in the state of each door in bold («312 menus today · 4 without a choice»), so they are read
 * at a glance. A plain script, not an AMD module, so it runs in Firefox 98 on Moodle 5.3 too; without it the state is the
 * same text, in one weight. It only splits text nodes: nothing is read as markup.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var FIGURE = /(\d+(?:[.,:]\d+)*)/;

    function figures(line) {
        // The dot between two figures stays with the one before, so no line of a narrow door starts with it.
        var parts = line.textContent.replace(/ · /g, '\u00a0· ').split(FIGURE);
        if (parts.length < 2) {
            line.textContent = parts[0];
            return;
        }
        var out = document.createDocumentFragment();
        parts.forEach(function(part, i) {
            if (part === '') {
                return;
            }
            if (i % 2) {
                var b = document.createElement('b');
                b.className = 'tbp-fig';
                b.textContent = part;
                out.appendChild(b);
            } else {
                out.appendChild(document.createTextNode(part));
            }
        });
        line.textContent = '';
        line.appendChild(out);
    }

    function run() {
        var lines = document.querySelectorAll('.block_oksigeniatembiapo .tbp-door-status:not(.tbp-done)');
        Array.prototype.forEach.call(lines, function(line) {
            figures(line);
            line.classList.add('tbp-done');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
