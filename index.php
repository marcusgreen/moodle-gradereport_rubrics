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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    grade_report_rubrics
 * @author     Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include('../../../config.php');
require_once($CFG->libdir .'/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/report/rubrics/lib.php');
require_once("select_form.php");

$courseid = required_param('id', PARAM_INT);// course id

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

$PAGE->set_url(new moodle_url('/grade/report/rubrics/index.php', array('id'=>$courseid)));

require_login($courseid);
$PAGE->set_pagelayout('report');

$context = context_course::instance($course->id);

require_capability('gradereport/rubrics:view', $context);

// Set up the form.
$mform = new report_rubrics_select_form(null, array('courseid' => $courseid));

//$mform->set_data();

// Set up some default info.
$assignmentid = 0;

if ($mform->is_cancelled()) {
}
// Did we get anything from the form?
else if ($formdata = $mform->get_data()) {
    // Get the users rubrics.
    $assignmentid = $formdata->assignmentid;
}

print_grade_page_head($COURSE->id, 'report', 'rubrics',
                      get_string('pluginname', 'gradereport_rubrics') .
                      $OUTPUT->help_icon('pluginname', 'gradereport_rubrics'));

// Display the form.
$mform->display();
echo("Selected assignment id is ".$assignmentid);

grade_regrade_final_grades($courseid);//first make sure we have proper final grades

$gpr = new grade_plugin_return(array('test'=>'test', 'type'=>'report', 'plugin'=>'grader', 'courseid'=>$courseid, 'assignmentid'=>$assignmentid));// return tracking object
$report = new grade_report_rubrics($courseid, $gpr, $context);// Initialise the grader report object

$report->show();

echo $OUTPUT->footer();
