<?php
require('../../config.php');

$id = required_param('id', PARAM_INT); // course module id

$cm = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$customassessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/customassessment/index.php', ['id' => $id]);
$PAGE->set_title(format_string($customassessment->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();

echo html_writer::tag('h2', format_string($customassessment->name));

if (has_capability('mod/customassessment:addinstance', $context)) {
    echo html_writer::link(
        new moodle_url('/mod/customassessment/teacher/dashboard.php', ['id' => $id]),
        'Go to Teacher Dashboard'
    );
} else {
    echo html_writer::link(
        new moodle_url('/mod/customassessment/student/attempt.php', ['id' => $id]),
        'Start Assessment'
    );
}

echo $OUTPUT->footer();
