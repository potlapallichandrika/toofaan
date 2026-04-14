<?php
defined('MOODLE_INTERNAL') || die();

class local_teacher_observer {
    public static function h5p_statement_received(\mod_h5pactivity\event\statement_received $event) {
        global $DB;

        // Get attempt ID from context
        $context = $event->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        if (!$cm) return;

        // Get latest attempt for this user + activity
        $attempt = $DB->get_record_sql("
            SELECT a.id 
            FROM {h5pactivity_attempts} a 
            WHERE a.h5pactivityid = ? AND a.userid = ? 
            ORDER BY a.timemodified DESC 
            LIMIT 1
        ", [$cm->instance, $event->userid]);

        if ($attempt) {
            $ip = getremoteaddr();
            debugging("H5P IP SAVE: Attempt {$attempt->id} | User {$event->userid} | IP: $ip", DEBUG_DEVELOPER);
            $DB->set_field('h5pactivity_attempts', 'ipaddress', $ip, ['id' => $attempt->id]);
        }
    }
}