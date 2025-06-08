<?php
// File: mod/zoomoodle/classes/observer.php

namespace mod_zoomoodle;
defined('MOODLE_INTERNAL') || die();

/**
 * Event observer: inoltra solo gli eventi di creazione/aggiornamento,
 * ignorando la cancellazione per non rompere le risposte JSON di Moodle.
 */
class observer {

    /**
     * Quando si crea un'iscrizione in Moodle
     *
     * @param \core\event\user_enrolment_created $event
     * @return void
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        handler::events($event);
    }

    /**
     * Quando si aggiorna un'iscrizione in Moodle
     *
     * @param \core\event\user_enrolment_updated $event
     * @return void
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        handler::events($event);
    }

    /**
     * Quando si cancella un'iscrizione in Moodle:
     * NON invochiamo più il handler per evitare output HTML/JSON invalidi.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return void
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        // intentionally empty
    }
}

