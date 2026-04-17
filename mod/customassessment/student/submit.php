<?php
// mod/customassessment/student/submit.php

require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);

$cm         = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
//require_sesskey();   // very important!

$context = context_module::instance($cm->id);

// Only allow submission if frozen
if (!in_array($assessment->status, ['frozen', 'evaluation_in_progress', 'result_published'])) {

    redirect('/mod/customassessment/view.php?id=' . $id,
        'This assessment is no longer open for submission.',
        null,
        \core\output\notification::NOTIFY_ERROR);
}

// Get submitted answers
$answers = optional_param_array('answers', [], PARAM_RAW);
$skipped = optional_param_array('skipped', [], PARAM_INT);

if (empty($answers)) {
    redirect('/mod/customassessment/student/attempt.php?id=' . $id,
        'No answers were submitted.',
        null,
        \core\output\notification::NOTIFY_ERROR);
}

// ────────────────────────────────────────────────
// Find or validate attempt
// ────────────────────────────────────────────────
$attempt = $DB->get_record('customassessment_attempt', [
    'assessmentid' => $assessment->id,
    'userid'       => $USER->id
], '*', MUST_EXIST);

if (in_array($attempt->status, ['submitted', 'evaluated'])) {
    redirect('/mod/customassessment/view.php?id=' . $id,
        'You have already submitted this assessment.',
        null,
        \core\output\notification::NOTIFY_WARNING);
}

// ────────────────────────────────────────────────
// Begin transaction (safer for multiple inserts)
// ────────────────────────────────────────────────
$transaction = $DB->start_delegated_transaction();

try {
    // 1. Mark attempt as submitted
    $DB->update_record('customassessment_attempt', (object) [
        'id'           => $attempt->id,
        'status'       => 'submitted',
        'submitted_at' => time(),
    ]);

    // 2. Save each answer
   $questions = $DB->get_records(
    'customassessment_questions',
    ['assessmentid' => $assessment->id, 'status' => 'accepted'],
    'id ASC'
);

foreach ($questions as $q) {

    $qid = $q->id;

    $text = isset($answers[$qid]) ? trim($answers[$qid]) : '';

    $is_skipped = !empty($skipped[$qid]) || $text === '';

    $record = (object) [
        'attemptid'   => $attempt->id,
        'questionid'  => $qid,
        'answer'      => $is_skipped ? '[SKIPPED]' : clean_param($text, PARAM_RAW),
        'score'       => $is_skipped ? 0 : null,
        'feedback'    => $is_skipped ? 'Question skipped by student' : null,
        'evaluated'   => $is_skipped ? 1 : 0,
        'timecreated' => time(),
    ];

    $DB->insert_record('customassessment_answer', $record);
}


    // 3. Add to evaluation queue (only if not already queued)
    if (!$DB->record_exists('customassessment_eva_queue', [
        'type'   => 'student_attempt',
        'ref_id' => $attempt->id,
        'status' => 'pending'
    ])) {
        $queue = (object) [
            'type'         => 'student_attempt',
            'ref_id'       => $attempt->id,
            'status'       => 'pending',
            'created_at'   => time(),
            'processed_at' => null,
            'failcount'    => 0
        ];
        $DB->insert_record('customassessment_eva_queue', $queue);
    }

    // 4. Optional: If first submission → move assessment to evaluation_in_progress
    $submitted_count = $DB->count_records('customassessment_attempt', [
        'assessmentid' => $assessment->id,
        'status'       => 'submitted'
    ]);

    // if ($submitted_count === 1) {
    //     $DB->set_field('customassessment', 'status', 'evaluation_in_progress', [
    //         'id' => $assessment->id
    //     ]);
    // }

    $transaction->allow_commit();

    // Success
    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        get_string('assessmentsubmitted', 'customassessment') ?: 'Your assessment has been submitted successfully. Results will be available after evaluation.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );

} catch (Exception $e) {
    $transaction->rollback($e);
    redirect(
        new moodle_url('/mod/customassessment/student/attempt.php', ['id' => $id]),
        'Error submitting assessment: ' . $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}