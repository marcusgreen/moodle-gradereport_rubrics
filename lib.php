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

    public $output;

    public function __construct($courseid, $gpr, $context, $page=null) {
        parent::__construct($courseid, $gpr, $context, $page);
        $this->course_grade_item = grade_item::fetch_course_item($this->courseid);
    }

    public function process_data($data) {
    }

    public function process_action($target, $action) {
    }

    public function show() {
        global $DB, $CFG;

        $output = "";
        $assignmentid = $this->assignmentid;
        if ($assignmentid == 0) {
            return($output);
        } // Disabling all assignments option.

        // Step one, find all enrolled users to course.

        $coursecontext = context_course::instance($this->course->id);
        $users = get_enrolled_users($coursecontext, $withcapability = '', $groupid = 0,
            $userfields = 'u.id,CONCAT(u.lastname, \' \', u.firstname) AS student,u.firstname,u.*', $orderby = 'u.id');
        $data = array();

        $rubricarray = array();

        // Step 2, find any rubrics related to assignment.
        $definitions = $DB->get_records_sql("select * from {grading_definitions} where areaid = ?", array($assignmentid));
        foreach ($definitions as $def) {
            $criteria = $DB->get_records_sql("select * from {gradingform_rubric_criteria}".
                " where definitionid = ? order by sortorder", array($def->id));
            foreach ($criteria as $crit) {
                $levels = $DB->get_records_sql("select * from {gradingform_rubric_levels} where criterionid = ?", array($crit->id));
                foreach ($levels as $level) {
                    $rubricarray[$crit->id][$level->id] = $level;
                    $rubricarray[$crit->id]['crit_desc'] = $crit->description;
                }
            }
        }

        $roleassignments = $DB->get_records('role_assignments', array('contextid' => $coursecontext->id));
        $rolenames = role_get_names($coursecontext, ROLENAME_ALIAS, true);
        $userroles = array();
        foreach ($roleassignments as $role) {
            $userroles[$role->userid] = $rolenames[$role->roleid];
        }

        foreach ($users as $user) {
            if ($userroles[$user->id] != "Student") {
                continue;
            } else {
                $query = "SELECT grf.id, gd.id as defid, ag.userid, ag.grade, grf.instanceid,".
                    " grf.criterionid, grf.levelid, grf.remark".
                    " FROM {assign_grades} ag".
                    " JOIN {grading_instances} gin".
                      " ON ag.id = gin.itemid".
                    " JOIN {grading_definitions} gd".
                      " ON (gd.id = gin.definitionid )".
                    " JOIN {gradingform_rubric_fillings} grf".
                      " ON (grf.instanceid = gin.id)".
                    " WHERE gin.status = ? and ag.assignment = ? and ag.userid = ?";

                $queryarray = array(1, $assignmentid, $user->id);
                $userdata = $DB->get_records_sql($query, $queryarray);
                $data[$user->id] = array($user->student, $userdata);
            }
        }

        if (count($data) == 0) {
            $output = get_string('err_norecords', 'gradereport_rubrics');
        } else {
            // Links for download.

            $linkurl = "index.php?id={$this->course->id}&amp;assignmentid={$this->assignmentid}&amp;".
                "displaylevel={$this->displaylevel}&amp;displayremark={$this->displayremark}&amp;format=";

            if ((!$this->csv)) {
                $output = '<ul class="rubrics-actions"><li><a href="'.$linkurl.'csv">'.
                    get_string('csvdownload', 'gradereport_rubrics').'</a></li>
                    <li><a href="'.$linkurl.'excelcsv">'.
                    get_string('excelcsvdownload', 'gradereport_rubrics').'</a></li></ul>';
            }

            // Put data into table.
            $output .= $this->display_table($data, $rubricarray);
        }

        $this->output = $output;
        if (!$this->csv) {
            echo $output;
        }
    }

    public function display_table($data, $rubricarray) {
        global $DB, $CFG;

        $csvoutput = "";
        $summaryarray = array();

        if (!$this->csv) {
            $output = html_writer::start_tag('div', array('class' => 'rubrics'));
            $table = new html_table();
            $table->head = array(get_string('student', 'gradereport_rubrics'));
            foreach ($rubricarray as $key => $value) {
                $table->head[] = $rubricarray[$key]['crit_desc'];
            }
            $table->head[] = get_string('grade', 'gradereport_rubrics');
            $table->data = array();
            $table->data[] = new html_table_row();
            $sep = ",";
            $line = "\n";
        } else {
            if ($this->excel) {
                print chr(0xFF).chr(0xFE);
                $sep = "\t".chr(0);
                $line = "\n".chr(0);
            } else {
                $sep = ",";
                $line = "\n";
            }
            // Add csv headers.
            $csvoutput .= $this->csv_quote(strip_tags(get_string('student', 'gradereport_rubrics')), $this->excel).$sep;
            foreach ($rubricarray as $key => $value) {
                $csvoutput .= $this->csv_quote(strip_tags($rubricarray[$key]['crit_desc']), $this->excel).$sep;
            }
            $csvoutput .= $this->csv_quote(strip_tags(get_string('grade', 'gradereport_rubrics')), $this->excel).$sep;
            $csvoutput .= $line;
        }

        foreach ($data as $values) {
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = $values[0]; // Student name.
            $csvoutput .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
            $row->cells[] = $cell;
            $thisgrade = get_string('nograde', 'gradereport_rubrics');
            if (count($values[1]) == 0) { // Students with no marks, add fillers.
                foreach ($rubricarray as $key => $value) {
                    $cell = new html_table_cell();
                    $cell->text = get_string('nograde', 'gradereport_rubrics');
                    $row->cells[] = $cell;
                    $csvoutput .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
                }
            }
            foreach ($values[1] as $value) {
                $cell = new html_table_cell();
                if ($this->displaylevel) {
                    $cell->text = $rubricarray[$value->criterionid][$value->levelid]->definition." - ";
                }
                $cell->text .= round($rubricarray[$value->criterionid][$value->levelid]->score, 2);
                if ($this->displayremark) {
                    $cell->text .= " - ".$value->remark;
                }
                $row->cells[] = $cell;
                $thisgrade = round($value->grade, 2); // Grade cell.

                if (!array_key_exists($value->criterionid, $summaryarray)) {
                    $summaryarray[$value->criterionid]["sum"] = 0;
                    $summaryarray[$value->criterionid]["count"] = 0;
                }
                $summaryarray[$value->criterionid]["sum"] += $rubricarray[$value->criterionid][$value->levelid]->score;
                $summaryarray[$value->criterionid]["count"]++;

                $csvoutput .= $this->csv_quote(strip_tags($cell->text), $this->excel).$sep;
            }

            $cell = new html_table_cell();
            $cell->text = $thisgrade; // Grade cell.
            if ($thisgrade != get_string('nograde', 'gradereport_rubrics')) {
                if (!array_key_exists("grade", $summaryarray)) {
                    $summaryarray["grade"]["sum"] = 0;
                    $summaryarray["grade"]["count"] = 0;
                }
                $summaryarray["grade"]["sum"] += $thisgrade;
                $summaryarray["grade"]["count"]++;
            }
            $row->cells[] = $cell;
            $csvoutput .= $this->csv_quote(strip_tags($thisgrade), $this->excel).$sep;
            $table->data[] = $row;
            $csvoutput .= $line;
        }

        // Summary row.
        if ($this->displaysummary) {
            $row = new html_table_row();
            $cell = new html_table_cell();
            $cell->text = get_string('summary', 'gradereport_rubrics');
            $row->cells[] = $cell;
            $csvoutput .= $this->csv_quote(strip_tags(get_string('summary', 'gradereport_rubrics')), $this->excel).$sep;
            foreach ($summaryarray as $sum) {
                $ave = round($sum["sum"] / $sum["count"], 2);
                $cell = new html_table_cell();
                $cell->text .= $ave;
                $csvoutput .= $this->csv_quote(strip_tags($ave), $this->excel).$sep;
                $row->cells[] = $cell;
            }
            $table->data[] = $row;
            $csvoutput .= $line;
        }

        if ($this->csv) {
            $output = $csvoutput;
        } else {
            $output .= html_writer::table($table);
            $output .= html_writer::end_tag('div');
        }

        return $output;
    }

    public function csv_quote($value, $excel) {
        if ($excel) {
            return core_text::convert('"'.str_replace('"', "'", $value).'"', 'UTF-8', 'UTF-16LE');
        } else {
            return '"'.str_replace('"', "'", $value).'"';
        }
    }

    private function get_moodle_grades() {
        global $DB, $CFG;

        $grades = $DB->get_records('grade_grades', array('itemid' => $this->course_grade_item->id), 'userid', 'userid, finalgrade');
        if (!is_array($grades)) {
            $grades = array();
        }

        $this->moodle_grades = array();

        if ($this->course_grade_item->gradetype == GRADE_TYPE_SCALE) {
            $config = get_config('grade_report_rubrics');
            $pgscale = new grade_scale(array('id' => $config->scale));
            $scaleitems = $pgscale->load_items();
            foreach ($this->moodle_students as $st) {
                if (isset($grades[$st->id])) {
                    $fg = (int)$grades[$st->id]->finalgrade;
                    if (isset($scaleitems[$fg - 1])) {
                        $this->moodle_grades[$st->id] = $scaleitems[$fg - 1];
                    } else {
                        $this->moodle_grades[$st->id] = null;
                    }
                } else {
                    $this->moodle_grades[$st->id] = null;
                }
            }
        } else {
            foreach ($this->moodle_students as $st) {
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
