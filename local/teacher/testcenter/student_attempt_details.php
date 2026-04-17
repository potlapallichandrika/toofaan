<?php
// local/teacher/testcenter/student_attempt_details.php
// while (ob_get_level()) {
//     ob_end_clean();
// }

// // Turn off ALL possible notices/warnings display (temporary debug only)
// ini_set('display_errors', '0');
// error_reporting(0);
define('AJAX_SCRIPT', true);
require_once('../../../config.php');

$attemptid = required_param('attemptid', PARAM_INT);

require_login();

// Fetch attempt
$attempt = $DB->get_record('customassessment_attempt', ['id' => $attemptid], '*', MUST_EXIST);

// Fetch assessment & course module
$assessment = $DB->get_record('customassessment', ['id' => $attempt->assessmentid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('customassessment', $assessment->id, 0, false, MUST_EXIST);

$context = context_module::instance($cm->id);
require_capability('mod/customassessment:manage', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');

// Fetch student
$user = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

// Hidden flag for JS
echo '<input type="hidden" id="is-evaluated" value="' . ($attempt->status === 'evaluated' ? '1' : '0') . '">';

// Container start
echo '<div class="container-fluid">';

// Header
echo '<h5 class="mb-4 text-primary">
        Submission by <strong>' . fullname($user) . '</strong>
      </h5>';

// Meta info
echo '<div class="row mb-4 bg-light p-3 rounded border">';

echo '<div class="col-md-4">
        <p><strong>Attempt ID:</strong> ' . $attempt->id . '</p>
      </div>';

$statusclass = ($attempt->status === 'evaluated')
    ? 'bg-success'
    : (($attempt->status === 'submitted') ? 'bg-warning' : 'bg-secondary');

echo '<div class="col-md-4">
        <p><strong>Status:</strong>
          <span class="badge fs-6 ' . $statusclass . '">' .
            ucfirst($attempt->status) .
          '</span>
        </p>
      </div>';

echo '<div class="col-md-4 text-end">
        <p><strong>Started:</strong> ' .
            ($attempt->started_at ? userdate($attempt->started_at, '%d %b %Y, %I:%M %p') : '—') .
        '</p>
        <p><strong>Submitted:</strong> ' .
            ($attempt->submitted_at ? userdate($attempt->submitted_at, '%d %b %Y, %I:%M %p') : '—') .
        '</p>
      </div>';

echo '</div>';

// Total score
echo '<h6 class="mt-4 mb-3 text-success">
        Total Score: ' .
        ($attempt->score !== null
            ? '<strong class="fs-4">' . $attempt->score . ' / 100</strong>'
            : 'Not graded yet') .
      '</h6>';
$assessmentid = $assessment->id;
      $questions = $DB->get_records(
    'customassessment_questions',
    ['assessmentid' => $assessmentid],
    'id ASC'
);
// Fetch responses
$responses = $DB->get_records(
    'customassessment_answer',
    ['attemptid' => $attemptid],
    'questionid ASC'
);

$total_questions = count($responses);
$answered_count  = 0;
$skipped_count   = 0;
$total_score     = 0;

if ($responses) {

    $qno = 1;

    foreach ($responses as $resp) {

        // Fetch question (safe)
        $question = $DB->get_record(
            'customassessment_questions',
            ['id' => $resp->questionid],
            '*',
            IGNORE_MISSING
        );

        // Skipped detection
        $is_skipped = (trim($resp->answer) === '[SKIPPED]');

        if ($is_skipped) {
            $skipped_count++;
        } else {
            $answered_count++;
            $total_score += ($resp->score !== null ? (int)$resp->score : 0);
        }

        $scoreclass = ($resp->score !== null && $resp->score >= 7)
            ? 'text-success'
            : (($resp->score !== null && $resp->score < 5) ? 'text-danger' : 'text-dark');

        echo '<div class="card mb-4 shadow-sm">';

        echo '<div class="card-header bg-light d-flex justify-content-between">';
        echo '<strong>Question ' . $qno++ . '</strong>';
        echo '<span class="badge ' . ($resp->evaluated ? 'bg-success' : 'bg-warning') . '">' .
                ($resp->evaluated ? 'Evaluated' : 'Pending') .
             '</span>';
        echo '</div>';

        echo '<div class="card-body">';

        if ($question) {
            echo '<p class="fw-bold">Question:</p>';
            echo '<div class="mb-3">' .
                 format_text($question->questiontext, FORMAT_HTML, ['context' => $context]) .
                 '</div>';
        }

        echo '<p class="fw-bold text-primary">Student Answer:</p>';

        if ($is_skipped) {
            echo '<div class="mb-3 p-3 border rounded text-danger fw-bold">
                    Question skipped by student
                  </div>';
        } else {
            echo '<div class="mb-3 p-3 border rounded">' .
                 format_text($resp->answer ?: '[No answer]', FORMAT_HTML, ['context' => $context]) .
                 '</div>';
        }

        echo '<p class="fw-bold">Feedback:</p>';
        echo '<div class="alert alert-info">' .
             ($resp->feedback
                ? format_text($resp->feedback, FORMAT_HTML, ['context' => $context])
                : '<em>No feedback yet</em>') .
             '</div>';

        echo '<p class="fw-bold">Score:
                <span class="' . $scoreclass . ' fs-5">' .
                ($resp->score !== null ? $resp->score : '—') .
                '</span>
              </p>';

        echo '</div></div>';
    }

    // Summary
    echo '<div class="mt-4 p-3 bg-light border rounded text-center">';
    echo '<strong>Summary:</strong> ' . ucfirst($attempt->status) .
         ' | Total Questions: ' . $total_questions .
         ' | Answered: ' . $answered_count .
         ' | Skipped: ' . $skipped_count .
         ' | Total Score: ' . $total_score . ' / ' . ($total_questions * 10);
    echo '</div>';

} else {
    echo '<div class="alert alert-info text-center py-4">
            No responses found for this attempt.
          </div>';
}

echo '</div>';