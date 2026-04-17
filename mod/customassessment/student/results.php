<?php
// mod/customassessment/view_result.php

require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT); // Course module ID

require_login();

$cm = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);
require_capability('mod/customassessment:view', $context);

// ─────────────────────────────────────────────
// PAGE SETUP (THIS FIXES YOUR ERROR)
// ─────────────────────────────────────────────
$PAGE->set_url('/mod/customassessment/view_result.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);   // ✅ REQUIRED
$PAGE->set_title('Assessment Results');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('course');

echo $OUTPUT->header();

echo $OUTPUT->heading('Assessment Results', 2);

// ─────────────────────────────────────────────
// Get student's evaluated attempt
// ─────────────────────────────────────────────
$attempt = $DB->get_record(
    'customassessment_attempt',
    [
        'assessmentid' => $assessment->id,
        'userid'       => $USER->id,
        'status'       => 'evaluated'
    ],
    '*',
    IGNORE_MISSING
);

if (!$attempt) {
    echo $OUTPUT->notification(
        'Results are not available yet.',
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

// ─────────────────────────────────────────────
// Fetch answers
// ─────────────────────────────────────────────
$answers = $DB->get_records(
    'customassessment_answer',
    ['attemptid' => $attempt->id],
    'id ASC'
);

if (empty($answers)) {
    echo $OUTPUT->notification(
        'No answers found.',
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();
    exit;
}

// ─────────────────────────────────────────────
// Display results INLINE (NO MODAL)
// ─────────────────────────────────────────────
$qno = 1;

foreach ($answers as $ans) {

    $question = $DB->get_record(
        'customassessment_questions',
        ['id' => $ans->questionid],
        '*',
        IGNORE_MISSING
    );

    if (!$question) {
        continue;
    }

    echo '<div class="card mb-4 shadow-sm">';
    echo '<div class="card-body">';

    // Question
    echo '<h5 class="card-title">Question ' . $qno++ . '</h5>';
    echo '<p class="fw-bold">' . format_text($question->questiontext) . '</p>';

    // Student Answer
    echo '<hr>';
    echo '<p class="fw-bold text-primary">Student Answer</p>';
    echo '<div class="border rounded p-3 mb-3">';
    echo format_text($ans->answer ?: 'Not Answered');
    echo '</div>';

    // Model Answer
    // echo '<p class="fw-bold text-success">Model Answer</p>';
    // echo '<div class="border rounded p-3 mb-3 bg-light">';
    // echo format_text($question->modelanswer);
    // echo '</div>';

    // Score
    echo '<p><strong>Score:</strong> ';
    echo '<span>' . ($ans->score ?? 0) . ' / 10</span>';
    echo '</p>';

    // AI Feedback
    echo '<p class="fw-bold">AI Feedback</p>';
    echo '<div class="alert alert-info">';
    echo format_text($ans->feedback ?? 'No feedback available.');
    echo '</div>';

    echo '</div></div>';
}

// ─────────────────────────────────────────────
// Final Score
// ─────────────────────────────────────────────
echo '<div class="alert alert-success mt-4">';
echo '<strong>Final Score:</strong> ' . format_float($attempt->score, 1);
echo '</div>';

echo $OUTPUT->footer();
