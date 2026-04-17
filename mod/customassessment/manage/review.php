<?php
// review.php - Review & manage generated questions

require_once('../../../config.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);
//$assessment->frozen = ($assessment->status === 'frozen');
$isfrozen = ($assessment->status === 'frozen');
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/customassessment:manage', $context);

// ────────────────────────────────────────────────
// Process form submission (self-processing)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {

    $action = optional_param('action', '', PARAM_ALPHANUMEXT);
if ($action === 'add_manual') {

    if ($isfrozen) {
        redirect($PAGE->url, get_string('cannoteditfrozen', 'customassessment'));
    }

    $qtext  = trim(required_param('manual_questiontext', PARAM_RAW));
    $answer = trim(required_param('manual_modelanswer', PARAM_RAW));
    $bloom  = required_param('manual_bloomslevel', PARAM_TEXT);

    if ($qtext === '' || $answer === '') {
        redirect($PAGE->url, get_string('missingfields', 'customassessment'));
    }

    $record = (object)[
        'assessmentid' => $assessment->id,
        'questiontext' => $qtext,
        'modelanswer'  => $answer,
        'bloomslevel'  => $bloom,
        'status'       => 'accepted',   // manual questions auto-accepted
        'source'       => 'manual',
        'timecreated'  => time()
    ];

    $DB->insert_record('customassessment_questions', $record);

   redirect( $PAGE->url, 'Manual question added successfully.', null, \core\output\notification::NOTIFY_SUCCESS ); 
   
   }


if ($action === 'delete_question') {

    if ($isfrozen) {
        redirect($PAGE->url, get_string('cannoteditfrozen', 'customassessment'));
    }

    $qid = required_param('qid', PARAM_INT);

    // Ensure question belongs to this assessment
    $question = $DB->get_record('customassessment_questions', [
        'id' => $qid,
        'assessmentid' => $assessment->id
    ], '*', MUST_EXIST);

    $DB->delete_records('customassessment_questions', ['id' => $qid]);

   redirect(
    $PAGE->url,
    get_string('questiondeletedsuccessfully', 'customassessment'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
}


    if ($action === 'save_statuses') {

    if ($isfrozen) {
        redirect($PAGE->url, get_string('cannoteditfrozen', 'customassessment'),
            null, \core\output\notification::NOTIFY_WARNING);
    }

    $questiontexts = optional_param_array('questiontext', [], PARAM_RAW);
    $modelanswers  = optional_param_array('modelanswer', [], PARAM_RAW);
    $statuses      = optional_param_array('status', [], PARAM_ALPHANUMEXT);

    $questions = $DB->get_records(
        'customassessment_questions',
        ['assessmentid' => $assessment->id]
    );

    $updated = 0;

    foreach ($questions as $question) {

        $qid = $question->id;
        $data = (object)['id' => $qid];
        $update = false;

        // ✅ Checkbox logic
        $newstatus = isset($statuses[$qid]) ? 'accepted' : 'rejected';

        if ($question->status !== $newstatus) {
            $data->status = $newstatus;
            $update = true;
        }

        // Question text edit
        if (isset($questiontexts[$qid]) && trim($questiontexts[$qid]) !== '') {
            if ($questiontexts[$qid] !== $question->questiontext) {
                $data->questiontext = clean_param($questiontexts[$qid], PARAM_RAW);
                $update = true;
            }
        }

        // Model answer edit
        if (isset($modelanswers[$qid]) && trim($modelanswers[$qid]) !== '') {
            if ($modelanswers[$qid] !== $question->modelanswer) {
                $data->modelanswer = clean_param($modelanswers[$qid], PARAM_RAW);
                $update = true;
            }
        }

        if ($update) {
            $DB->update_record('customassessment_questions', $data);
            $updated++;
        }
    }

    redirect(
        $PAGE->url,
        $updated
            ? get_string('changessaved') . " ($updated question(s))"
            : get_string('nochanges'),
        null,
        $updated
            ? \core\output\notification::NOTIFY_SUCCESS
            : \core\output\notification::NOTIFY_INFO
    );
}


   else if ($action === 'freeze') {

    if ($isfrozen) {
        redirect($PAGE->url,
            get_string('alreadyfrozen', 'customassessment'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Mark assessment as frozen
    $DB->set_field('customassessment', 'status', 'frozen', ['id' => $assessment->id]);

    // Redirect to view.php to show frozen questions
    $viewurl = new moodle_url('/mod/customassessment/view.php', ['id' => $cm->id]);
    redirect(
        $viewurl,
        get_string('topicfrozen', 'customassessment'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

}

// ────────────────────────────────────────────────
// Page display
// ────────────────────────────────────────────────

$PAGE->set_url('/mod/customassessment/manage/review.php', ['id' => $id]);
$PAGE->set_title(get_string('reviewquestions', 'customassessment') . ' - ' . $assessment->name);
$PAGE->set_heading($assessment->name);
$PAGE->set_context($context);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('reviewgeneratedquestions', 'customassessment'), 2);

echo '<p><strong>' . get_string('assessment', 'customassessment') . ':</strong> ' . format_string($assessment->name) . '</p>';

$topic = $DB->get_field('customassessment_topics', 'topicname', ['id' => $assessment->topicid]);
echo '<p><strong>' . get_string('topic', 'customassessment') . ':</strong> ' . format_string($topic) . '</p>';

if ($isfrozen) {
    echo $OUTPUT->notification(
        get_string('frozenwarning', 'customassessment'),
        \core\output\notification::NOTIFY_WARNING
    );
}

$questions = $DB->get_records(
    'customassessment_questions',
    ['assessmentid' => $assessment->id],
    'id ASC'
);

if (!$questions) {
    echo $OUTPUT->notification(get_string('noquestionsyet', 'customassessment'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Form
echo '<form method="post" action="' . $PAGE->url . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

$disabled_attr = $isfrozen ? ' disabled' : '';
$edit_buttons_visible = !$assessment->frozen;
$i = 1;
foreach ($questions as $q) {

    $badge_class = ($q->status === 'accepted') ? 'badge-success' : 'badge-warning';
    if ($q->status === 'rejected') $badge_class = 'badge-danger';

    echo '<div class="card mb-3">';
    echo '<div class="card-header d-flex justify-content-between align-items-center">';
echo '<strong>' . get_string('question') . ' ' . $i . '</strong>';

    echo '<span class="badge ' . $badge_class . '">' . ucfirst($q->status) . '</span>';
    echo '</div>';

    echo '<div class="card-body">';

    // Question text - view / edit
    echo '<div id="view_q_' . $q->id . '"' . ($edit_buttons_visible ? '' : ' class="d-none"') . '>';
    echo format_text($q->questiontext, FORMAT_HTML);
    echo '</div>';

    echo '<div id="edit_q_' . $q->id . '" class="' . ($edit_buttons_visible ? 'd-none' : '') . '">';
    echo '<textarea name="questiontext[' . $q->id . ']" class="form-control" rows="4"' . $disabled_attr . '>' .
         s($q->questiontext) .
         '</textarea>';
    echo '</div>';

    // Model answer
    echo '<h6 class="mt-3">' . get_string('desiredanswer', 'customassessment') . '</h6>';

    echo '<div id="view_a_' . $q->id . '"' . ($edit_buttons_visible ? '' : ' class="d-none"') . '>';
    echo '<div class="border p-3 bg-light">';
    echo format_text($q->modelanswer, FORMAT_HTML);
    echo '</div>';
    echo '</div>';

    echo '<div id="edit_a_' . $q->id . '" class="' . ($edit_buttons_visible ? 'd-none' : '') . '">';
    echo '<textarea name="modelanswer[' . $q->id . ']" class="form-control" rows="5"' . $disabled_attr . '>' .
         s($q->modelanswer) .
         '</textarea>';
    echo '</div>';

    // Controls
    echo '<div class="mt-3 d-flex align-items-center gap-3 flex-wrap">';

    echo '<div class="custom-control custom-switch">';
    echo '<input type="checkbox" class="custom-control-input" id="qstatus_' . $q->id . '" ' .
         'name="status[' . $q->id . ']" value="accepted" ' .
         (($q->status === 'accepted') ? 'checked' : '') . $disabled_attr . '>';
    echo '<label class="custom-control-label" for="qstatus_' . $q->id . '">' . get_string('accept', 'customassessment') . '</label>';
    echo '</div>';

  if ($edit_buttons_visible) {
    echo '<button type="button" class="btn btn-outline-primary btn-sm"
          onclick="toggleEdit(' . $q->id . ')">'
          . get_string('edit') .
          '</button>';

    echo '<button type="button" class="btn btn-outline-danger btn-sm ml-2"
          onclick="confirmDelete(' . $q->id . ')">'
          . get_string('delete') .
          '</button>';
}
    
    

    echo '</div>';
    echo '</div>'; // card-body
    echo '</div>'; // card

     $i++;
}


if (!$isfrozen) {

    echo '<button type="button" class="btn btn-success mb-3 mt-4" onclick="toggleManualQuestionForm();">';
    echo 'Add Manual Question';
    echo '</button>';

    echo '<div id="manual-question-form" class="card mt-3 d-none">';
    echo '<div class="card-header"><strong>Add Manual Question</strong></div>';
    echo '<div class="card-body">';

    echo '<div id="manual-error" class="alert alert-danger d-none"></div>';

    echo '<div class="form-group mb-3">';
    echo '<label><strong>Question</strong></label>';
    echo '<textarea id="manual_questiontext" name="manual_questiontext" class="form-control" rows="4"></textarea>';
    echo '</div>';

    echo '<div class="form-group mb-3">';
    echo '<label><strong>Desired Answer</strong></label>';
    echo '<textarea id="manual_modelanswer" name="manual_modelanswer" class="form-control" rows="6"></textarea>';
    echo '</div>';

    echo '<div class="form-group mb-3">';
    echo '<label><strong>Bloom\'s Level</strong></label>';
    echo '<select name="manual_bloomslevel" class="form-control">';
    echo '<option value="Remembering">Remembering</option>';
    echo '<option value="Understanding">Understanding</option>';
    echo '<option value="Applying">Applying</option>';
    echo '<option value="Analyzing">Analyzing</option>';
    echo '<option value="Evaluating">Evaluating</option>';
    echo '<option value="Creating">Creating</option>';
    echo '</select>';
    echo '</div>';

    echo '<button type="submit" name="action" value="add_manual" class="btn btn-success" onclick="return validateManualQuestion();">';
    echo 'Add Question';
    echo '</button>';

    echo '</div></div>';
}

// Action buttons
echo '<div class="text-center mt-5">';
echo '<div id="regenerating-msg" class="alert alert-info mt-4 d-none text-center">';
echo '<strong>Questions are regenerating…</strong><br>Please wait, this may take a few moments.';
echo '</div>';
if (!$assessment->frozen) {
    echo '<button type="submit" name="action" value="save_statuses" class="btn btn-primary btn-lg px-5" ' . 
     ($isfrozen ? 'disabled' : '') . '>' .
     get_string('savechanges') . '</button> ';

    // Freeze button with confirmation popup
    $confirm_message = get_string('freezetopicconfirm', 'customassessment');
   echo '<button type="submit" 
             name="action" 
             value="freeze" 
             class="btn btn-success btn-lg px-5 ml-3"
             onclick="return confirm(' . json_encode($confirm_message) . ');" ' .
             ($isfrozen ? 'disabled' : '') . '>' .
     get_string('freezetopic', 'customassessment') . '</button>';
}



echo '</form>';
$regenerate_url = new moodle_url('/mod/customassessment/generate_questions.php', [
    'id' => $cm->id
]);

echo '<form method="post" action="' . $regenerate_url . '" class="d-inline" onsubmit="return showRegeneratingMessage();">';

echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="regenerate" value="1">';

echo '<button type="submit" class="btn btn-warning btn-lg px-5 ml-3" ' . ($isfrozen ? 'disabled' : '') . '>';
echo get_string('regenerateall', 'customassessment');
echo '</button>';

echo '</form>';

echo '<form id="delete-form" method="post" action="' . $PAGE->url . '" class="d-none">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="delete_question">';
echo '<input type="hidden" name="qid" id="delete-qid">';
echo '</form>';

echo '</div>';


// JavaScript for inline editing toggle
$PAGE->requires->js_init_code("
    window.toggleEdit = function(qid) {
        document.getElementById('view_q_'+qid).classList.toggle('d-none');
        document.getElementById('edit_q_'+qid).classList.toggle('d-none');
        document.getElementById('view_a_'+qid).classList.toggle('d-none');
        document.getElementById('edit_a_'+qid).classList.toggle('d-none');
    };
");

$PAGE->requires->js_init_code("
    window.showRegeneratingMessage = function() {
        var msg = document.getElementById('regenerating-msg');
        if (msg) {
            msg.classList.remove('d-none');
        }

        // Disable all buttons to prevent double click
        document.querySelectorAll('button').forEach(function(btn) {
            btn.disabled = true;
        });

        return true; // allow form submission
    };
");

$PAGE->requires->js_init_code("
    window.toggleManualQuestionForm = function () {
        document.getElementById('manual-question-form').classList.toggle('d-none');
    };

    window.validateManualQuestion = function () {
        var q = document.getElementById('manual_questiontext').value.trim();
        var a = document.getElementById('manual_modelanswer').value.trim();
        var err = document.getElementById('manual-error');

        if (!q || !a) {
            err.innerText = 'Question and answer are required.';
            err.classList.remove('d-none');
            return false;
        }
        err.classList.add('d-none');
        return true;
    };

");
$PAGE->requires->js_init_code("
    window.confirmDelete = function(qid) {
        if (confirm('Are you sure you want to delete this question? This cannot be undone.')) {
            document.getElementById('delete-qid').value = qid;
            document.getElementById('delete-form').submit();
        }
    };
");
echo $OUTPUT->footer();