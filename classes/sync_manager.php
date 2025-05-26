<?php
// File: mod/zoomoodle/classes/sync_manager.php
// License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

// Include la libreria delle completion
require_once($CFG->libdir . '/completionlib.php');

class sync_manager {

    /**
     * Esegue la sincronizzazione dei report Zoom e applica la completion
     */
    public static function run(): void {
        global $DB, $CFG;

        debugging('Zoomoodle DEBUG: sync_manager::run() iniziato', DEBUG_DEVELOPER);

        // Prendi tutte le attività Zoomoodle con sync abilitato
        $instances = $DB->get_records('zoomoodle', ['enablesync' => 1]);
        debugging('Zoomoodle DEBUG: istanze con enablesync=1 trovate: ' . count($instances), DEBUG_DEVELOPER);

        foreach ($instances as $instance) {
            // Prepara course, cm e completion_info
            $cm     = get_coursemodule_from_instance('zoomoodle', $instance->id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
            $comp   = new \completion_info($course);

            // Calcola la soglia in secondi
            $thresholdsec = intval($instance->duration) * 60
                          * (intval($instance->attendance_threshold) / 100);
            debugging('Zoomoodle DEBUG: webinarid=' . $instance->webinar_id . ' soglia in secondi=' . $thresholdsec, DEBUG_DEVELOPER);

            // Recupera i partecipanti dal report Zoom
            $participants = api::get_webinar_participants($instance->webinar_id);
            debugging('Zoomoodle DEBUG: recuperati ' . count($participants) . ' partecipanti per webinar ' . $instance->webinar_id, DEBUG_DEVELOPER);

            foreach ($participants as $p) {
                // Trova l’utente Moodle via email
                $user = $DB->get_record('user', ['email' => $p['user_email']], 'id', IGNORE_MISSING);
                if (!$user) {
                    debugging('Zoomoodle DEBUG: nessun utente Moodle con email ' . $p['user_email'], DEBUG_DEVELOPER);
                    continue;
                }
                $duration = intval($p['duration']); // in secondi
                debugging('Zoomoodle DEBUG: partecipante userid=' . $user->id . ' durata=' . $duration . 's', DEBUG_DEVELOPER);

                // Se supera la soglia, marca completion
                if ($duration >= $thresholdsec) {
                    debugging('Zoomoodle DEBUG: utente ' . $user->id . ' supera soglia (' . $duration . ' >= ' . $thresholdsec . ') → update_state()', DEBUG_DEVELOPER);
                    $comp->update_state(
                        $cm,
                        COMPLETION_COMPLETE,
                        $user->id
                    );
                } else {
                    debugging('Zoomoodle DEBUG: utente ' . $user->id . ' NON supera soglia (' . $duration . ' < ' . $thresholdsec . ')', DEBUG_DEVELOPER);
                }
            }
        }

        debugging('Zoomoodle DEBUG: sync_manager::run() terminato', DEBUG_DEVELOPER);
    }
}
