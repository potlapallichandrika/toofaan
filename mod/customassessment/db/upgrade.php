<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_customassessment_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026010200) {

        // Create customassessment_subjects table
        $table = new xmldb_table('customassessment_subjects');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('subjectname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('subjectname_idx', XMLDB_INDEX_NOTUNIQUE, ['subjectname']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create customassessment_topics table
        $table = new xmldb_table('customassessment_topics');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('subjectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('topicname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // This foreign key automatically creates an index — no need to add manual one
        $table->add_key('subject_fk', XMLDB_KEY_FOREIGN, ['subjectid'], 'customassessment_subjects', ['id']);

        
        $table->add_index('topicname_idx', XMLDB_INDEX_NOTUNIQUE, ['topicname']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026010200, 'customassessment');
    }

    return true;
}