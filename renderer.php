<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Letter grading version file
 *
 * @package    gradingform
 * @subpackage letter
 * @author     Charles Fulton
 * @copyright  2022 Lafayette College ITS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class gradingform_letter_renderer extends plugin_renderer_base {
    public function display_letter($letters, $itemids, $elementname = null, $value = null) {
        $options = array_combine($itemids, $letters);

        $html = html_writer::start_tag('div', array('id' => 'letter-{NAME}', 'class' => 'clearfix gradingform_letter'));
        $html .= html_writer::select($options, 'letter', $value);
        $html .= html_writer::end_tag('div');
        return str_replace('{NAME}', $elementname, $html);
    }
}
