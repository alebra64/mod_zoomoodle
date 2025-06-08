<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'mod_zoomoodle\observer::user_enrolment_created',
        'includefile' => '/mod/zoomoodle/classes/observer.php',
        'priority'    => 998,
        'internal'    => false
    ],
    [
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'mod_zoomoodle\observer::user_enrolment_updated',
        'includefile' => '/mod/zoomoodle/classes/observer.php',
        'priority'    => 998,
        'internal'    => false
    ]
];
