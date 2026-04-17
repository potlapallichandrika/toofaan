<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // OpenAI API Key – CORRECT CLASS NAME
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_customassessment/openai_api_key',
        'OpenAI API Key',
        'Enter your OpenAI API key (starts with sk-proj-...). Required for AI Smart Grading (o3-mini). Never share this key!',
        ''
    ));

   
}