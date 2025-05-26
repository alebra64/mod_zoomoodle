<?php
namespace mod_zoomoodle\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use mod_zoomoodle\api;

class sync_enrolments extends scheduled_task {
    /**
     * Name shown in Scheduled tasks.
     */
    public function get_name(): string {
        return get_string('tasksyncenrolments', 'mod_zoomoodle');
    }

    /**
     * Cerca tutte le iscrizioni ancora non replicate su Zoom
     * e le registra via API.
     */
    public function execute() {
        global $DB;

        $sql = "SELECT ue.id       AS ueid,
                       u.id        AS userid,
                       u.email,
                       u.firstname,
                       u.lastname,
                       cm.instance AS instid,
                       cm.id       AS cmid,
                       c.id        AS courseid,
                       z.webinar_id
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON ue.enrolid = e.id
                   AND e.enrol IN ('zoomoodle','manual','self')
                  JOIN {user} u
                    ON u.id = ue.userid
                  JOIN {course_modules} cm
                    ON cm.id = e.customint1
                  JOIN {course} c
                    ON c.id = cm.course
                  JOIN {zoomoodle} z
                    ON z.id = cm.instance
             LEFT JOIN {zoomoodle_urls} zu
                    ON zu.zoomoodleid = z.id
                   AND zu.userid      = u.id
                 WHERE zu.id IS NULL";

        $pending = $DB->get_records_sql($sql);

        foreach ($pending as $p) {
            // 1) Prendo un token valido
            $token = api::get_token();
            if (empty($token)) {
                debugging("Zoomoodle: impossibile ottenere token per enrolment user {$p->userid}", DEBUG_DEVELOPER);
                continue;
            }

            // 2) Provo a registrare l’utente su Zoom
            list($zoomuserid, $joinurl) = api::register_user_to_webinar(
                $token,
                $p->webinar_id,
                $p->userid
            );

            // 3) Se successo, inserisco in zoomoodle_urls
            if (!empty($zoomuserid) && !empty($joinurl)) {
                $rec = (object)[
                    'zoomoodleid' => $p->instid,
                    'module'      => $p->cmid,
                    'userid'      => $p->userid,
                    'user'        => $p->userid,
                    'course'      => $p->courseid,
                    'zoom_user'   => $zoomuserid,
                    'zoom_url'    => $joinurl,
                ];
                $DB->insert_record('zoomoodle_urls', $rec);
                debugging("Zoomoodle: enrolment automatico per user {$p->userid}", DEBUG_DEVELOPER);
            } else {
                debugging("Zoomoodle: enrolment FALLITO per user {$p->userid}", DEBUG_DEVELOPER);
            }
        }
    }
}
