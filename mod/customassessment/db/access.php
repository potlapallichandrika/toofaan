<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'mod/customassessment:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'mod/customassessment:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        ]
        ],
            'mod/customassessment:managesubjects' => [
        'captype'    => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,  // optional
        ],
        'riskbitmask' => RISK_XSS,
    ],
    'mod/customassessment:managetopics' => [
        'captype'    => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
        'riskbitmask' => RISK_XSS,
    ],

    
    'mod/customassessment:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ]
    ],

    'mod/customassessment:attempt' => [
    'captype' => 'read',
    'contextlevel' => CONTEXT_MODULE,
    'archetypes' => [
        'student' => CAP_ALLOW,
    ]
]

    
];

