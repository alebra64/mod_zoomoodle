<?php
// File: mod/zoomoodle/db/events.php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'mod_zoomoodle\observer::user_enrolment_created',
        'includefile' => '/mod/zoomoodle/classes/observer.php',
        'internal'    => false,
        'priority'    => 998,
        'internal'    => false
    ],
    [
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'mod_zoomoodle\observer::user_enrolment_updated',
        'includefile' => '/mod/zoomoodle/classes/observer.php',
        'priority'    => 998,
        'internal'    => false
    ],
    // rimosso observer per user_enrolment_deleted
];
