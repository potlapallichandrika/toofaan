<?php

require_once('../../config.php');  

$id    = required_param('id', PARAM_INT);
$regenerate = optional_param('regenerate', 0, PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

$cm = get_coursemodule_from_id('customassessment', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assessment = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

// Fetch subject and topic
$subject = $DB->get_record('customassessment_subjects', ['id' => $assessment->subjectid]);
$topic   = $DB->get_record('customassessment_topics', ['id' => $assessment->topicid]);

if (!$subject || !$topic) {
    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        'Error: Subject or Topic not found.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$topicname    = $topic->topicname;
$numquestions = $assessment->numquestions ?? 10;

// === Get API Key ===
$apikey = get_config('mod_customassessment', 'openai_api_key');

if (empty($apikey)) {
    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        'OpenAI API key is not configured.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// === Bloom Levels from form ===
$selectedBlooms = $assessment->bloomslevels;
$selectedBloomsArray = array_filter(array_map('trim', explode(',', $selectedBlooms)));

if (!empty($selectedBloomsArray)) {

    $allowedBlooms = implode(', ', $selectedBloomsArray);

    $bloomText = "IMPORTANT RULE:
You MUST generate questions using ONLY the following Bloom's Taxonomy levels:
{$allowedBlooms}

DO NOT use any other Bloom's levels.
If a level outside this list is used, the response will be rejected.";

} else {

    $bloomText = "You may use all Bloom's Taxonomy levels:
Remembering, Understanding, Applying, Analyzing, Evaluating, Creating.";
}

$allowedBloomsLower = array_map('strtolower', $selectedBloomsArray);

// === Set status ===
$DB->set_field('customassessment', 'status', 'generating', ['id' => $assessment->id]);

// === GPT System Prompt ===
$systemprompt = <<<EOT
You are an expert examiner designing high-quality theory questions for B.Tech Computer Science students.

Generate exactly {$numquestions} unique, conceptual theory questions with detailed model answers on the topic: "{$topicname}".

{$bloomText}
Bloom’s Taxonomy Action Verb Guidance (MANDATORY):

- Remembering: define, list, identify, state, name, recall
- Understanding: explain, describe, summarize, interpret, distinguish
- Applying: apply, illustrate, demonstrate, show, use
- Analyzing: analyze, compare, contrast, differentiate, examine
- Evaluating: evaluate, justify, assess, critique, defend
- Creating: design, propose, formulate, develop, construct

Rules:
- Each question MUST clearly use an appropriate action verb.
- The action verb MUST match the assigned Bloom’s level.
- Do NOT mix verbs from different Bloom levels.

Strict rules:
- Written theory exam only — NO code, NO diagrams, NO programming.
- Questions answerable in 3–5 paragraphs.
- Answers: 300–350 words.
- Each question MUST belong to EXACTLY ONE Bloom's level from the allowed list.
The bloom_level field MUST exactly match one of the allowed levels.
- No yes/no questions. No duplicates.

Return valid JSON only:
{
  "questions": [
    {
      "question": "Question text",
      "answer": "Detailed answer (300-350 words)",
      "bloom_level": "One of: {$allowedBlooms}",
      "tags": []
    }
  ]
}
EOT;

$payload = [
    'model' => 'gpt-4.1-mini',
    'messages' => [
        ['role' => 'system', 'content' => $systemprompt],
        ['role' => 'user', 'content' => 'Generate the questions now.']
    ],
    'temperature' => 0.6,
    'max_tokens' => 6000
    //'response_format' => 'json'
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apikey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 180,
    CURLOPT_CONNECTTIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($httpcode !== 200 || $response === false) {

    $DB->set_field('customassessment', 'status', 'generation_failed', ['id' => $assessment->id]);

    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        'Failed to contact OpenAI: ' . s($curl_error),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$data = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? '';

$content = trim($content);

// Remove markdown fences if present
$content = preg_replace('/^```json\s*/i', '', $content);
$content = preg_replace('/^```\s*/i', '', $content);
$content = preg_replace('/\s*```$/', '', $content);

// Extract JSON safely (in case text exists before/after)
$start = strpos($content, '{');
$end   = strrpos($content, '}');

if ($start !== false && $end !== false) {
    $content = substr($content, $start, $end - $start + 1);
}

$questionsjson = json_decode($content, true);

if (
    empty($questionsjson) ||
    !is_array($questionsjson) ||
    !isset($questionsjson['questions']) ||
    !is_array($questionsjson['questions'])
) {
    debugging('RAW AI CONTENT: ' . $content, DEBUG_DEVELOPER);

    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        'Invalid AI response (JSON parsing failed).',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$questions = $questionsjson['questions'];

// === Delete only GPT questions on regenerate ===
if ($regenerate) {
    $DB->delete_records('customassessment_questions', [
        'assessmentid' => $assessment->id,
        'source'       => 'gpt'
    ]);
}

// === Insert questions ===
$inserted = 0;

foreach ($questions as $q) {

    if (empty($q['question']) || empty($q['answer'])) {
        continue;
    }

    $aiBloom = strtolower(trim($q['bloom_level'] ?? ''));

if (!empty($allowedBloomsLower) && !in_array($aiBloom, $allowedBloomsLower)) {
    continue;
}

$map = [
    'remembering'   => 'remember',
    'understanding' => 'understand',
    'applying'      => 'apply',
    'analyzing'     => 'analyze',
    'evaluating'    => 'evaluate',
    'creating'      => 'create',
];

    $record = new stdClass();
    $record->assessmentid = $assessment->id;
    $record->questiontext = clean_param($q['question'], PARAM_RAW);
    $record->modelanswer  = clean_param($q['answer'], PARAM_RAW);
    //$record->bloomslevel  = ucfirst($aiBloom);
    $record->bloomslevel = $map[$aiBloom] ?? strtolower($aiBloom);
    $record->status       = 'rejected';
    $record->source       = 'gpt';
    $record->timecreated  = time();

    $DB->insert_record('customassessment_questions', $record);
    $inserted++;
}

// 🚨 If no valid questions inserted
if ($inserted === 0) {
    $DB->set_field('customassessment', 'status', 'generation_failed', ['id' => $assessment->id]);

    redirect(
        new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
        'AI did not generate questions matching selected Bloom levels. Please try again.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// === Final status ===
$DB->set_field('customassessment', 'status', 'questions_generated', ['id' => $assessment->id]);

$levelsText = !empty($allowedBlooms) ? $allowedBlooms : 'all Bloom\'s levels';

redirect(
    new moodle_url('/mod/customassessment/view.php', ['id' => $id]),
    "Successfully generated questions using Bloom's levels: {$levelsText}",
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
