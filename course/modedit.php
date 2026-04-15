
<style>

.availability-inner, .availability-plugincontrols {
    float: left;
    border-radius: .5rem;
    border: 1px solid rgba(0,0,0,.125);
    padding: 1rem;
    margin-top: .5rem;
    display:none;
}
#fitem_id_availabilityconditionsjson.form-group.row.fitem{
    display:none;
}
#fitem_id_visible.form-group.row.fitem{
    display:none;
}
</style>

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
* Adds or updates modules in a course using new formslib
*
* @package    moodlecore
* @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once("../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot . '/course/modlib.php');

$add    = optional_param('add', '', PARAM_ALPHANUM);     // Module name.
$update = optional_param('update', 0, PARAM_INT);
$return = optional_param('return', 0, PARAM_BOOL);    //return to course/view.php if false or mod/modname/view.php if true
$type   = optional_param('type', '', PARAM_ALPHANUM); //TODO: hopefully will be removed in 2.0
$sectionreturn = optional_param('sr', null, PARAM_INT);

$url = new moodle_url('/course/modedit.php');
$url->param('sr', $sectionreturn);
if (!empty($return)) {
    $url->param('return', $return);
}

if (!empty($add)) {
    $section = required_param('section', PARAM_INT);
    $course  = required_param('course', PARAM_INT);

    $url->param('add', $add);
    $url->param('section', $section);
    $url->param('course', $course);
    $PAGE->set_url($url);

    $course = $DB->get_record('course', array('id'=>$course), '*', MUST_EXIST);
    require_login($course);

    // There is no page for this in the navigation. The closest we'll have is the course section.
    // If the course section isn't displayed on the navigation this will fall back to the course which
    // will be the closest match we have.
    navigation_node::override_active_url(course_get_url($course, $section));

    // MDL-69431 Validate that $section (url param) does not exceed the maximum for this course / format.
    // If too high (e.g. section *id* not number) non-sequential sections inserted in course_sections table.
    // Then on import, backup fills 'gap' with empty sections (see restore_rebuild_course_cache). Avoid this.

    // $courseformat = course_get_format($course);
    // $maxsections = $courseformat->get_max_sections();
    // if ($section > $maxsections) {
    //     print_error('maxsectionslimit', 'moodle', '', $maxsections);
    // }

    // list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $add, $section);
    // $data->return = 0;
    // $data->sr = $sectionreturn;
    // $data->add = $add;
    // if (!empty($type)) { //TODO: hopefully will be removed in 2.0
    //     $data->type = $type;
    // }

    // $sectionname = get_section_name($course, $cw);
    // $fullmodulename = get_string('modulename', $module->name);

    // if ($data->section && $course->format != 'site') {
    //     $heading = new stdClass();
    //     $heading->what = $fullmodulename;
    //     $heading->to   = $sectionname;
    //     $pageheading = get_string('addinganewto', 'moodle', $heading);
    // } else {
    //     $pageheading = get_string('addinganew', 'moodle', $fullmodulename);
    // }
    // $navbaraddition = $pageheading;
    list($module, $context, $cw) = can_add_moduleinfo($course, $add, $section);

    $cm = null;

    $data = new stdClass();
    $data->section          = $section;  // The section number itself - relative!!! (section column in course_sections)
    $data->visible          = 0;
    $data->course           = $course->id;
    $data->module           = $module->id;
    $data->modulename       = $module->name;
    $data->groupmode        = $course->groupmode;
    $data->groupingid       = $course->defaultgroupingid;
    $data->id               = '';
    $data->instance         = '';
    $data->coursemodule     = '';
    $data->add              = $add;
    $data->return           = 0; //must be false if this is an add, go back to course view on cancel
    $data->sr               = $sectionreturn;

    if (plugin_supports('mod', $data->modulename, FEATURE_MOD_INTRO, true)) {
        $draftid_editor = file_get_submitted_draft_itemid('introeditor');
        file_prepare_draft_area($draftid_editor, null, null, null, null, array('subdirs'=>true));
        $data->introeditor = array('text'=>'', 'format'=>FORMAT_HTML, 'itemid'=>$draftid_editor); // TODO: add better default
    }

    // tle code update by chandrika
    if($data->modulename=='vpl'){
        $data->algorithm    = $cm->algorithm;
        $data->tlelimit    = $cm->tlelimit;
    }
    if (plugin_supports('mod', $data->modulename, FEATURE_ADVANCED_GRADING, false)
            and has_capability('moodle/grade:managegradingforms', $context)) {
        require_once($CFG->dirroot.'/grade/grading/lib.php');

        $data->_advancedgradingdata['methods'] = grading_manager::available_methods();
        $areas = grading_manager::available_areas('mod_'.$module->name);

        foreach ($areas as $areaname => $areatitle) {
            $data->_advancedgradingdata['areas'][$areaname] = array(
                'title'  => $areatitle,
                'method' => '',
            );
            $formfield = 'advancedgradingmethod_'.$areaname;
            $data->{$formfield} = '';
        }
    }

    if (!empty($type)) { //TODO: hopefully will be removed in 2.0
        $data->type = $type;
    }

    $sectionname = get_section_name($course, $cw);
    $fullmodulename = get_string('modulename', $module->name);

    if ($data->section && $course->format != 'site') {
        $heading = new stdClass();
        $heading->what = $fullmodulename;
        $heading->to   = $sectionname;
        $pageheading = get_string('addinganewto', 'moodle', $heading);
    } else {
        $pageheading = get_string('addinganew', 'moodle', $fullmodulename);
    }
    $navbaraddition = $pageheading;
} else if (!empty($update)) {

    $url->param('update', $update);
    $PAGE->set_url($url);

    // Select the "Edit settings" from navigation.
    navigation_node::override_active_url(new moodle_url('/course/modedit.php', array('update'=>$update, 'return'=>1)));

    // Check the course module exists.
    $cm = get_coursemodule_from_id('', $update, 0, false, MUST_EXIST);

    // Check the course exists.
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

    // require_login
    require_login($course, false, $cm); // needed to setup proper $COURSE

    list($cm, $context, $module, $data, $cw) = get_moduleinfo_data($cm, $course);
    $data->return = $return;
    $data->sr = $sectionreturn;
    $data->update = $update;

    // algorithm change by ram mohan
    if($data->modulename=='vpl'){

    //   print_r($data->timelimit_minutes);
    // die();
        $data->algorithm    = $cm->algorithm;

       // ✅ Timer logic code updated by chandrika
   $minutes = isset($data->timelimit_minutes) ? $data->timelimit_minutes : 0;

    if ($minutes >= 10080) {
        $data->timelimit_value = $minutes / 10080;
        $data->timelimit_unit = 'weeks';
    } else if ($minutes >= 1440) {
        $data->timelimit_value = $minutes / 1440;
        $data->timelimit_unit = 'days';
    } else if ($minutes >= 60) {
        $data->timelimit_value = $minutes / 60;
        $data->timelimit_unit = 'hours';
    } else {
        $data->timelimit_value = $minutes;
        $data->timelimit_unit = 'minutes';
    }

    //$data->timelimit_enabled = ($minutes > 0) ? 1 : 0; 
    $data->timelimit_enabled = 0;
    }

    $sectionname = get_section_name($course, $cw);
    $fullmodulename = get_string('modulename', $module->name);

    if ($data->section && $course->format != 'site') {
        $heading = new stdClass();
        $heading->what = $fullmodulename;
        $heading->in   = $sectionname;
        $pageheading = get_string('updatingain', 'moodle', $heading);
    } else {
        $pageheading = get_string('updatinga', 'moodle', $fullmodulename);
    }
    $navbaraddition = null;

} else {
    require_login();
    print_error('invalidaction');
}

$pagepath = 'mod-' . $module->name . '-';
if (!empty($type)) { //TODO: hopefully will be removed in 2.0
    $pagepath .= $type;
} else {
    $pagepath .= 'mod';
}
$PAGE->set_pagetype($pagepath);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('limitedwidth');


$modmoodleform = "$CFG->dirroot/mod/$module->name/mod_form.php";
if (file_exists($modmoodleform)) {
    require_once($modmoodleform);
} else {
    print_error('noformdesc');
}

$mformclassname = 'mod_'.$module->name.'_mod_form';
$mform = new $mformclassname($data, $cw->section, $cm, $course);
$mform->set_data($data);

if ($mform->is_cancelled()) {
    if ($return && !empty($cm->id)) {
        $urlparams = [
            'id' => $cm->id, // We always need the activity id.
            'forceview' => 1, // Stop file downloads in resources.
        ];
        $activityurl = new moodle_url("/mod/$module->name/view.php", $urlparams);
        redirect($activityurl);
    } else {
        redirect(course_get_url($course, $cw->section, array('sr' => $sectionreturn)));
    }
} else if ($fromform = $mform->get_data()) {
    if (!empty($fromform->update)) {
        list($cm, $fromform) = update_moduleinfo($cm, $fromform, $course, $mform);
    } else if (!empty($fromform->add)) {
        $fromform = add_moduleinfo($fromform, $course, $mform);
    } else {
        print_error('invaliddata');
    }

    if (isset($fromform->submitbutton)) {
        $url = new moodle_url("/mod/$module->name/view.php", array('id' => $fromform->coursemodule, 'forceview' => 1));
        if (empty($fromform->showgradingmanagement)) {
            redirect($url);
        } else {
            redirect($fromform->gradingman->get_management_url($url));
        }
    } else {
        redirect(course_get_url($course, $cw->section, array('sr' => $sectionreturn)));
    }
    exit;

} else {

    $streditinga = get_string('editinga', 'moodle', $fullmodulename);
    $strmodulenameplural = get_string('modulenameplural', $module->name);

    if (!empty($cm->id)) {
        $context = context_module::instance($cm->id);
    } else {
        $context = context_course::instance($course->id);
    }

    $PAGE->set_heading($course->fullname);
    $PAGE->set_title($streditinga);
    $PAGE->set_cacheable(false);

    if (isset($navbaraddition)) {
        $PAGE->navbar->add($navbaraddition);
    }
    $PAGE->activityheader->disable();

    echo $OUTPUT->header();

    if (get_string_manager()->string_exists('modulename_help', $module->name)) {
        echo $OUTPUT->heading_with_help($pageheading, 'modulename', $module->name, 'monologo');
    } else {
        echo $OUTPUT->heading_with_help($pageheading, '', $module->name, 'monologo');
    }

    $mform->display();

    echo $OUTPUT->footer();
}
// if(user_has_role_assignment($USER->id,3)){
?>

<script>
//*******removing course links and added dashboard / activity type start */
 /*if ($('#roles').val()) {


  
    var baseUrl = '<?php echo $CFG->wwwroot ?>';
    var url = baseUrl + '/local/teacher/dashboard.php';

    // Remove the element with class 'page-context-header'.
    document.querySelector('.page-context-header').remove();

    // Set the HTML content for the element with id 'page-navbar'.
    document.getElementById('page-navbar').innerHTML = "<div id='navbar' style='padding: 2% 6% 1% 2%; display: flex; align-items: center;'> </div>";
    // Set the 'href' attribute for the element with id 'dlink'.
    document.getElementById('dlink').href = url;
}
*/
//*******removing course links and added dashboard / activity type end */


// algorithm check ram mohan

//alert("Hello check");

// ---------- TLE logic (added) ----------
document.getElementById("id_tlelimit").disabled = true; 
// Get the checkbox
  var checkBox = document.getElementById("id_checktle");
//alert("xvcvcx");
document.getElementById("fitem_id_tlelimit").style.display = "none";

document.getElementById("id_checktle").onclick = function(){
    if(checkBox.checked == true){
        document.getElementById("id_tlelimit").disabled = false; 
document.getElementById("fitem_id_tlelimit").style.display = "block";
    }
    else{
        document.getElementById("id_tlelimit").disabled = true; 
document.getElementById("fitem_id_tlelimit").style.display = "none";

    }
}
// ---------- TLE logic end ----------

document.getElementById("id_algorithm").disabled = true; 
// Get the checkbox
  var checkBox1 = document.getElementById("id_enablealgocheck");


document.getElementById("id_enablealgocheck").onclick = function(){
    if(checkBox1.checked == true){
        document.getElementById("id_algorithm").disabled = false; 
    }
    else{
        document.getElementById("id_algorithm").disabled = true; 
        document.getElementById("id_algorithm").value='';

    }
}


</script>




