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

require_once($CFG->dirroot.'/grade/grading/form/lib.php');

class gradingform_letter_controller extends gradingform_controller {
    /**
     * Returns the HTML code displaying the preview of the letter grading form
     *
     * @throws coding_exception
     * @param moodle_page $page the target page
     * @return string
     */
    public function render_preview(moodle_page $page) {
        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        $output = $this->get_renderer($page);
        $options = $this->get_options();
        $letters = '';
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
        } else if (!empty($options['alwaysshowdefinition'])) {
        }

        return $letters;
    } 

    /**
     * Deletes the letter definition and all the associated information
     */
    protected function delete_plugin_definition() {
        global $DB;
	}

    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     *
     * @return stdClass
     */
    public function get_definition_for_editing() {
        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if ($definition) {
            foreach (array('id', 'name', 'description', 'descriptionformat', 'status') as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor($properties, 'description', $options, $this->get_context(),
                'grading', 'description', $definition->id);
        }

        $properties->letter = $definition->letter ?? array();
        $properties->value = $definition->value ?? array();
        $properties->itemid = $definition->itemid ?? array();
        return $properties;
    }

    /**
     * Options for displaying the letter scale description field in the form
     *
     * @param object $context
     * @return array options for the form description field
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return array(
            'maxfiles' => -1,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes),
            'context'  => $context,
        );
    }

    /**
     * Returns the letter plugin renderer
     *
     * @param moodle_page $page the target page
     * @return gradingform_letter_renderer
     */
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('gradingform_'. $this->get_method_name());
    }

    /**
     * Returns the default options for the letter display
     *
     * @return array
     */
    public static function get_default_options() {
        $options = [];
        return $options;
    }

    /**
     * Gets the options of this letter definition, fills the missing options with default values
     *
     * @return array
     */
    public function get_options() {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $thisoptions = json_decode($this->definition->options);
            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
        }
        return $options;
    }

    /**
     * Saves the letter definition into the database
     *
     * @see parent::update_definition()
     * @param stdClass $newdefinition letter definition data as coming from gradingform_letter_editletter::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {
        $this->update_or_check_letter($newdefinition, $usermodified, true);
        if (isset($newdefinition->letter['regrade']) && $newdefinition->letter['regrade']) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Either saves the letter definition into the database or check if it has been changed.
     * Returns the level of changes:
     * 0 - no changes
     * 1 - only texts changed, students probably do not require re-grading
     * 2 - added but did not remove scales, students still may not require re-grading
     * 3 - modified the value of a scale, students require regrading
     * 4 - removed scales - students require re-grading and not all students may be re-graded automatically
     *
     * @param stdClass $newdefinition letter definition data as coming from gradingform_letter_editletter::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @param boolean $doupdate if true actually updates DB, otherwise performs a check
     *
     * @return int
     */
    public function update_or_check_letter(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        if ($this->definition === false) {
            if (!$doupdate) {
                return 4;
            }
            // if definition does not exist yet, create a blank one
            // (we need id to save files embedded in description)
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }
 
        $currentdefinition = $this->get_definition(true);

        $haschanges = array();
        $itemsdata = array();
        $scalecount = $newdefinition->option_repeats;
        for($i=0; $i< $scalecount; $i++) {
            foreach (array('itemid', 'letter', 'value') as $fieldname) {
                $itemsdata[$i][$fieldname] = $newdefinition->$fieldname[$i];
            }
        }

        $currentitems = array();
        for($i=0; $i < count($currentdefinition->itemid); $i++) {
            foreach (array('itemid', 'letter', 'value') as $fieldname) {
                $currentitems[$currentdefinition->itemid[$i]][$fieldname] = $currentdefinition->$fieldname[$i];
            }
        }

        foreach($itemsdata as $item) {
            // Update or delete existing letter.
            if(isset($item['itemid']) && $item['itemid'] != 0) {
                if(isset($item['letter']) && !empty($item['letter']) && isset($item['value'])) {
                    if($item['value'] != $currentitems[$item['itemid']]['value']) {
                        $data = array(
                            'id' => $item['itemid'],
                            'letter' => $item['letter'],
                            'value' => $item['value']
                        );
                        if($doupdate) {
                            $DB->update_record('gradingform_letter_items', $data);
                        }
                        $haschanges[3] = true;
                    }
                } else {
                    // Delete.
                    if($doupdate) {
                        $DB->delete_records('gradingform_letter_items', array('id' => $item['itemid']));
                    }
                    $haschanges[4] = true;
                }
            } else {
                // New item.
                if(isset($item['letter']) && !empty($item['letter']) && isset($item['value'])) {
                    $data = array(
                        'definitionid' => $this->definition->id,
                        'letter' => $item['letter'],
                        'value' => $item['value'],
                    );
                    if($doupdate) {
                        $DB->insert_record('gradingform_letter_items', $data);
                    }
                    $haschanges[2] = true;
                }
            }
        }

        foreach (array('status', 'description', 'descriptionformat', 'name') as $key) {
            if (isset($newdefinition->$key) && $newdefinition->$key != $this->definition->$key) {
                $haschanges[1] = true;
            }
        }
        if ($usermodified && $usermodified != $this->definition->usermodified) {
            $haschanges[1] = true;
        }
        if (!count($haschanges)) {
            return 0;
        }

        if ($doupdate) {
            parent::update_definition($newdefinition, $usermodified);
            $this->load_definition();
        }
        // return the maximum level of changes
        $changelevels = array_keys($haschanges);
        sort($changelevels);
        return array_pop($changelevels);
    }

    /**
     * Loads the letter form definition if it exists
     *
     * There is a new array called 'scales' appended to the list of parent's definition properties.
     */
    protected function load_definition() {
        global $DB;
        $sql = "SELECT gd.*,
                       cli.id AS cliitemid, cli.value AS clivalue, cli.letter AS cliletter
                  FROM {grading_definitions} gd
             LEFT JOIN {gradingform_letter_items} cli ON (cli.definitionid = gd.id)
                 WHERE gd.areaid = :areaid AND gd.method = :method
              ORDER BY cli.value DESC";
        $params = array('areaid' => $this->areaid, 'method' => $this->get_method_name());
        $rs = $DB->get_recordset_sql($sql, $params);
        $this->definition = false;

        foreach ($rs as $record) {
            // pick the common definition data
            if ($this->definition === false) {
                $this->definition = new stdClass();
                foreach (array('id', 'name', 'description', 'descriptionformat', 'status', 'copiedfromid',
                             'timecreated', 'usercreated', 'timemodified', 'usermodified', 'timecopied', 'options') as $fieldname) {
                    $this->definition->$fieldname = $record->$fieldname;
                }
                $this->definition->letter = array();
                $this->definition->value = array();
                $this->definition->itemid = array();
            }
            // pick the items data
            if (!empty($record->cliitemid)) {
                foreach (array('itemid', 'letter', 'value') as $fieldname) {
                    $value = $record->{'cli'.$fieldname};
                    if ($fieldname == 'value') {
                        $value = (float)$value; // To prevent display like 1.00000
                    }
                    array_push($this->definition->$fieldname, $value);
                }
            }
        }
        $rs->close();
    }
}

class gradingform_letter_instance extends gradingform_instance {
    protected $scale;

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        global $DB;

        parent::cancel();
        $DB->delete_records('gradingform_letter_fills', array('instanceid' => $this->get_id()));
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
/*        if (!$gradingformelement->_flagFrozen) {
            $mode = gradingform_letter_controller::DISPLAY_EVAL;
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $mode = gradingform_letter_controller::DISPLAY_EVAL_FROZEN;
            } else {
                $mode = gradingform_letter_controller::DISPLAY_REVIEW;
            }
        }*/
        $letters = $this->get_controller()->get_definition()->letter;
        $itemids = $this->get_controller()->get_definition()->itemid;
        $value = $gradingformelement->getValue();
        $html = '';
        if ($value === null) {
            $value = $this->get_letter_filling();
        } else if (!$this->validate_grading_element($value)) {
            $html .= html_writer::tag('div', get_string('letternotcompleted', 'gradingform_letter'), array('class' => 'gradingform_letter-error'));
        }
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::tag('div', get_string('needregrademessage', 'gradingform_letter'), array('class' => 'gradingform_letter-regrade'));
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_letter_filling();
            /*foreach ($curfilling['groups'] as $groupid => $group) {
                foreach ($group['items'] as $itemid => $item)
                    // the saved checked status
                    $value['groups'][$groupid]['items'][$itemid]['savedchecked'] = !empty($item['checked']);
                    $newremark = null;
                    $newchecked = null;
                    if (isset($value['groups'][$groupid]['items'][$itemid]['remark'])) $newremark = $value['groups'][$groupid]['items'][$itemid]['remark'];
                    if (isset($value['groups'][$groupid]['items'][$itemid]['id'])) $newchecked = !empty($value['groups'][$groupid]['items'][$itemid]['id']);
                    if ($newchecked != !empty($item['checked']) || $newremark != $item['remark']) {
                        $haschanges = true;
                }
            }*/
        }
        if ($this->get_data('isrestored') && $haschanges) {
            $html .= html_writer::tag('div', get_string('restoredfromdraft', 'gradingform_letter'), array('class' => 'gradingform_letter-restored'));
        }

        $html .= html_writer::tag('div', $this->get_controller()->get_formatted_description(), array('class' => 'gradingform_letter-description clearfix'));
        $html .= $this->get_controller()->get_renderer($page)->display_letter($letters, $itemids, $gradingformelement->getName(), $value);
        return $html;
    }

     /**
     * Retrieves from DB and returns the data how this letter was filled
     *
     * @param boolean $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_letter_filling($force = false) {
        global $DB;

        if ($this->letter === null || $force) {
            $record = $DB->get_record('gradingform_letter_fills', array('instanceid' => $this->get_id()));
        }
        return (array)$this->letter;
    }

    /**
     * Calculates the grade to be pushed to the gradebook
     *
     * @return int the valid grade from $this->get_controller()->get_grade_range()
     */
    public function get_grade() {
        $grade = $this->get_letter_filling();


        return 93;
    }

    public function update($data) {
        global $DB;

        $currentgrade = $this->get_letter_filling();
        parent::update($data);
 
        if (!array_key_exists('itemid', $currentgrade)) {
            $newrecord = array('instanceid' => $this->get_id(),
                                       'itemid' => $data, 'remarkformat' => FORMAT_MOODLE); 
            $DB->insert_record('gradingform_letter_fills', $newrecord);
        } else {
        }
        $this->get_letter_filling(true);
    }
}
