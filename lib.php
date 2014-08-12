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

    public function show() {
        global $DB, $CFG;

        $output = "";
        $assignmentid = $this->assignmentid;
        if ($assignmentid == 0) { return($output); } // disabling all assignments option

    // step one, find all enrolled users to course
    // step three, loop through users to find their results

    $coursecontext = context_course::instance($this->course->id);
    $users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0, $userfields = 'u.id,CONCAT(u.lastname, \' \', u.firstname) AS student,u.firstname,u.*', $orderby = 'u.id');
    $data = array();

    $rubric_array = array();
    // step 2, find any rubrics related to assignment
    $definitions = $DB->get_records_sql("select * from {grading_definitions} where areaid = ?", array($assignmentid));
    foreach($definitions as $def) {
        $criteria = $DB->get_records_sql("select * from {gradingform_rubric_criteria} where definitionid = ? order by sortorder", array($def->id));
        foreach($criteria as $crit) {
            $levels = $DB->get_records_sql("select * from {gradingform_rubric_levels} where criterionid = ?", array($crit->id));
            foreach($levels as $level) {
                $rubric_array[$crit->id][$level->id] = $level;
                $rubric_array[$crit->id]['crit_desc'] = $crit->description;
            } 
       }
    }

    $userroles = $DB->get_records('role_assignments', array('contextid' => $coursecontext->id));
    $rolenames = role_get_names($coursecontext, ROLENAME_ALIAS, true);
    $user_roles = array();
    foreach ($userroles as $userrole) {
        $user_roles[$userrole->userid] = $rolenames[$userrole->roleid];
    }

    foreach($users as $user) {
        if ($user_roles[$user->id] != "Student") {
            continue;
        } else {

        $query = "SELECT grf.id, gd.id as defid, ag.userid, ag.grade, grf.instanceid, grf.criterionid, grf.levelid, grf.remark ".
        " FROM {assign_grades} AS ag JOIN {grading_instances} as gin ON ag.id = gin.itemid".
        " JOIN {grading_definitions} AS gd ON (gd.id = gin.definitionid )".
        " JOIN {gradingform_rubric_fillings} AS grf ON (grf.instanceid = gin.id)".
        " WHERE gin.status = ? and ag.assignment = ? and ag.userid = ?";
        $query_array = array(1, $assignmentid, $user->id);
        $userdata = $DB->get_records_sql($query, $query_array);
            $data[$user->id] = array($user->student, $userdata);
        }
    }

	if (count($data)==0) {
            $output = get_string('err_norecords', 'gradereport_rubrics');
	} else {
            // Links for download

            $link_url = "index.php?id={$this->course->id}&amp;assignmentid={$this->assignmentid}&amp;format=";

            if ((!$this->csv)) {
                $output = '<ul class="rubrics-actions"><li><a href="'.$link_url.'csv">'.
                    get_string('csvdownload','gradereport_rubrics').'</a></li>
                    <li><a href="'.$link_url.'excelcsv">'.
                    get_string('excelcsvdownload','gradereport_rubrics').'</a></li></ul>';
            }

            // put data into table
            $output .= $this->display_table($data, $rubric_array);
        }

	echo $output;
    }

    public function display_table($data, $rubric_array) {
        global $DB, $CFG;

	    $csv_output = "";
        if (!$this->csv) {
        $output = html_writer::start_tag('div', array('class' => 'rubrics'));
        $table = new html_table();
    	$header_array = array();
    	$table->head = array("Student");
        foreach($rubric_array as $key=>$value) {
            $table->head[] = $rubric_array[$key]['crit_desc'];
        }
        $table->head[] = "Grade";
    	if ($this->assignmentid == 0) 
            $table->data = array();
            $table->data[] = new html_table_row();
            $sep=",";
            $line="\n";
        } else {
	    if ($this->excel) {
                //print chr(0xFF).chr(0xFE);
                $sep="\t".chr(0);
                $line="\n".chr(0);
            } else {
                $sep=",";
                $line="\n";
            }
        }

        foreach($data as $values) {
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = $values[0]; // student name
            //if ($this->csv) $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
            $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
            $row->cells[] = $cell;
            $this_grade = "-";
            if (count($values[1]) == 0) { // students with no marks
                foreach($rubric_array as $key=>$value) {
                    $cell = new html_table_cell();
                    $cell->text = "-";
                    $row->cells[] = $cell;
                    //if ($this->csv) $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
                    $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
                }
            }
	        foreach($values[1] as $value) { 
                $cell = new html_table_cell();
                $cell->text = $rubric_array[$value->criterionid][$value->levelid]->definition." - ";
                $cell->text .= round($rubric_array[$value->criterionid][$value->levelid]->score, 2)." - ".$value->remark;
                $row->cells[] = $cell;
		        $this_grade = round($value->grade, 2); // grade cell
                
                //if ($this->csv) $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
                $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
	        }

        $cell = new html_table_cell();
        $cell->text = $this_grade; // grade cell
        $row->cells[] = $cell;
        //if ($this->csv) $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
        $csv_output .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
	    $table->data[] = $row;
            //if ($this->csv) $csv_output .= $line;
            $csv_output .= $line;
        }

        //echo($csv_output);
        if ($this->csv) {
            //$output = $csv_output;
            $output = "Test data";
            echo("Test data");
        } else {
	    $output .= html_writer::table($table);
        }

	return $output;
    }

    function csv_quote($value, $excel) {
        if ($excel) {
            return core_text::convert('"'.str_replace('"',"'",$value).'"','UTF-8','UTF-16LE');
        } else {
            return '"'.str_replace('"',"'",$value).'"';
        }
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
