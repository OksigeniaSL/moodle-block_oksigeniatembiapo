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

namespace block_oksigeniatembiapo\admin;

use block_oksigeniatembiapo\local\services;

/**
 * The services of «Your work» in the settings: each installed one with its place and whether it shows. The list fills
 * itself with what is installed.
 *
 * @package    block_oksigeniatembiapo
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_services extends \admin_setting {
    /**
     * The setting, under the block's settings.
     */
    public function __construct() {
        $this->plugin = 'block_oksigeniatembiapo';
        parent::__construct(
            'block_oksigeniatembiapo/services',
            get_string('services', 'block_oksigeniatembiapo'),
            get_string('services_desc', 'block_oksigeniatembiapo'),
            ''
        );
    }

    /**
     * What is kept.
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Keeps the order and the hidden services from the form.
     *
     * @param mixed $data {place: component => number, hide: component => 1}
     * @return string an error, or ''
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return '';
        }
        $places = is_array($data['place'] ?? null) ? $data['place'] : [];
        $hide = is_array($data['hide'] ?? null) ? $data['hide'] : [];
        $known = array_column(services::listed(), 'component');
        $order = [];
        foreach ($known as $i => $component) {
            $order[$component] = [isset($places[$component]) ? (int) $places[$component] : $i + 1, $i];
        }
        asort($order);
        $hidden = array_values(array_filter($known, function ($component) use ($hide) {
            return !empty($hide[$component]);
        }));
        services::save(array_keys($order), $hidden);
        return '';
    }

    /**
     * The list, with a place and a box to hide each.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        $listed = services::listed();
        $c = 'block_oksigeniatembiapo';
        $name = $this->get_full_name();
        if (!$listed) {
            $html = \html_writer::tag('p', get_string('services_none', $c));
        } else {
            $rows = '';
            foreach ($listed as $i => $service) {
                $component = $service['component'];
                $id = $this->get_id() . '_' . $component;
                $place = \html_writer::empty_tag('input', [
                    'type' => 'number',
                    'min' => 1,
                    'max' => count($listed),
                    'class' => 'form-control',
                    'style' => 'width: 5rem',
                    'name' => $name . '[place][' . $component . ']',
                    'value' => $i + 1,
                    'aria-label' => get_string('services_place', $c, $service['name']),
                ]);
                $who = s($service['name']) . ' ' . \html_writer::tag('small', s($component), ['class' => 'text-muted']);
                $box = ['type' => 'checkbox', 'value' => 1, 'id' => $id, 'class' => 'form-check-input',
                    'name' => $name . '[hide][' . $component . ']'];
                if ($service['hidden']) {
                    $box['checked'] = 'checked';
                }
                $hide = \html_writer::empty_tag('input', $box) . ' ' .
                    \html_writer::label(get_string('services_hide', $c), $id, false, ['class' => 'form-check-label']);
                $rows .= \html_writer::tag('tr', \html_writer::tag('td', $place) . \html_writer::tag('td', $who) .
                    \html_writer::tag('td', $hide));
            }
            $head = \html_writer::tag('th', get_string('services_position', $c)) .
                \html_writer::tag('th', get_string('services_name', $c)) . \html_writer::tag('th', '');
            $table = \html_writer::tag('thead', \html_writer::tag('tr', $head)) . \html_writer::tag('tbody', $rows);
            $sent = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name . '[sent]', 'value' => 1]);
            $html = \html_writer::tag('table', $table, ['class' => 'table table-sm']) . $sent;
        }
        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '', null, $query);
    }
}
