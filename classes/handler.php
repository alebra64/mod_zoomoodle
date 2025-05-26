<?php
// File: mod/zoomoodle/classes/handler.php
// License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

class handler {

    /**
     * Stub per compatibilità: evita errori se viene chiamato handler::events().
     *
     * @return array
     */
    public static function events(): array {
        return [];
    }

    /**
     * Sincronizza l'iscrizione di un utente Moodle al webinar Zoom.
     *
     * @param int $instanceid id dell'istanza Zoomoodle
     * @param int $userid     id dell'utente Moodle
     * @param int $courseid   id del corso Moodle
     */
    public static function sync_enrolment_to_zoom(int $instanceid, int $userid, int $courseid) {
        global $DB;

        // *** Patch FTP-only: cancello sempre il record precedente ***
        $DB->delete_records('zoomoodle_urls', [
            'zoomoodleid' => $instanceid,
            'userid'      => $userid
        ]);
        // *** Fine patch ***

        // 1) Carica i dati dell'istanza Zoomoodle
        $zm = $DB->get_record('zoomoodle', ['id' => $instanceid], '*', MUST_EXIST);

        // 2) Prendi il course module
        $cm = get_coursemodule_from_instance('zoomoodle', $instanceid, 0, false, MUST_EXIST);

        // 3) Ottieni token OAuth
        $token = \mod_zoomoodle\api::get_token();
        if (empty($token)) {
            error_log(date('c') . " - No OAuth token available\n");
            throw new \moodle_exception('err_no_token', 'mod_zoomoodle');
        }

        // 4) Registra l'utente
        list($zoomuser, $joinurl) = api::register_user_to_webinar(
            $token,
            $zm->webinar_id,
            $userid
        );

        // Log risposta Zoom
        error_log(date('c') . " - Zoom registrant={$zoomuser}, joinurl={$joinurl}\n");

        if (empty($zoomuser) || empty($joinurl)) {
            return;
        }

        // 5) Aggiorna o inserisci record
        $record = $DB->get_record('zoomoodle_urls', [
            'zoomoodleid' => $instanceid,
            'userid'      => $userid
        ], '*', IGNORE_MISSING);

        if ($record) {
            $record->zoom_user = $zoomuser;
            $record->zoom_url  = $joinurl;
            $record->course    = $courseid;
            $DB->update_record('zoomoodle_urls', $record);
        } else {
            $new = new \stdClass();
            $new->zoomoodleid = $instanceid;
            $new->module      = $cm->id;
            $new->userid      = $userid;
            $new->user        = $userid;
            $new->course      = $courseid;
            $new->zoom_user   = $zoomuser;
            $new->zoom_url    = $joinurl;
            $DB->insert_record('zoomoodle_urls', $new);
        }
    }

    /**
     * Recupera il token OAuth valido, sfruttando la cache di api::get_token().
     *
     * @return string|null
     */
    public static function get_token(): ?string {
        $config = get_config('mod_zoomoodle');
        if (!empty($config->apitoken)) {
            return $config->apitoken;
        }
        return \mod_zoomoodle\api::get_token();
    }

    /**
     * Handler per i webhook di Zoom (es. deregistrazione).
     *
     * @param array $data Dati ricevuti dal webhook
     */
    public static function handler_webhook(array $data) {
        global $DB;

        if (!empty($data['event']) && $data['event'] === 'webinar.registrant_removed') {
            $instanceid = (int)$data['payload']['object']['id'];
            $zoomuserid = $data['payload']['object']['registrant']['id'];

            $record = $DB->get_record('zoomoodle_urls', [
                'zoomoodleid' => $instanceid,
                'zoom_user'   => $zoomuserid
            ], '*', IGNORE_MISSING);

            if ($record) {
                $DB->delete_records('zoomoodle_urls', ['id' => $record->id]);
                error_log(date('c') . " - Removed registrant {$zoomuserid} for instance {$instanceid}\n");
            }
        }
    }
}
