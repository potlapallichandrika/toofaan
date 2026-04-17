<?php
// mod/customassessment/student/attempt.php

require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);   // course module id

$cm         = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if (!in_array($assessment->status, ['frozen', 'evaluation_in_progress', 'result_published'])) {

    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        get_string('assessmentnotready', 'customassessment'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Only accepted questions
$questions = $DB->get_records(
    'customassessment_questions',
    ['assessmentid' => $assessment->id, 'status' => 'accepted'],
    'id ASC'
);

if (empty($questions)) {
    redirect(new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        'No questions are available for this assessment yet.',
        null,
        \core\output\notification::NOTIFY_ERROR);
}

$total_questions = count($questions);

// ── Attempt handling ──
$attempt = $DB->get_record('customassessment_attempt', [
    'assessmentid' => $assessment->id,
    'userid'       => $USER->id
]);

if (!$attempt) {
    // Create new attempt
    $attempt = (object) [
        'assessmentid'   => $assessment->id,
        'userid'         => $USER->id,
        'status'         => 'in_progress',
        'started_at'     => time(),
        'submitted_at'   => null,
        'score'          => null
    ];
    $attempt->id = $DB->insert_record('customassessment_attempt', $attempt);
} else if (in_array($attempt->status, ['submitted', 'evaluated'])) {
    redirect(new moodle_url('/mod/customassessment/student/review.php', ['id' => $id]),
        'You have already submitted this assessment.',
        null,
        \core\output\notification::NOTIFY_INFO);
} else if ($attempt->status === 'not_started') {
    $DB->set_field('customassessment_attempt', 'status', 'in_progress', ['id' => $attempt->id]);
    $attempt->status = 'in_progress';
}

$PAGE->set_url('/mod/customassessment/student/attempt.php', ['id' => $id]);
$PAGE->set_title(format_string($assessment->name));
$PAGE->set_heading($assessment->name);
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');

echo $OUTPUT->header();

//echo $OUTPUT->heading(format_string($assessment->name), 2);

echo $OUTPUT->box_start('generalbox mb-4');

$topicname = $DB->get_field('customassessment_topics', 'topicname', ['id' => $assessment->topicid]);
echo '<p><strong>Topic:</strong> ' . format_string($topicname ?? '—') . '</p>';
echo '<p><strong>Total Questions:</strong> ' . $total_questions . '</p>';
//echo '<p class="text-muted">Answer in detail. You may skip questions if needed — skipped questions receive no marks.</p>';

echo $OUTPUT->box_end();

// Form
echo '<form method="post" action="' . new moodle_url('/mod/customassessment/student/submit.php') . '" id="attemptform">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

$i = 1;
foreach ($questions as $q) {
    echo '<div class="card mb-4 shadow-sm border">';
    echo '<div class="card-header bg-light d-flex justify-content-between align-items-center">';
    echo '<div>';
    echo '<strong>Question ' . $i . ' of ' . $total_questions . '</strong>';
    //echo '<small class="text-muted ms-3">(' . ucfirst($q->bloomslevel) . ' level)</small>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="card-body">';
    
    // Question text
    echo '<div class="mb-3">';
    echo format_text($q->questiontext, FORMAT_HTML);
    echo '</div>';
    
    // Answer area
    
   echo '<textarea name="answers[' . $q->id . ']"
        class="form-control answer-textarea"
        rows="9"
        autocomplete="off"></textarea>';

echo '<input type="hidden"
        name="skipped[' . $q->id . ']"
        value="0">';
    // Skip controls
    echo '<div class="mt-3 d-flex align-items-center gap-3">';
    echo '<button type="button" class="btn btn-outline-secondary skip-btn" data-qid="' . $q->id . '">';
    echo '<i class="fa fa-forward me-1"></i> Skip this question';
    echo '</button>';
    
    echo '<small class="text-muted skipped-notice d-none fw-bold text-danger">';
    echo 'This question will be submitted as skipped (no marks)';
    echo '</small>';
    echo '</div>';
    
    echo '</div>'; // card-body
    echo '</div>'; // card
    $i++;
}

echo '<div class="text-center mt-5 mb-5">';
echo '<button type="submit" class="btn btn-success btn-lg px-5 py-3" id="submitbtn">';
echo get_string('submitassessment', 'customassessment');
echo '</button>';
echo '</div>';

echo '</form>';

echo $OUTPUT->footer();
?>

<script>
// Proper submit handler (DO NOT use onclick)
document.getElementById('attemptform').addEventListener('submit', function (e) {

    if (!confirm('Are you sure you want to submit?\nYou cannot change answers after submission.')) {
        e.preventDefault();
        return;
    }

    const btn = document.getElementById('submitbtn');
    btn.disabled = true;
    btn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';
});

// Skip button logic
document.querySelectorAll('.skip-btn').forEach(btn => {
    btn.addEventListener('click', function () {

        const qid = this.dataset.qid;
        const textarea = document.querySelector(`textarea[name="answers[${qid}]"]`);
        const skippedInput = document.querySelector(`input[name="skipped[${qid}]"]`);
        const notice = this.parentElement.querySelector('.skipped-notice');

        if (textarea.value.trim() !== '') {
            if (!confirm('You have written something. Skipping will discard your answer. Continue?')) {
                return;
            }
        }

        // Clear textarea and disable it
        textarea.value = '';
        textarea.disabled = true;

        // Mark skipped explicitly
        skippedInput.value = 1;

        this.disabled = true;
        this.innerHTML = '<i class="fa fa-check me-1"></i> Skipped';
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-outline-danger');

        notice.classList.remove('d-none');
    });
});

</script>


<style>
.skipped-notice {
    font-style: italic;
}
.answer-textarea:disabled {
    background-color: #f8f9fa !important;
    color: #6c757d !important;
    cursor: not-allowed;
}

.skipped-notice {
    font-size: 0.95rem;
}

.skip-btn {
    min-width: 160px;
}
</style>