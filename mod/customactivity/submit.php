<?php
// submit.php - ONLY saves answer and moves forward
require_once('../../config.php');

$cmid      = required_param('id', PARAM_INT);
$qindex    = required_param('qindex', PARAM_INT);
$answer = trim(optional_param('answer', '', PARAM_RAW_TRIMMED));
$timespent = required_param('timespent', PARAM_INT);
$sesskey   = required_param('sesskey', PARAM_ALPHANUM); // Required for security

$cm       = get_coursemodule_from_id('customactivity', $cmid, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('customactivity', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey(); // Validates the sesskey

// Get all questions
$questions = $DB->get_records('customactivity_questions', ['customactivityid' => $activity->id], 'qno ASC');
$questionlist = array_values($questions);
if (!isset($questionlist[$qindex])) {
    redirect(new moodle_url('/mod/customactivity/view.php', ['id' => $cmid]));
}
$currentq = $questionlist[$qindex];
// Save temp answer
$record = $DB->get_record('customactivity_submissions', [
    'customactivityid' => $activity->id,
    'questionid'       => $currentq->id,
    'userid'           => $USER->id
]);

$record = $record ?: new stdClass();
$record->customactivityid = $activity->id;
$record->questionid       = $currentq->id;
$record->userid           = $USER->id;
$record->answer = $answer;
$record->timespent = max((int)($record->timespent ?? 0), $timespent);
$record->ipaddress        = getremoteaddr();   // add here
if (empty($record->id)) {
    $record->timecreated = time();
}
$record->timemodified = time();

if (!empty($record->id)) {
    $DB->update_record('customactivity_submissions', $record);
} else {
    $DB->insert_record('customactivity_submissions', $record);
}

// FINAL SUBMIT?
if (optional_param('finalsubmit', false, PARAM_BOOL)) {

    // (Optional but recommended) mark completion
    $final = $DB->get_record('customactivity_submissions', [
        'customactivityid' => $activity->id,
        'userid'           => $USER->id,
        'questionid'       => 0
    ]);

    if (!$final) {
        $final = new stdClass();
        $final->customactivityid = $activity->id;
        $final->userid           = $USER->id;
        $final->questionid       = 0;
        $final->timecreated      = time();
        $final->ipaddress        = getremoteaddr();
        $DB->insert_record('customactivity_submissions', $final);
    }

    redirect(new moodle_url('/mod/customactivity/finalgrade.php', [
        'id' => $cmid,
        'sesskey' => sesskey()
    ]));
    exit;

} else {
    // Normal next question
    $nextindex = $qindex + 1;
    redirect(new moodle_url('/mod/customactivity/view.php', [
        'id' => $cmid,
        'qindex' => $nextindex
    ]));
}
