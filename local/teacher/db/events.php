<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_h5pactivity\event\statement_received',
        'callback'  => '\local_teacher\observer::h5p_statement_received',
        'internal'  => true,
    ],
];
