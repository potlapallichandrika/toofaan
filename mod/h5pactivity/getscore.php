<?php
// File: mod/h5pactivity/getscore.php

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

// Get course module
$cm = get_coursemodule_from_id('h5pactivity', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($cm->course, false, $cm);
require_capability('mod/h5pactivity:view', $context);

// Get latest attempt
global $DB, $USER;

$sql = "
    SELECT ar.rawscore, ar.maxscore
    FROM {h5pactivity_attempts} a
    JOIN {h5pactivity_attempts_results} ar ON ar.attemptid = a.id
    WHERE a.h5pactivityid = ? AND a.userid = ?
    ORDER BY a.timemodified DESC
    LIMIT 1
";

$record = $DB->get_record_sql($sql, [$cm->instance, $USER->id]);

// Return JSON
if ($record && $record->maxscore > 0) {
    $percentage = round(($record->rawscore / $record->maxscore) * 100);
    echo json_encode(['score' => $percentage, 'max' => 100]);
} else {
    echo json_encode(['score' => 0, 'max' => 100]);
}

// STOP ALL OUTPUT
die();