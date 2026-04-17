<?php
define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);

require_once('../../../config.php');

require_login();
require_sesskey();

ob_start();
header('Content-Type: application/json; charset=utf-8');

$courseid = required_param('courseid', PARAM_INT);
$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context  = context_course::instance($courseid);

/* ✅ Filter attempts by COURSE */
$attempts = $DB->get_records_sql(
    "SELECT ca.*
     FROM {customassessment_attempt} ca
     JOIN {customassessment} c ON c.id = ca.assessmentid
     WHERE ca.status IN ('submitted','processing')
       AND c.courseid = ?
     ORDER BY ca.id ASC",
    [$courseid]
);

if (empty($attempts)) {
    ob_clean();
    echo json_encode([
        'status'  => 'info',
        'message' => 'No pending attempts to evaluate.',
        'logs'    => []
    ]);
    exit;
}

require_once($CFG->dirroot . '/mod/customassessment/classes/task/process_evaluation_queue.php');

$alllogs = [];

try {
    $task = new \mod_customassessment\task\process_evaluation_queue();

    foreach ($attempts as $attempt) {
        $task->execute_attempt($attempt->id);
        $alllogs = array_merge($alllogs, $task->get_logs());
        $task->clear_logs();
    }

    ob_clean();
    echo json_encode([
        'status'  => 'success',
        'message' => 'Evaluation completed',
        'logs'    => $alllogs
    ]);
    exit;

} catch (Exception $e) {

    ob_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Evaluation failed',
        'error'   => $e->getMessage(),
        'logs'    => $alllogs
    ]);
    exit;
}
