<?php
namespace mod_customassessment\task;

use core\task\scheduled_task;

defined('MOODLE_INTERNAL') || die();

class process_evaluation_queue extends scheduled_task {

    /* ============================================================
     * LOG BUFFER (FOR AJAX RESPONSE + CRON)
     * ============================================================ */
    private $logbuffer = [];

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $line = "{$timestamp} | {$message}";
        $this->logbuffer[] = $line;

        // Keep cron output
        mtrace($message);
    }
  public function clear_logs() {
    $this->logbuffer = [];
}

    public function get_logs() {
        return $this->logbuffer;
    }

  

    /* ============================================================
     * TASK NAME
     * ============================================================ */
    public function get_name() {
    return 'Evaluate custom assessment answers (Bloom taxonomy)';
}

    /* ============================================================
     * REQUIRED BY scheduled_task
     * ============================================================ */
    public function execute() {
        $this->log("Scheduled task execute() called (AJAX uses execute_attempt)");
    }

    private function remember_prompt() {
    return "You are an examiner. This question tests REMEMBERING.
Assess factual accuracy and recall only. Do not reward interpretation.
Give a score from 0 to 10.";
}

private function understand_prompt() {
    return "You are an examiner. This question tests UNDERSTANDING.
Assess explanation, interpretation, and clarity in the student's answer.
Give a score from 0 to 10.";
}

private function apply_prompt() {
    return "You are an examiner. This question tests APPLICATION.
Assess whether concepts are correctly applied to the situation.
Give a score from 0 to 10.";
}

private function analyze_prompt() {
    return "You are an examiner. This question tests ANALYSIS.
Assess logical structure, relationships, and reasoning depth.
Give a score from 0 to 10.";
}

private function evaluate_prompt() {
    return "You are an examiner. This question tests EVALUATION.
Assess judgment quality, justification, and critical reasoning.
Give a score from 0 to 10.";
}

private function create_prompt() {
    return "You are an examiner. This question tests CREATION.
Assess originality, coherence, and correctness of the created response.
Give a score from 0 to 10.";
}
    /* ============================================================
     * MAIN EVALUATION METHOD
     * ============================================================ */
    public function execute_attempt($attemptid) {
        global $DB;

       $this->log("Evaluation started for attempt {$attemptid}");

        // Atomic lock
        $updated = $DB->execute(
            "UPDATE {customassessment_attempt}
             SET status = 'processing'
             WHERE id = ? AND status = 'submitted'",
            [$attemptid]
        );

        if (!$updated) {
            $this->log("Attempt {$attemptid} already processed or locked");
            return;
        }

        $attempt = $DB->get_record('customassessment_attempt', ['id' => $attemptid]);
        if (!$attempt) {
            $this->log("Attempt {$attemptid} not found");
            return;
        }

        $apikey = get_config('mod_customassessment', 'openai_api_key');
        $this->log("OpenAI key found: " . (!empty($apikey) ? 'YES' : 'NO'));

        if (empty($apikey)) {
            return;
        }

        $answers = $DB->get_records('customassessment_answer', ['attemptid' => $attempt->id]);
        if (empty($answers)) {
            $DB->set_field('customassessment_attempt', 'status', 'evaluated', ['id' => $attempt->id]);
            $this->log("No answers found, attempt marked evaluated");
            return;
        }

        $this->log("Found " . count($answers) . " answers");

        $total_points    = 0;
        $evaluated_count = 0;

        foreach ($answers as $ans) {

            /* ================= SKIPPED QUESTION ================= */
            if ($ans->answer === '[SKIPPED]') {

                if (!$ans->evaluated) {
                    $DB->update_record('customassessment_answer', (object)[
                        'id'           => $ans->id,
                        'score'        => 0,
                        'feedback'     => 'Question skipped by student',
                        'evaluated'    => 1,
                        'timemodified' => time()
                    ]);
                }

                $evaluated_count++;
                $this->log("Answer #{$ans->id} skipped → 0 marks");
                continue;
            }

            /* ================= ALREADY EVALUATED ================= */
            if ($ans->evaluated) {
                $evaluated_count++;
                $total_points += (int)$ans->score;
                continue;
            }

            $question = $DB->get_record(
                'customassessment_questions',
                ['id' => $ans->questionid]
            );

            if (!$question || empty($question->modelanswer)) {
                $this->log("Model answer missing for answer #{$ans->id}");
                continue;
            }

            if (empty($question->bloomslevel)) {
    $this->log("Bloom level missing for question {$question->id}, defaulting to Remember");
    $question->bloomslevel = 'remember'; // hard fallback
}
$validlevels = ['remember','understand','apply','analyze','evaluate','create'];
if (!in_array(strtolower($question->bloomslevel), $validlevels)) {
    $this->log(
        "Invalid Bloom level '{$question->bloomslevel}' for question {$question->id}, defaulting to Remember"
    );
    $question->bloomslevel = 'remember';
}

$this->log(
    "Evaluating answer #{$ans->id} | Question {$question->id} | Bloom level = {$question->bloomslevel}"
);
            $prompt = $this->build_full_bloom_prompt($question, $ans);

            $this->log("Calling OpenAI for answer #{$ans->id}");
            $result = $this->call_openai($apikey, $prompt);

            if ($result && isset($result['rating']) && is_numeric($result['rating'])) {

                $score = max(0, min(10, (int)$result['rating']));

                $DB->update_record('customassessment_answer', (object)[
                    'id'           => $ans->id,
                    'score'        => $score,
                    'feedback'     => $result['explanation'] ?? 'Auto-evaluated',
                    'evaluated'    => 1,
                    'timemodified' => time()
                ]);

                $total_points += $score;
                $evaluated_count++;

                $this->log("Success: answer #{$ans->id} rating = {$score}");
            } else {
    $this->log("Failed: OpenAI invalid response for answer #{$ans->id}");
    $this->mark_failed_attempt_answer($ans);
    $evaluated_count++;
}
        }

        /* ================= FINALIZE ATTEMPT ================= */

        $total_questions = count($answers);

       if ($evaluated_count === $total_questions) {

    // ✅ Count only questions shown to THIS student (answered + skipped)
    $total_questions = $DB->count_records(
        'customassessment_answer',
        ['attemptid' => $attempt->id]
    );

    $max = $total_questions * 10;

    $final_score = ($max > 0)
        ? round(($total_points / $max) * 100, 1)
        : 0;

    // 🔍 DEBUG LOGS
    $this->log("Score calculation debug:");
    $this->log("→ Attempt ID        = {$attempt->id}");
    $this->log("→ Total questions   = {$total_questions} (attempt-based)");
    $this->log("→ Evaluated count   = {$evaluated_count}");
    $this->log("→ Total points      = {$total_points}");
    $this->log("→ Max points        = {$max}");
    $this->log("→ Final percentage  = {$final_score}%");

    $DB->update_record('customassessment_attempt', (object)[
        'id'     => $attempt->id,
        'status' => 'evaluated',
        'score'  => $final_score
    ]);

    $this->log("Attempt {$attempt->id} evaluated → {$final_score}%");
}


        /* ================= PUBLISH ASSESSMENT ================= */

        $pending_any = $DB->record_exists_select(
            'customassessment_attempt',
            "assessmentid = ? AND status IN ('submitted','processing')",
            [$attempt->assessmentid]
        );

        if (!$pending_any) {
            $DB->set_field(
                'customassessment',
                'status',
                'result_published',
                ['id' => $attempt->assessmentid]
            );
            $this->log("All attempts evaluated → assessment published");
        }

        /* ================= QUEUE CLEANUP ================= */

        $queue_items = $DB->get_records_select(
            'customassessment_eva_queue',
            "ref_id = ? AND type = 'student_attempt' AND status = 'pending'",
            [$attempt->id]
        );

        foreach ($queue_items as $item) {
            $this->mark_processed($item);
        }

        $this->log("Task finished");
    }

    /* ============================================================
     * PROMPT BUILDER
     * ============================================================ */
    private function build_full_bloom_prompt($question, $ans) {

        $level = $question->bloomslevel;

        $prompts = [
            'remember' => $this->remember_prompt(),
            'understand' => $this->understand_prompt(),
            'apply' => $this->apply_prompt(),
            'analyze' => $this->analyze_prompt(),
            'evaluate' => $this->evaluate_prompt(),
            'create' => $this->create_prompt()
        ];

       $system = $prompts[$level] ?? $prompts['remember'];

        return <<<EOT
{$system}

QUESTION:
{$question->questiontext}

MODEL ANSWER:
{$question->modelanswer}

STUDENT ANSWER:
{$ans->answer}

Return JSON only:
{
  "rating": <integer 0-10>,
  "explanation": "<short justification>"
}
EOT;
    }

    /* ============================================================
     * OPENAI CALL
     * ============================================================ */
    private function call_openai($apikey, $prompt) {

        $ch = curl_init('https://api.openai.com/v1/chat/completions');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'max_tokens' => 800
            ]),
            CURLOPT_TIMEOUT => 180,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200 || !$response) {
            return false;
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            return false;
        }

        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $json;
    }
     //ANSWER FAILURE HANDLER 
    private function mark_failed_attempt_answer($ans) {
        global $DB;

        $DB->update_record('customassessment_answer', (object)[
            'id'           => $ans->id,
            'evaluated'    => 1,
            'score'        => 0,
            'feedback'     => 'Evaluation failed. Please contact instructor.',
            'timemodified' => time()
        ]);

        $this->log("Answer #{$ans->id} marked failed → 0 marks");
    }


    /* ============================================================
     * QUEUE HELPERS
     * ============================================================ */
    private function mark_processed($item) {
        global $DB;

        $DB->update_record('customassessment_eva_queue', (object)[
            'id'           => $item->id,
            'status'       => 'processed',
            'processed_at' => time()
        ]);

        $this->log("Queue item #{$item->id} marked processed");
    }

    private function mark_failed($item) {
        global $DB;

        $DB->execute(
            "UPDATE {customassessment_eva_queue}
             SET failcount = failcount + 1,
                 status = IF(failcount >= 3, 'failed', 'pending')
             WHERE id = ?",
            [$item->id]
        );

        $this->log("Queue item #{$item->id} marked failed");
    }
}
