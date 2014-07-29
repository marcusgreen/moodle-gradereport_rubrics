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
 * @package    grade_report_rubrics
 * @copyright  2014 Learning Technology Services, www.lts.ie - Lead Developer: Karen Holland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/grade/report/lib.php');

class grade_report_rubrics extends grade_report {


    function __construct($courseid, $gpr, $context, $page=null) {
        parent::__construct($courseid, $gpr, $context, $page);
        $this->course_grade_item = grade_item::fetch_course_item($this->courseid);
    }

    function process_data($data){
    }

    function process_action($target, $action){
    }

    public function show($assignmentid) {
        global $DB, $CFG;

	$assignmentsql = ($assignmentid == 0)?"asg.id AS assignmentid, asg.name AS assignment, ":"";

        $query = "SELECT grf.id as rubricid, {$assignmentsql}stu.id as studentid, CONCAT(stu.lastname, ' ', stu.firstname) AS student".
", grc.description, grl.definition, grl.score, grf.remark, CONCAT(rubm.lastname, ' '".
", rubm.firstname) AS Rater, ROUND(grg.finalgrade,2) AS Finalgrade".
", FROM_UNIXTIME(grg.timemodified) AS FinalGrade_modified".
" FROM {course} AS crs JOIN {course_modules} AS cm ON crs.id = cm.course".
" JOIN {assign} AS asg ON asg.id = cm.instance JOIN {context} AS c ON".
" cm.id = c.instanceid JOIN {grading_areas} AS ga ON c.id=ga.contextid".
" JOIN {grading_definitions} AS gd ON ga.id = gd.areaid JOIN {gradingform_rubric_criteria}".
" AS grc ON (grc.definitionid = gd.id) JOIN {gradingform_rubric_levels}".
" AS grl ON (grl.criterionid = grc.id) JOIN {grading_instances} AS gin ON".
" gin.definitionid = gd.id JOIN {assign_grades} AS ag ON ag.id = gin.itemid".
" JOIN {user} AS rubm ON rubm.id = gin.raterid JOIN {gradingform_rubric_fillings}".
" AS grf ON ((grf.instanceid = gin.id) AND (grf.criterionid = grc.id) AND".
" (grf.levelid = grl.id)) JOIN {grade_items} AS grit ON ((grit.courseid = crs.id)".
" AND (grit.itemmodule = 'assign') AND (grit.iteminstance = asg.id)) JOIN".
" {grade_grades} AS grg ON (grg.itemid = grit.id) JOIN {user} AS stu ON".
" ((stu.id = ag.userid) AND (stu.id = grg.userid)) JOIN {user} AS m ON".
" m.id = grg.usermodified WHERE gin.status = ? and crs.id = ?";
        $query_array = array(1, $this->course->id);        
        if ($assignmentid!=0) {
            $query .= " and asg.id = ?";        
            $query_array[] = $assignmentid;
        }

        $data = $DB->get_records_sql($query, $query_array);

        // put data into table
        $output = $this->display_table($data);
	echo $output;
    }

    public function display_table($data) {
        global $DB, $CFG;

        $output = html_writer::start_tag('div', array('class' => 'rubrics'));
        $table = new html_table();
        //$table->head = array();
	$table->head = array(1,2,3,4,5);
        $table->data = array();
        $table->data[] = new html_table_row();

        foreach($data as $key) {
            $row = new html_table_row();
	    foreach($key as $value) {
                $cell = new html_table_cell();
		$cell->text = $value;
		$row->cells[] = $cell;
	    }
	    $table->data[] = $row;
        }

	$output .= html_writer::table($table);

	return $output;
    }

    private function get_moodle_grades() {
        global $DB, $CFG;

        $grades = $DB->get_records('grade_grades', array('itemid' => $this->course_grade_item->id), 'userid', 'userid, finalgrade');
        if(!is_array($grades)) {
            $grades = array();
        }

        $this->moodle_grades = array();

        if ($this->course_grade_item->gradetype == GRADE_TYPE_SCALE) {
            $pg_scale = new grade_scale(array('id' => $CFG->grade_report_rubrics_scale));
            $scale_items = $pg_scale->load_items();
            foreach ($this->moodle_students as $st)  {
                if (isset($grades[$st->id])) {
                    $fg = (int)$grades[$st->id]->finalgrade;
                    if(isset($scale_items[$fg-1])) {
                        $this->moodle_grades[$st->id] = $scale_items[$fg-1];
                    } else {
                        $this->moodle_grades[$st->id] = null;
                    }
                } else {
                    $this->moodle_grades[$st->id] = null;
                }
            }
        } else {
            foreach ($this->moodle_students as $st)  {
                if (isset($grades[$st->id])) {
                    $this->moodle_grades[$st->id] = grade_format_gradevalue($grades[$st->id]->finalgrade,
                                                                        $this->course_grade_item, true,
                                                                        $this->course_grade_item->get_displaytype(), null);
                } else {
                    $this->moodle_grades[$st->id] = null;
                }
            }
        }
    }
}
