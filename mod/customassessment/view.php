<?php
// mod/customassessment/view.php

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course module ID

$cm = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);


require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/customassessment/view.php', ['id' => $id]);
$PAGE->set_title(format_string($assessment->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('course');  // Changed from popup → better for main view

echo $OUTPUT->header();

if (!empty($assessment->intro)) {
    echo $OUTPUT->box(format_module_intro('customassessment', $assessment, $cm->id), 'generalbox mod_introbox mb-4');
}

// Fetch subject and topic
$subject = $DB->get_record('customassessment_subjects', ['id' => $assessment->subjectid]);
$topic   = $DB->get_record('customassessment_topics', ['id' => $assessment->topicid]);

// Normalize status
$normalizedstatus = strtolower(trim($assessment->status ?? 'created'));

// Status display mapping
$status_map = [
    'created'                => ['text' => 'Draft – Not ready yet',          'class' => 'badge-secondary'],
    'generating'             => ['text' => 'Generating questions…',          'class' => 'badge-warning'],
    'questions_generated'    => ['text' => 'Questions generated',            'class' => 'badge-info'],
    'under_review'           => ['text' => 'Under teacher review',           'class' => 'badge-primary'],
    'frozen'                 => ['text' => 'Frozen – Ready for students',    'class' => 'badge-success'],
    'evaluation_in_progress' => ['text' => 'Evaluation in progress',         'class' => 'badge-warning'],
    'result_published'       => ['text' => 'Results published',              'class' => 'badge-success text-white bg-success'],
];

$status = $status_map[$normalizedstatus] ?? ['text' => ucfirst($normalizedstatus), 'class' => 'badge-secondary'];

// Info box with status
//echo $OUTPUT->box_start('generalbox mb-4 shadow-sm');
echo '<div class="row align-items-center">';
echo '<div class="col-md-8">';
echo '<p class="mb-1"><strong>Subject:</strong> '   . format_string($subject->subjectname  ?? '—') . '</p>';
echo '<p class="mb-1"><strong>Topic:</strong> '     . format_string($topic->topicname      ?? '—') . '</p>';
echo '<p class="mb-1"><strong>Required questions:</strong> ' . ($assessment->numquestions ?? 10) . '</p>';
echo '</div>';
echo '<div class="col-md-4 text-md-end">';

if (has_capability('mod/customassessment:manage', $context)
    || has_capability('moodle/course:update', $context)
    || is_siteadmin()) {

    // Teacher/Admin ONLY
    echo '<span class="badge ' . $status['class'] . ' px-3 py-2 fs-5">'
        . $status['text'] .
        '</span>';
}

echo '</div>';
echo '</div>';
//echo $OUTPUT->box_end();

// Count questions
$generatedcount = $DB->count_records('customassessment_questions', ['assessmentid' => $assessment->id]);
$acceptedcount  = $DB->count_records('customassessment_questions', ['assessmentid' => $assessment->id, 'status' => 'accepted']);

// ────────────────────────────────────────────────
// TEACHER / ADMIN VIEW
// ────────────────────────────────────────────────
if (has_capability('mod/customassessment:manage', $context) ||
    has_capability('moodle/course:update', $context) ||
    is_siteadmin()) {

    echo $OUTPUT->container_start('text-center mt-4');

    // Progress bar: accepted vs required
    // if ($generatedcount > 0) {
    //     $percent = min(100, ($acceptedcount / max(1, $assessment->numquestions)) * 100);
    //     echo '<div class="mb-4">';
    //     echo '<p class="text-muted">';
    //     echo "Accepted: <strong>{$acceptedcount}</strong> / Required: <strong>{$assessment->numquestions}</strong>";
    //     echo '</p>';
    //     echo '<div class="progress" style="height: 10px;">';
    //     echo '<div class="progress-bar ' . ($percent >= 100 ? 'bg-success' : 'bg-info') . '" role="progressbar" style="width: ' . $percent . '%;" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"></div>';
    //     echo '</div>';
    //     echo '</div>';
    // }



    if (has_capability('mod/customassessment:manage', $context) && $generatedcount > 0) {

    echo $OUTPUT->heading(get_string('generatedquestions', 'customassessment'), 3, 'mt-5 mb-3');

    if ($normalizedstatus === 'frozen') {
        echo $OUTPUT->notification(
            get_string('questionsfrozenviewonly', 'customassessment'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

   $questions = $DB->get_records(
    'customassessment_questions',
    ['assessmentid' => $assessment->id],
    'id ASC'
);
    if ($assessment->status === 'questions_generated' && !empty($questions)) {
    $DB->set_field(
        'customassessment',
        'status',
        'under_review',
        ['id' => $assessment->id]
    );
    $assessment->status = 'under_review';
    $normalizedstatus = 'under_review';
}
$i = 0;
    foreach ($questions as $q) {
        $i++; // continue numbering from earlier if needed, or reset $i=1;

        $statusbadge = '';
        if ($q->status === 'accepted') {
            $statusbadge = '<span class="badge bg-success">Accepted</span>';
        } elseif ($q->status === 'rejected') {
            $statusbadge = '<span class="badge bg-danger">Rejected</span>';
        } else {
            $statusbadge = '<span class="badge bg-warning">Pending</span>';
        }

        echo '<div class="card mb-3 border-' . ($q->status === 'accepted' ? 'success' : 'secondary') . '">';
        echo '<div class="card-header d-flex justify-content-between">';
        echo '<strong>' . get_string('question') . ' ' . $i . ' ' . $statusbadge . '</strong>';
        echo '<small class="text-muted">Bloom\'s: ' . s($q->bloomslevel ?? '—') . '</small>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<p><strong>' . get_string('question') . ':</strong></p>';
        echo format_text($q->questiontext, FORMAT_HTML, ['filter' => true]);
        echo '<hr>';
        echo '<p><strong>' . get_string('modelanswer', 'customassessment') . ':</strong></p>';
        echo '<div class="bg-light p-3 border rounded">';
        echo format_text($q->modelanswer, FORMAT_HTML, ['filter' => true]);
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

    // Generate / Regenerate
    if (in_array($normalizedstatus, ['created', 'questions_generated', 'under_review'])) {
        if ($normalizedstatus === 'created' || $generatedcount == 0) {
            // First generation
            $url = new moodle_url('/mod/customassessment/generate_questions.php');

echo html_writer::start_tag('form', [
    'method'   => 'get',
    'action'  => $url->out(false),
    'onsubmit'=> 'showGeneratingMessage(this);'
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'id',
    'value' => $cm->id
]);

echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey()
]);

echo html_writer::tag('button', 'Generate Questions', [
    'type'  => 'submit',
    'class' => 'btn btn-primary btn-lg px-5'
]);

echo html_writer::end_tag('form');

        }
        
  
    }

    // Review button
    if ($generatedcount > 0 && !in_array($normalizedstatus, ['frozen', 'evaluation_in_progress', 'result_published'])) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/customassessment/manage/review.php', ['id' => $id]),
            'Review & Accept Questions',
            'get',
            ['class' => 'btn btn-secondary btn-lg px-5 mb-3']
        );
    }

    // Freeze button
    if (in_array($normalizedstatus, ['questions_generated', 'under_review'])) {
        if ($acceptedcount >= $assessment->numquestions) {
            $freezeurl = new moodle_url('/mod/customassessment/manage/freeze.php', ['id' => $id]);
            echo '<div class="mt-4">';
            echo $OUTPUT->single_button(
                $freezeurl,
                'Freeze Assessment (Ready for Students)',
                'post',
                ['class' => 'btn btn-success btn-lg px-5']
            );
            echo '</div>';
        } else {
            echo '<div class="alert alert-info mt-4">';
            echo "You need to accept at least <strong>{$assessment->numquestions}</strong> questions before freezing.<br>";
            echo "Currently accepted: <strong>{$acceptedcount}</strong>";
            echo '</div>';
        }
        
    }

    // Status messages for frozen / evaluation / published
    if ($normalizedstatus === 'frozen') {
        echo '<div class="alert alert-success mt-4">';
        echo '<strong>Assessment is FROZEN</strong><br>';
        echo 'Students can now attempt it. No further changes are allowed.';
        echo '</div>';
    } elseif ($normalizedstatus === 'evaluation_in_progress') {
        echo '<div class="alert alert-warning mt-4">';
        echo '<strong>Evaluation in progress</strong><br>';
        echo 'Student submissions are being processed. Results will be available soon.';
        echo '</div>';
    } elseif ($normalizedstatus === 'result_published') {
        echo '<div class="alert alert-success mt-4">';
        echo '<strong>Results Published</strong><br>';
        echo 'All evaluations are complete. Students can view their scores and feedback.';
        echo '</div>';
    }

    echo $OUTPUT->container_end();
}

// ────────────────────────────────────────────────
// STUDENT VIEW
// ────────────────────────────────────────────────
else {

    $attempt = $DB->get_record('customassessment_attempt', [
        'assessmentid' => $assessment->id,
        'userid'       => $USER->id
    ]);

    echo html_writer::start_div('container-fluid px-4 mt-4');
    echo html_writer::start_div('row');
    echo html_writer::start_div('col-12 col-md-6 text-center');

    // Student NOT STARTED
    if (in_array($normalizedstatus, ['frozen', 'evaluation_in_progress', 'result_published']) && !$attempt) {


        echo '<h4 class="mb-3">Assessment is ready to start</h4>';

        echo $OUTPUT->single_button(
            new moodle_url('/mod/customassessment/student/attempt.php', ['id' => $id]),
            'Start Assessment',
            'get',
            ['class' => 'btn btn-secondary']
        );
    }

    // Student IN PROGRESS
    elseif ($attempt && $attempt->status === 'in_progress') {

        echo '<h4 class="mb-3">Continue your assessment</h4>';

        echo $OUTPUT->single_button(
            new moodle_url('/mod/customassessment/student/attempt.php', ['id' => $id]),
            'Continue Assessment',
            'get',
            ['class' => 'btn btn-secondary']
        );
    }

    // Student SUBMITTED
    elseif ($attempt && $attempt->status === 'submitted') {

        echo $OUTPUT->notification(
            'Submission completed. Awaiting teacher evaluation. Refresh the page after some time.',
            \core\output\notification::NOTIFY_INFO
        );
    }

    // Student EVALUATED
    elseif ($attempt && $attempt->status === 'evaluated') {

        echo '<h4 class="mb-3">Your results are available</h4>';

        echo $OUTPUT->single_button(
            new moodle_url('/mod/customassessment/student/results.php', ['id' => $id]),
            'View Results',
            'get',
            ['class' => 'btn btn-primary btn-lg px-5']
        );
    }

    // Assessment NOT READY
    else {
        echo $OUTPUT->notification(
            'This assessment is not yet available.',
            \core\output\notification::NOTIFY_INFO
        );
    }

    echo html_writer::end_div(); // col
    echo html_writer::end_div(); // row
    echo html_writer::end_div(); // container
}



// JavaScript for generating message
$PAGE->requires->js_init_code("
    window.showGeneratingMessage = function(form) {
        const btn = form.querySelector('button');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span> Generating questions...';
        }
        const msg = document.createElement('div');
        msg.className = 'alert alert-info mt-3';
        msg.innerHTML = 'Please wait, questions are being generated. This may take 30–90 seconds.';
        form.parentNode.appendChild(msg);
    };
");

echo $OUTPUT->footer();


