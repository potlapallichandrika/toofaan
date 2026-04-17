<?php
define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_login();
require_sesskey();

global $DB;

$name = trim(required_param('subjectname', PARAM_TEXT));

if ($name === '') {
    echo json_encode([
        'success' => false,
        'error'   => get_string('required')
    ]);
    exit;
}

/* ===== DUPLICATE CHECK (case-insensitive) ===== */
$sql = "SELECT id
          FROM {customassessment_subjects}
         WHERE LOWER(subjectname) = LOWER(?)";

if ($DB->record_exists_sql($sql, [$name])) {
    echo json_encode([
        'success' => false,
        'error'   => get_string('subjectalreadyexists', 'customassessment')
    ]);
    exit;
}

/* ===== INSERT ONLY IF NOT EXISTS ===== */
$id = $DB->insert_record('customassessment_subjects', [
    'subjectname' => $name,
    'timecreated' => time()
]);

echo json_encode([
    'success' => true,
    'id'      => $id,
    'name'    => $name
]);
exit;
