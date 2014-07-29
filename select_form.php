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
 *
 * @package    report_rubrics
 * @copyright  2014 Learning Technology Services, www.lts.ie - Lead Developer: Karen Holland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class report_rubrics_select_form extends moodleform {

   public function definition() {
        global $CFG, $DB;

	$assignments = $DB->get_records_sql('SELECT asg.id AS assignmentid, asg.name AS assignment FROM {assign} AS asg JOIN {course} AS crs ON crs.id = asg.course JOIN {grading_areas} AS gra ON asg.id = gra.id WHERE asg.course = ? and gra.activemethod = ?', array($this->_customdata['courseid'], 'rubric'));

	$form_array = array(0=>'All');

	foreach($assignments as $item) {
		$form_array[$item->assignmentid] = $item->assignment;
	}

        $mform =& $this->_form;
        //$mform->addElement ('select', 'assignmentid', get_string('assignmentidtype', 'report_rubrics'), $assignments);
        $mform->addElement ('select', 'assignmentid', "Select assignment", $form_array);
        $mform->setType('assignmentid', PARAM_INT);
	$mform->getElement('assignmentid')->setSelected(0);
	$mform->addElement('hidden', 'id', $this->_customdata['courseid']);
	$mform->setType('id', PARAM_INT);
        $this->add_action_buttons(false, 'Go');
    }
}
