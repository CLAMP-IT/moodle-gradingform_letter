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

require_once($CFG->dirroot . '/lib/formslib.php');

class gradingform_letter_editletter extends moodleform {
    public function definition() {
        $form = $this->_form;

        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        // Name.
        $form->addElement('text', 'name', get_string('name', 'gradingform_letter'), array('size'=>52));
        $form->addRule('name', get_string('required'), 'required');
        $form->setType('name', PARAM_TEXT);

        // description
        $options = gradingform_letter_controller::description_form_field_options($this->_customdata['context']);
        $form->addElement('editor', 'description_editor', get_string('description', 'gradingform_letter'), null, $options);
        $form->setType('description_editor', PARAM_RAW);

        // Letter scale completion status.
        $choices = array();
        $choices[gradingform_controller::DEFINITION_STATUS_DRAFT]    = html_writer::tag('span', get_string('statusdraft', 'core_grading'), array('class' => 'status draft'));
        $choices[gradingform_controller::DEFINITION_STATUS_READY]    = html_writer::tag('span', get_string('statusready', 'core_grading'), array('class' => 'status ready'));
        $form->addElement('select', 'status', get_string('scalestatus', 'gradingform_letter'), $choices)->freeze();

        // Scale fields.
        $repeatarray = [
            $form->createElement('text', 'letter', get_string('letter', 'gradingform_letter')),
            $form->createElement('text', 'value', get_string('value', 'gradingform_letter')),
            $form->createElement('hidden', 'itemid', 0),
		    $form->createElement('html', '<hr>'),
        ];

		$repeateloptions = [
		];

        $form->setType('letter', PARAM_TEXT);
        $form->setType('value', PARAM_INT);
        $form->setType('itemid', PARAM_INT);
        $this->repeat_elements(
            $repeatarray,
            10,
			$repeateloptions,
    		'option_repeats',
    		'option_add_fields',
			3,
			null,
			true
        );

        $buttonarray = array();
        $buttonarray[] = &$form->createElement('submit', 'saveletter', get_string('saveletter', 'gradingform_letter'));
        if ($this->_customdata['allowdraft']) {
            $buttonarray[] = &$form->createElement('submit', 'saveletterdraft', get_string('saveletterdraft', 'gradingform_letter'));
        }
        $editbutton = &$form->createElement('submit', 'editletter', ' ');
        $editbutton->freeze();
        $buttonarray[] = &$editbutton;
        $buttonarray[] = &$form->createElement('cancel');
        $form->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $form->closeHeaderBefore('buttonar');
    }

    /**
     * Check if there are changes in the letter scale and it is needed to ask user whether to
     * mark the current grades for re-grading. User may confirm re-grading and continue,
     * return to editing or cancel the changes
     *
     * @param gradingform_letter_controller $controller
     * @return boolean
     */
    public function need_confirm_regrading($controller) {
        $data = $this->get_data();
        if (isset($data->letter['regrade'])) {
            // we have already displayed the confirmation on the previous step
            return false;
        }
        if (!isset($data->saveletter) || !$data->saveletter) {
            return false;
        }
        if (!$controller->has_active_instances()) {
            // nothing to re-grade, confirmation not needed
            return false;
        }
        $changelevel = $controller->update_or_check_letter($data);
        if ($changelevel == 0) {
            // no changes in the letter scale, no confirmation needed
            return false;
        }

        $this->findButton('saveletter')->setValue(get_string('continue'));
        $el =& $this->findButton('editletter');
        $el->setValue(get_string('backtoediting', 'gradingform_letter'));
        $el->unfreeze();

        return true;
    }

    public function get_data() {
        $data = parent::get_data();
        if (!empty($data->saveletter)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_READY;
        } else if (!empty($data->saveletterdraft)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_DRAFT;
        }
        return $data;
    }
}
