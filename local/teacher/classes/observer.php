<?php
namespace local_teacher;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function h5p_statement_received(\mod_h5pactivity\event\statement_received $event) {
        global $DB;

        debugging('H5P observer fired for user ' . $event->userid, DEBUG_DEVELOPER);
        $context = $event->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (!$cm) {
            return;
        }

        $attempt = $DB->get_record_sql("
            SELECT a.id
            FROM {h5pactivity_attempts} a
            WHERE a.h5pactivityid = ? AND a.userid = ?
            ORDER BY a.timemodified DESC
            LIMIT 1
        ", [$cm->instance, $event->userid]);

        if ($attempt) {
            $ip = getremoteaddr();
            $DB->set_field('h5pactivity_attempts', 'ipaddress', $ip, ['id' => $attempt->id]);
        }
    }
}