<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Indicates Moodle features supported by this plugin.
 */
function customassessment_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Add a new customassessment instance.
 */
function customassessment_add_instance($data, $mform) {
    global $DB, $USER;

    $record = new stdClass();
    $record->courseid       = $data->course;
    $record->name           = trim($data->name);
    
    // FIX: Extract value from grouped select (it's an array: subjectid[0])
    $record->subjectid      = $data->subjectid[0] ?? 0;
    $record->topicid        = $data->topicid[0] ?? 0;
    
    $record->numquestions   = $data->numquestions;
    if (!empty($data->bloomslevels)) {
        $blooms = is_array($data->bloomslevels)
            ? $data->bloomslevels
            : [$data->bloomslevels];

        $record->bloomslevels = implode(',', $blooms);
    } else {
        $record->bloomslevels = '';
    }

    $record->status         = 'created';
    $record->createdby      = $USER->id;
    $record->timecreated    = time();
    $record->timemodified   = time();

    return $DB->insert_record('customassessment', $record);
}

/**
 * Update an existing instance
 */
function customassessment_update_instance($data, $mform) {
    global $DB;

    $record = new stdClass();
    $record->id             = $data->instance;
    $record->name           = trim($data->name);
    
    // FIX: Same here — extract [0] from grouped fields
    $record->subjectid      = $data->subjectid[0] ?? 0;
    $record->topicid        = $data->topicid[0] ?? 0;
    
    $record->numquestions   = $data->numquestions;
    if (!empty($data->bloomslevels)) {
        $blooms = is_array($data->bloomslevels)
            ? $data->bloomslevels
            : [$data->bloomslevels];

        $record->bloomslevels = implode(',', $blooms);
    } else {
        $record->bloomslevels = '';
    }

    $record->timemodified   = time();


    return $DB->update_record('customassessment', $record);
}

/**
 * Delete a customassessment instance.
 */
function customassessment_delete_instance($id) {
    global $DB;

    // Delete main record
    $DB->delete_records('customassessment', ['id' => $id]);

    // Optional: delete related data
    $DB->delete_records('customassessment_questions', ['assessmentid' => $id]);
    $DB->delete_records('customassessment_attempt', ['assessmentid' => $id]);

    return true;
}

function mod_customassessment_output_fragment_new_subject_form($args) {
    $form = new \mod_customassessment\form\subject_add_form(null, [], 'post', '', ['class'=>'ignoredirty']);
    return $form->render();
}

function mod_customassessment_output_fragment_new_topic_form($args) {
    $args = (object)$args;
    $form = new \mod_customassessment\form\topic_add_form(null, $args, 'post', '', ['class'=>'ignoredirty']);
    return $form->render();
}


