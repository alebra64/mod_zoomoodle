<?php
// This file is part of the Zoomoodle plugin for Moodle - http://moodle.org/
//
// Zoomoodle è free software: puoi ridistribuirlo e/o modificarlo
// secondo i termini della GNU General Public License come pubblicata
// dalla Free Software Foundation: versione 3 o successive.
// Zoomoodle viene distribuito nella speranza che sia utile,
// ma SENZA ALCUNA GARANZIA; senza neanche la garanzia implicita di
// PUBBLICA UTILITÀ. Per i dettagli, consulta la GNU GPL.
// Dovresti aver ricevuto una copia della GNU GPL insieme a Zoomoodle.
// Se non l'hai trovata, vai su <http://www.gnu.org/licenses/>.

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

// Costanti per i voti
define('GRADE_TYPE_NONE', 0);
define('GRADE_TYPE_VALUE', 1);
define('GRADE_TYPE_SCALE', 2);
define('GRADE_TYPE_TEXT', 3);

use moodle_exception;
use coding_exception;
use context_course;

/**
 * Class sync_manager
 *
 * Responsible for fetching Zoom webinar data, calculating attendance thresholds,
 * assigning grades, and marking completion.
 *
 * @package    mod_zoomoodle
 */
class sync_manager {

    /**
     * Entry point per il cron task.
     *
     * Trova tutte le istanze zoomoodle con enablesync=1 e le processa.
     */
    public static function run() {
        debugging('+++ Zoomoodle: ENTERING sync_manager::run() +++', DEBUG_DEVELOPER);

        global $DB;
        // Prendo tutte le istanze dove enablesync = 1.
        $instances = $DB->get_records('zoomoodle', ['enablesync' => 1]);

        debugging('++ Zoomoodle DEBUG: found ' . count($instances) . ' instances with enablesync=1 ++', DEBUG_DEVELOPER);

        foreach ($instances as $instance) {
            // Debug: stampo l'ID che sto per processare.
            debugging("++ Zoomoodle DEBUG: ciclo sulle istanze, attuale \$instance->id = '{$instance->id}' ++", DEBUG_DEVELOPER);

            // Processo TUTTE le istanze senza filtri su 160.
            self::process_instance($instance);
        }

        debugging('++ Zoomoodle DEBUG: sync_manager::run() COMPLETE ++', DEBUG_DEVELOPER);
        return true;
    }

    /**
     * Processa una singola istanza Zoomoodle: prende i partecipanti,
     * calcola punteggi, aggiorna i voti e marca completion.
     *
     * @param \stdClass $instance Record dalla tabella zoomoodle.
     */
    protected static function process_instance($instance) {
        global $DB;

        debugging("+++ Zoomoodle: ENTERING process_instance for instance {$instance->id} +++", DEBUG_DEVELOPER);

        // -----------------------------------------------------------
        // 1. Leggere pagesize (default = 30).
        // -----------------------------------------------------------
        $pagesize = 30;
        if (isset($instance->pagesize) && is_numeric($instance->pagesize) && (int)$instance->pagesize > 0) {
            $pagesize = (int)$instance->pagesize;
        }
        debugging("++ Zoomoodle DEBUG: pagesize utilizzato = {$pagesize} ++", DEBUG_DEVELOPER);

        // -----------------------------------------------------------
        // 2. Recuperare l'ID del webinar come intero.
        // -----------------------------------------------------------
        $webinarid = 0;
        if (isset($instance->webinar_id) && is_numeric($instance->webinar_id)) {
            $webinarid = (int)$instance->webinar_id;
        }
        debugging("++ Zoomoodle DEBUG: webinar_id castato a intero = {$webinarid} ++", DEBUG_DEVELOPER);

        if ($webinarid <= 0) {
            debugging("++ Zoomoodle ERROR: webinar_id non valido (<=0), salto questa istanza ++", DEBUG_DEVELOPER);
            return;
        }

        // -----------------------------------------------------------
        // 3. Recuperare i partecipanti usando la Zoom API.
        // -----------------------------------------------------------
        try {
            $participants = api::get_webinar_participants($webinarid);
        } catch (\Exception $e) {
            debugging("++ Zoomoodle ERROR: Failed to fetch participants for webinar_id={$webinarid}: " . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }

        // ───────────────────────────────────────────────────────────────────
        // 3.a. Trasformiamo eventuali array in oggetti, per uniformità.
        if (is_array($participants)) {
            foreach ($participants as $key => $p) {
                if (is_array($p)) {
                    $participants[$key] = (object)$p;
                }
            }
        }
        // ───────────────────────────────────────────────────────────────────

        // ───────────────────────────────────────────────────────────────────
        // DEBUG TEMPORANEO: stampo struttura di $participants.
        debugging('++ Zoomoodle DEBUG: RAW participants dump: ' . print_r($participants, true), DEBUG_DEVELOPER);
        if (is_array($participants) && count($participants) > 0) {
            $first = reset($participants);
            debugging('++ Zoomoodle DEBUG: First participant object: ' . print_r($first, true), DEBUG_DEVELOPER);
        }
        // ───────────────────────────────────────────────────────────────────

        // Se non ho un array di oggetti, esco.
        if (empty($participants) || !is_array($participants)) {
            debugging("++ Zoomoodle DEBUG: no participants for webinar_id={$webinarid} ++", DEBUG_DEVELOPER);
            return;
        }

        // -----------------------------------------------------------
        // 4. Determinare la "durata ufficiale" del webinar.
        //    Se in $instance->duration c'è un valore positivo, lo uso come secondi;
        //    altrimenti faccio fallback calcolando il max tra join_time e leave_time.
        // -----------------------------------------------------------
        $webinarduration = 0;
        if (isset($instance->duration) && is_numeric($instance->duration) && (int)$instance->duration > 0) {
            // Il form salva direttamente i secondi (p.es. 300 per 5 minuti).
            $webinarduration = (int)$instance->duration;
            debugging("++ Zoomoodle DEBUG: using form-configured duration = {$webinarduration} secondi (collegato da {$instance->duration} nel DB) ++", DEBUG_DEVELOPER);
        } else {
            // Fallback: calcolo il massimo intervallo tra join_time e leave_time.
            $maxduration = 0;
            foreach ($participants as $p) {
                if (isset($p->join_time) && isset($p->leave_time)) {
                    $tsjoin  = strtotime($p->join_time);
                    $tsleave = strtotime($p->leave_time);
                    if ($tsjoin !== false && $tsleave !== false && $tsleave > $tsjoin) {
                        $interval = $tsleave - $tsjoin;
                        if ($interval > $maxduration) {
                            $maxduration = $interval;
                        }
                    }
                }
            }
            $webinarduration = $maxduration;
            debugging("++ Zoomoodle DEBUG: fallback webinar duration (from join/leave) = {$webinarduration} ++", DEBUG_DEVELOPER);
        }

        // Se la durata è ancora zero, esco.
        if ($webinarduration <= 0) {
            debugging("++ Zoomoodle ERROR: webinar duration non valida (=0), salto questa istanza ++", DEBUG_DEVELOPER);
            return;
        }

        // -----------------------------------------------------------
        // 5. Leggere la soglia di frequenza in percentuale (0–100).
        //    Se non c'è, default = 0% (tutti prendono 100).
        //    La converto in secondi: arrotondo( webinarduration * (thresholdraw/100) ).
        // -----------------------------------------------------------
        $thresholdraw = isset($instance->attendance_threshold) && is_numeric($instance->attendance_threshold)
            ? (float)$instance->attendance_threshold
            : 0.0;
        if ($thresholdraw < 0) {
            $thresholdraw = 0.0;
        }
        if ($thresholdraw > 100) {
            $thresholdraw = 100.0;
        }
        $thresholdpercent = $thresholdraw / 100.0;
        $thresholdseconds = (int)round($webinarduration * $thresholdpercent);
        debugging("++ Zoomoodle DEBUG: thresholdraw = {$thresholdraw}%, thresholdpercent = {$thresholdpercent}, thresholdseconds = {$thresholdseconds} ++", DEBUG_DEVELOPER);

        // -----------------------------------------------------------
        // 6. Per ogni partecipante: calcolo permanenza, voto e completion.
        // -----------------------------------------------------------
        foreach ($participants as $p) {
            // Se non c'è user_email, skip.
            if (!isset($p->user_email) || empty($p->user_email)) {
                debugging("++ Zoomoodle DEBUG: elemento partecipanti non valido o senza user_email, skip ++", DEBUG_DEVELOPER);
                continue;
            }

            // Trovo l'utente Moodle corrispondente a quell'email.
            $user = $DB->get_record('user', ['email' => $p->user_email]);
            if (!$user) {
                debugging("++ Zoomoodle DEBUG: nessun utente Moodle trovato per email {$p->user_email}, skip ++", DEBUG_DEVELOPER);
                continue;
            }

            // Calcolo la permanenza in secondi.
            $tsjoin  = strtotime($p->join_time);
            $tsleave = strtotime($p->leave_time);
            if ($tsjoin === false || $tsleave === false || $tsleave <= $tsjoin) {
                debugging("++ Zoomoodle DEBUG: join/leave time non validi per {$p->user_email}, skip ++", DEBUG_DEVELOPER);
                continue;
            }
            $duration = $tsleave - $tsjoin;

            // Calcolo il voto in base alla soglia.
            $grade = ($duration >= $thresholdseconds) ? 100 : 0;
            debugging("++ Zoomoodle DEBUG: user {$user->id} ({$p->user_email}): duration = {$duration}, threshold = {$thresholdseconds}, grade = {$grade} ++", DEBUG_DEVELOPER);

            // Aggiorno il voto nel registro.
            $cm = get_coursemodule_from_instance('zoomoodle', $instance->id);
            if (!$cm) {
                debugging("++ Zoomoodle ERROR: course module not found for instance {$instance->id}, skip ++", DEBUG_DEVELOPER);
                continue;
            }

            $grades = array();
            $grades[$user->id] = new \stdClass();
            $grades[$user->id]->userid = $user->id;
            $grades[$user->id]->rawgrade = $grade;
            zoomoodle_grade_item_update($instance, $grades);

            // Marco il completamento usando la funzione dedicata
            $course = get_course($cm->course);
            zoomoodle_update_completion($course, $cm, $user->id, $grade);
            debugging("++ Zoomoodle DEBUG: updated completion for user {$user->id} with grade {$grade} ++", DEBUG_DEVELOPER);
        }
    }
}
