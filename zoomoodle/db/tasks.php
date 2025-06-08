<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Sincronizza le iscrizioni Moodle → Zoom
        'classname'  => 'mod_zoomoodle\\task\\sync_enrolments',
        'blocking'   => 0,
        // Esegui ogni minuto
        'minute'     => '*/1',
        'hour'       => '*',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
        'disabled'   => 0,
    ],
    [
        // Recupera il report Zoom e marca la completion
        'classname'  => 'mod_zoomoodle\\task\\webinar_graduation',
        'blocking'   => 0,
        // Esegui ogni 5 minuti
        'minute'     => '*/5',
        'hour'       => '*',
        'day'        => '*',
        'month'      => '*',
        'dayofweek'  => '*',
        'disabled'   => 0,
    ],
];
