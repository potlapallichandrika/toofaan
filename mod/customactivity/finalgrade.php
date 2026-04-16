<?php
// finalgrade.php - AI grades the full submission
require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');

$cmid    = required_param('id', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM); // Required for security

$cm       = get_coursemodule_from_id('customactivity', $cmid, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('customactivity', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey(); // Validates the sesskey

// Get questions and student answers
$questions = $DB->get_records('customactivity_questions', ['customactivityid' => $activity->id], 'qno ASC');
$submissions = $DB->get_records('customactivity_submissions', [
    'customactivityid' => $activity->id,
    'userid'           => $USER->id
]);

if (empty($submissions)) {
    redirect(new moodle_url('/mod/customactivity/view.php', ['id' => $cmid]),
        'No answers found.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// // Build prompt for AI
// $prompt = "You are a strict but fair university professor grading a student's full submission.\n\n";

// foreach ($questions as $q) {
//     // Properly fetch student's answer for this question
//     $student_answer = "(no answer)";
//     foreach ($submissions as $s) {
//         if ($s->questionid == $q->id) {
//             $student_answer = $s->tempsave;
//             break;
//         }
//     }

//     $prompt .= "Question {$q->qno}:\n{$q->questiontext}\n\n";
//     $prompt .= "Model Answer:\n" . trim($q->modelanswer) . "\n\n";
//     $prompt .= "Student's Answer:\n{$student_answer}\n\n";
//     $prompt .= "----------------------------------------\n\n";
// }

// $prompt .= "Grade the ENTIRE submission and give ONE final score from 0 to 100.\n";
// $prompt .= "Use this rubric exactly:\n";
// $prompt .= "- 100 = Perfect: contains all key concepts, accurate, well-explained\n";
// $prompt .= "- 90–95 = Excellent: minor omission or slightly incomplete\n";
// $prompt .= "- 70–85 = Good: main idea correct but missing some important details\n";
// $prompt .= "- 50–65 = Fair: partially correct, some understanding shown\n";
// $prompt .= "- 30–45 = Poor: very vague, only 1 small part correct\n";
// $prompt .= "- 0–25 = Completely wrong or irrelevant\n";
// $prompt .= "Then write a very brief feedback of 1–2 sentences.\n";
// $prompt .= "Format:\nScore: XX\nFeedback: [your text]\n";



// Build prompt for AI
$prompt = "You are a strict but fair university professor grading a student's full submission.\n\n";

foreach ($questions as $q) {
    $student_answer = "(no answer)";
    foreach ($submissions as $s) {
        if ($s->questionid == $q->id) {
            $student_answer = trim((string)($s->answer ?? ''));
            if ($student_answer === '') {
                $student_answer = "(no answer)";
            }
            break;
        }
    }

    $prompt .= "Question {$q->qno}:\n{$q->questiontext}\n\n";
    $prompt .= "Model Answer:\n" . trim($q->modelanswer) . "\n\n";
    $prompt .= "Student's Answer:\n{$student_answer}\n\n";
}

$prompt .= "Evaluate the ENTIRE submission as a whole.\n";
$prompt .= "Give ONE final score from 0 to 100 using this rubric exactly:\n";
$prompt .= "- 100 = Perfect: contains all key concepts, accurate, well-explained\n";
$prompt .= "- 90–95 = Excellent: minor omission or slightly incomplete\n";
$prompt .= "- 70–85 = Good: main idea correct but missing some important details\n";
$prompt .= "- 50–65 = Fair: partially correct, some understanding shown\n";
$prompt .= "- 30–45 = Poor: very vague, only 1 small part correct\n";
$prompt .= "- 0–25 = Completely wrong or irrelevant\n\n";

$prompt .= "IMPORTANT RULES:\n";
$prompt .= "- Do NOT explain each question\n";
$prompt .= "- Do NOT mention question numbers\n";
$prompt .= "- Feedback must be MAXIMUM 20 words\n";
$prompt .= "- Feedback must be general, concise, and summary-style\n\n";

$prompt .= "Output format (STRICT):\n";
$prompt .= "Score: XX\n";
$prompt .= "Feedback: <one short sentence>\n";


// Call OpenAI
$apikey = get_config('mod_customactivity', 'openai_api_key');
$score = 0;
$feedback = "AI grading failed.";

if (!empty($apikey)) {
    $payload = [
        "model"       => "gpt-4o-mini",
        "messages"    => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.3,
        "max_tokens"  => 600
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $apikey",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    

    if (!$error && $response) {
        $data = json_decode($response, true);
        $output = $data['choices'][0]['message']['content'] ?? '';

        // Optional: save AI output for debugging
        // file_put_contents(__DIR__.'/ai_debug.txt', $output);

        if (preg_match('/Score:\s*(\d+)/i', $output, $m)) {
            $score = max(0, min(100, (int)$m[1]));
        }
        if (preg_match('/Feedback:\s*(.+)/is', $output, $m)) {
            $feedback = trim($m[1]);
        } else {
            $feedback = trim($output);
        }
    } else {
        $feedback = "AI Error: " . ($error ?: "No response");
    }
}

// Save final grade with questionid = 0
$final = new stdClass();
$final->customactivityid = $activity->id;
$final->userid           = $USER->id;
$final->questionid       = 0; // mark as final grade
$final->grade            = $score;
$final->feedback         = $feedback;
$final->timegraded       = time();
$final->timecreated      = time();

$existing = $DB->get_record('customactivity_submissions', [
    'customactivityid' => $activity->id,
    'userid'           => $USER->id,
    'questionid'       => 0
]);

if ($existing) {
    $final->id = $existing->id;
    $DB->update_record('customactivity_submissions', $final);
} else {
    $DB->insert_record('customactivity_submissions', $final);
}

// Redirect to view.php
redirect(
    new moodle_url('/mod/customactivity/view.php', ['id' => $cmid]),
    "All answers submitted! Your final grade: $score / 100",
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
