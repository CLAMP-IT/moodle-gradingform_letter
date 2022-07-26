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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->dirroot . '/grade/grading/form/letter/edit_form.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$areaid = required_param('areaid', PARAM_INT);
$manager = get_grading_manager($areaid);
list($context, $course, $cm) = get_context_info_array($manager->get_context()->id);

require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

$controller = $manager->get_controller('letter');
$PAGE->set_url(new moodle_url('/grade/grading/form/letter/edit.php', array('areaid' => $areaid)));
$PAGE->set_title(get_string('defineletter', 'gradingform_letter'));
$PAGE->set_heading(get_string('defineletter', 'gradingform_letter'));

$mform = new gradingform_letter_editletter(null, array('areaid' => $areaid, 'context' => $context, 'allowdraft' => !$controller->has_active_instances()), 'post', '', array('class' => 'gradingform_letter_editform'));
$data = $controller->get_definition_for_editing(true);
$returnurl = optional_param('returnurl', $manager->get_management_url(), PARAM_LOCALURL);
$data->returnurl = $returnurl;
$mform->set_data($data);
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->is_submitted() && $mform->is_validated() && !$mform->need_confirm_regrading($controller)) {
    // everything ok, validated, re-grading confirmed if needed. Make changes to the letter url
    $controller->update_definition($mform->get_data());
    redirect($returnurl);
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
