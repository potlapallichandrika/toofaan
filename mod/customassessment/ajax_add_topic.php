<?php
define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_login();
require_sesskey();

global $DB;

$topicname = trim(required_param('topicname', PARAM_TEXT));
$subjectid = required_param('subjectid', PARAM_INT);

if ($topicname === '' || $subjectid <= 0) {
    echo json_encode([
        'success' => false,
        'error'   => get_string('required')
    ]);
    exit;
}

/* ===== DUPLICATE CHECK (topic + subject) ===== */
$sql = "SELECT id
          FROM {customassessment_topics}
         WHERE subjectid = ?
           AND LOWER(topicname) = LOWER(?)";

if ($DB->record_exists_sql($sql, [$subjectid, $topicname])) {
    echo json_encode([
        'success' => false,
        'error'   => get_string('topicalreadyexists', 'customassessment')
    ]);
    exit;
}

/* ===== INSERT ONLY IF NOT EXISTS ===== */
$id = $DB->insert_record('customassessment_topics', [
    'topicname'    => $topicname,
    'subjectid'    => $subjectid,
    'timecreated'  => time()
]);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'id'      => $id,
    'name'    => $topicname
]);
exit;
