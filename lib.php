<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU GPL v3 or later.
//
// See http://www.gnu.org/copyleft/gpl.html for details.

/**
 * Library of interface functions and constants for Zoomoodle module.
 *
 * @package     mod_zoomoodle
 * @copyright   2020 (c) Your Name
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

// Core Moodle libraries with absolute paths
require_once($CFG->dirroot . '/lib/completionlib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/lib/grade/grade_grade.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/lib/datalib.php');

// Ensure completion constants are defined
if (!defined('COMPLETION_TRACKING_NONE')) {
    define('COMPLETION_TRACKING_NONE', 0);
}
if (!defined('COMPLETION_TRACKING_MANUAL')) {
    define('COMPLETION_TRACKING_MANUAL', 1);
}
if (!defined('COMPLETION_TRACKING_AUTOMATIC')) {
    define('COMPLETION_TRACKING_AUTOMATIC', 2);
}
if (!defined('COMPLETION_INCOMPLETE')) {
    define('COMPLETION_INCOMPLETE', 0);
}
if (!defined('COMPLETION_COMPLETE')) {
    define('COMPLETION_COMPLETE', 1);
}
if (!defined('COMPLETION_COMPLETE_PASS')) {
    define('COMPLETION_COMPLETE_PASS', 2);
}
if (!defined('COMPLETION_COMPLETE_FAIL')) {
    define('COMPLETION_COMPLETE_FAIL', 3);
}

/**
 * Verifica se il modulo supporta una determinata feature
 * 
 * @param string $feature Nome della feature da verificare
 * @return bool|null true se supportata, null se non supportata
 */
function zoomoodle_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:        // Supporto backup/restore
        case FEATURE_GRADE_HAS_GRADE:       // Supporto voti
        case FEATURE_GROUPINGS:             // Supporto raggruppamenti
        case FEATURE_GROUPMEMBERSONLY:      // Supporto restrizione per gruppi
        case FEATURE_MOD_INTRO:             // Supporto campo introduzione
        case FEATURE_SHOW_DESCRIPTION:      // Supporto descrizione in corso
        case FEATURE_COMPLETION_TRACKS_VIEWS: // Tracciamento visualizzazioni
        case FEATURE_COMPLETION_HAS_RULES:   // Regole di completamento
            return true;
        default:
            return null;
    }
}

/**
 * Crea una nuova istanza del modulo nel database
 * 
 * @param object $moduleinstance Dati del modulo da creare
 * @param object|null $mform Form del modulo (opzionale)
 * @return int ID della nuova istanza
 */
function zoomoodle_add_instance($moduleinstance, $mform = null) {
    global $DB;
    $moduleinstance->timecreated = time();
    $moduleinstance->id = $DB->insert_record('zoomoodle', $moduleinstance);
    zoomoodle_grade_item_update($moduleinstance);
    return $moduleinstance->id;
}

/**
 * Aggiorna un'istanza esistente del modulo
 * 
 * @param object $moduleinstance Dati aggiornati del modulo
 * @param object|null $mform Form del modulo (opzionale)
 * @return bool true se l'aggiornamento è riuscito
 */
function zoomoodle_update_instance($moduleinstance, $mform = null) {
    global $DB;
    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    $DB->update_record('zoomoodle', $moduleinstance);
    zoomoodle_grade_item_update($moduleinstance);
    return true;
}

/**
 * Elimina un'istanza del modulo
 * 
 * @param int $id ID dell'istanza da eliminare
 * @return bool true se l'eliminazione è riuscita
 */
function zoomoodle_delete_instance($id) {
    global $DB;
    if (!$moduleinstance = $DB->get_record('zoomoodle', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('zoomoodle', ['id' => $moduleinstance->id]);
    zoomoodle_grade_item_delete($moduleinstance);
    return true;
}

/**
 * Verifica se una scala specifica è usata da un'istanza del modulo
 */
function zoomoodle_scale_used($moduleinstanceid, $scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('zoomoodle', [
        'id'    => $moduleinstanceid,
        'grade' => -$scaleid
    ]);
}

/**
 * Verifica se una scala è usata da qualsiasi istanza del modulo
 */
function zoomoodle_scale_used_anywhere($scaleid) {
    global $DB;
    return $scaleid && $DB->record_exists('zoomoodle', ['grade' => -$scaleid]);
}

/**
 * Aggiorna o crea un elemento di valutazione per un'istanza del modulo
 * 
 * @param object $moduleinstance Istanza del modulo
 * @param array|null $grades Array di voti da aggiornare
 */
function zoomoodle_grade_item_update($moduleinstance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    // Configura l'elemento di valutazione
    $item = [
        'itemname'  => clean_param($moduleinstance->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'decimals'  => 2,
        'gradepass' => (float)$moduleinstance->attendance_threshold
    ];

    // Imposta il range di voti in base al tipo (valore numerico o scala)
    if ($moduleinstance->grade > 0) {
        $item['grademax'] = $moduleinstance->grade;
        $item['grademin'] = 0;
    } elseif ($moduleinstance->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moduleinstance->grade;
    }

    // Gestisce il reset dei voti se richiesto
    if ($grades === 'reset') {
        $grades = null;
        $item['reset'] = true;
    }

    // Aggiorna l'elemento di valutazione nel registro
    grade_update('mod/zoomoodle',
        $moduleinstance->course, 'mod', 'zoomoodle',
        $moduleinstance->id, 0, $grades, $item
    );
}

/**
 * Elimina un elemento di valutazione
 */
function zoomoodle_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/zoomoodle',
        $moduleinstance->course, 'mod', 'zoomoodle',
        $moduleinstance->id, 0, null, ['deleted' => 1]
    );
}

/**
 * Aggiorna lo stato di completamento per un utente
 * 
 * @param object $course Corso
 * @param object $cm Course module
 * @param int $userid ID utente
 * @param float|null $score Punteggio ottenuto (opzionale)
 * @return bool true se l'aggiornamento è riuscito
 */
function zoomoodle_update_completion($course, $cm, $userid, $score = null) {
    global $DB;
    
    if (empty($course) || empty($cm) || empty($userid)) {
        debugging('Zoomoodle: parametri mancanti per update_completion', DEBUG_DEVELOPER);
        return false;
    }

    try {
        // Verifica se il completamento è abilitato
        if (empty($cm->completion)) {
            return true;
        }

        // Recupera la soglia di superamento dal modulo
        $instance = $DB->get_record('zoomoodle', array('id' => $cm->instance), '*', MUST_EXIST);
        $threshold = isset($instance->attendance_threshold) ? (float)$instance->attendance_threshold : 0.0;

        // Aggiorna direttamente il record di completamento
        $completion = new \stdClass();
        $completion->coursemoduleid = $cm->id;
        $completion->userid = $userid;
        $completion->viewed = 1;
        $completion->timemodified = time();

        // Determina lo stato di completamento
        if ($score === null) {
            // Se non abbiamo un punteggio, usiamo COMPLETION_INCOMPLETE
            $completion->completionstate = COMPLETION_INCOMPLETE;
        } else if ($score >= $threshold) {
            // Se supera la soglia, usiamo COMPLETION_COMPLETE_PASS
            $completion->completionstate = COMPLETION_COMPLETE_PASS;
        } else {
            // Se non supera la soglia, usiamo COMPLETION_COMPLETE_FAIL
            $completion->completionstate = COMPLETION_COMPLETE_FAIL;
        }

        // Cerca un record esistente
        $current = $DB->get_record('course_modules_completion', 
            array('coursemoduleid' => $cm->id, 'userid' => $userid));

        if ($current) {
            $completion->id = $current->id;
            $DB->update_record('course_modules_completion', $completion);
        } else {
            $DB->insert_record('course_modules_completion', $completion);
        }

        // Trigger dell'evento di completamento
        $event = \core\event\course_module_completion_updated::create(array(
            'objectid' => $cm->id,
            'context' => \context_module::instance($cm->id),
            'relateduserid' => $userid,
            'other' => array(
                'completionstate' => $completion->completionstate
            )
        ));
        $event->add_record_snapshot('course_modules_completion', $completion);
        $event->trigger();

        return true;
    } catch (\Exception $e) {
        debugging('Zoomoodle: errore in update_completion: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Get a lock instance for zoomoodle operations
 *
 * @param string $resource Resource identifier
 * @param int $timeout Timeout in seconds
 * @return \core\lock\lock|false The lock instance or false if not available
 */
function zoomoodle_get_lock($resource, $timeout) {
    try {
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_zoomoodle');
        if ($lockfactory) {
            $lock = $lockfactory->get_lock($resource, $timeout);
            if ($lock) {
                return $lock;
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    return false;
}

/**
 * Release a lock instance safely
 *
 * @param \core\lock\lock|null $lock The lock to release
 */
function zoomoodle_release_lock($lock) {
    try {
        if ($lock && $lock instanceof \core\lock\lock) {
            $lock->release();
        }
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Verifica se le librerie necessarie sono caricate
 * @return bool true se tutto ok, false altrimenti
 */
function zoomoodle_check_requirements() {
    if (!class_exists('completion_info')) {
        return false;
    }
    if (!class_exists('grade_item')) {
        return false;
    }
    return true;
}

/**
 * Aggiorna lo stato di completamento in modo sicuro
 */
function zoomoodle_mark_complete($cmid, $userid) {
    global $DB, $CFG;
    
    if (!zoomoodle_check_requirements()) {
        return false;
    }

    if (empty($cmid) || empty($userid)) {
        return false;
    }

    try {
        $transaction = $DB->start_delegated_transaction();
        
        $params = ['coursemoduleid' => $cmid, 'userid' => $userid];
        $exists = $DB->record_exists('course_modules_completion', $params);
        
        $record = new \stdClass();
        $record->coursemoduleid = $cmid;
        $record->userid = $userid;
        $record->completionstate = COMPLETION_COMPLETE;
        $record->timemodified = time();
        $record->viewed = 1;
        
        if ($exists) {
            $DB->set_field('course_modules_completion', 'completionstate', COMPLETION_COMPLETE, $params);
            $DB->set_field('course_modules_completion', 'timemodified', time(), $params);
            $DB->set_field('course_modules_completion', 'viewed', 1, $params);
        } else {
            $record->timemodified = $record->timecreated = time();
            $DB->insert_record('course_modules_completion', $record);
        }

        // Trigger dell'evento di completamento
        $cm = get_coursemodule_from_id('', $cmid);
        if ($cm) {
            $event = \core\event\course_module_completion_updated::create([
                'objectid' => $cmid,
                'context' => \context_module::instance($cmid),
                'relateduserid' => $userid,
                'other' => [
                    'completionstate' => COMPLETION_COMPLETE
                ]
            ]);
            $event->trigger();
        }
        
        $DB->commit_delegated_transaction($transaction);
        return true;
        
    } catch (\Exception $e) {
        if (isset($transaction)) {
            $DB->rollback_delegated_transaction($transaction);
        }
        return false;
    }
}

function zoomoodle_fetch_participants($instance) {
    global $DB;

    if (empty($instance) || empty($instance->id) || empty($instance->webinar_id)) {
        return false;
    }

    // Ottieni un lock per evitare elaborazioni simultanee
    $lock = zoomoodle_get_lock('fetch_participants_' . $instance->id, 60);
    if (!$lock) {
        return false;
    }

    try {
        require_once(__DIR__ . '/classes/api.php');
        $token = \mod_zoomoodle\api::get_token();
        if (empty($token)) {
            throw new \Exception('Token API non valido');
        }

        $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('zoomoodle', $instance->id, $course->id);
        if (!$cm) {
            throw new \Exception('Course module non trovato');
        }

        $url = "https://api.zoom.us/v2/report/webinars/{$instance->webinar_id}/participants";
        $allparticipants = [];
        $next = '';
        $page = 1;
        $max_pages = 10;
        $requests_per_minute = 10;
        $last_request_time = 0;

        do {
            // Implementa rate limiting
            $current_time = time();
            $time_since_last = $current_time - $last_request_time;
            if ($time_since_last < (60 / $requests_per_minute)) {
                sleep(ceil((60 / $requests_per_minute) - $time_since_last));
            }

            $curl = new \curl();
            $params = ['page_size' => 100];
            if ($next) {
                $params['next_page_token'] = $next;
            }
            
            $curl->setHeader('Authorization: Bearer ' . $token);
            $curl->setHeader('Content-Type: application/json');
            $response = $curl->get($url . '?' . http_build_query($params));
            $last_request_time = time();
            
            if ($curl->get_errno()) {
                continue;
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            if (empty($data['participants'])) {
                break;
            }

            foreach ($data['participants'] as $p) {
                $email = strtolower(trim($p['user_email'] ?? ''));
                if (empty($email)) {
                    continue;
                }

                $userid = $DB->get_field('user', 'id', ['email' => $email]);
                if (!$userid) {
                    continue;
                }
                
                $duration = (int)($p['duration'] ?? 0) * 60;
                $join_time = strtotime($p['join_time'] ?? '');
                $leave_time = strtotime($p['leave_time'] ?? '');
                
                if (!isset($allparticipants[$userid])) {
                    $allparticipants[$userid] = [
                        'duration' => 0,
                        'first_join' => $join_time,
                        'last_leave' => $leave_time
                    ];
                }
                
                $allparticipants[$userid]['duration'] += $duration;
                if ($join_time < $allparticipants[$userid]['first_join']) {
                    $allparticipants[$userid]['first_join'] = $join_time;
                }
                if ($leave_time > $allparticipants[$userid]['last_leave']) {
                    $allparticipants[$userid]['last_leave'] = $leave_time;
                }
            }
            
            $next = $data['next_page_token'] ?? '';
            $page++;
            
            if ($page > $max_pages) {
                break;
            }
            
        } while ($next);

        // Batch update per migliorare le performance
        $transaction = $DB->start_delegated_transaction();
        
        try {
            foreach ($allparticipants as $userid => $data) {
                $record = (object)[
                    'zoomid' => $instance->id,
                    'userid' => $userid,
                    'duration' => $data['duration'],
                    'joinedat' => $data['first_join'],
                    'leftat' => $data['last_leave'],
                    'timemodified' => time()
                ];
                
                if ($existing = $DB->get_record('zoomoodle_participants', 
                    ['zoomid' => $instance->id, 'userid' => $userid])) {
                    $record->id = $existing->id;
                    $DB->update_record('zoomoodle_participants', $record);
                } else {
                    $record->timecreated = time();
                    $DB->insert_record('zoomoodle_participants', $record);
                }

                if ($cm && $cm->completion) {
                    zoomoodle_mark_complete($cm->id, $userid);
                }
            }

            $DB->commit_delegated_transaction($transaction);
            
            // Aggiorna i voti dopo aver salvato tutti i partecipanti
            if (!empty($allparticipants)) {
                zoomoodle_update_grades($instance);
            }

            return true;

        } catch (\Exception $e) {
            $DB->rollback_delegated_transaction($transaction);
            throw $e;
        }

    } catch (\Exception $e) {
        return false;
    } finally {
        zoomoodle_release_lock($lock);
    }
}

function zoomoodle_update_grades($instance, $userid = 0) {
    global $DB, $CFG;

    if (!zoomoodle_check_requirements()) {
        return false;
    }

    if (empty($instance) || empty($instance->id)) {
        return false;
    }

    $totalsec = (int)$instance->totalseconds;
    if ($totalsec <= 0) {
        return false;
    }

    try {
        // Ottieni il grade item
        $gradeitem = grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'zoomoodle',
            'iteminstance' => $instance->id,
            'courseid'     => $instance->course
        ]);

        if (!$gradeitem) {
            return false;
        }

        // Prepara la query per i partecipanti
        $params = ['zoomid' => $instance->id];
        $userwhere = '';
        if ($userid > 0) {
            $params['userid'] = $userid;
            $userwhere = ' AND userid = :userid';
        }

        // Ottieni i record dei partecipanti in modo efficiente
        $sql = "SELECT userid, duration 
                FROM {zoomoodle_participants} 
                WHERE zoomid = :zoomid" . $userwhere;
        
        $participants = $DB->get_records_sql($sql, $params);

        if (empty($participants)) {
            return false;
        }

        // Inizia una transazione per l'aggiornamento dei voti
        $transaction = $DB->start_delegated_transaction();

        try {
            $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('zoomoodle', $instance->id, $course->id, false, MUST_EXIST);

            // Prepara l'oggetto completion_info una sola volta se necessario
            $completion = null;
            if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC && !empty($cm->completionusegrade)) {
                $completion = new completion_info($course);
                if (!$completion->is_enabled($cm)) {
                    $completion = null;
                }
            }

            foreach ($participants as $record) {
                // Calcola il voto
                $rawgrade = ($record->duration / $totalsec) * 100;
                
                // Prepara l'oggetto grade
                $grade = new \stdClass();
                $grade->userid = $record->userid;
                $grade->rawgrade = $rawgrade;
                
                // Aggiorna il voto
                grade_update('mod/zoomoodle',
                    $instance->course,
                    'mod',
                    'zoomoodle',
                    $instance->id,
                    0,
                    $grade,
                    ['itemid' => $gradeitem->id]
                );

                // Aggiorna il completamento se necessario
                if ($completion) {
                    $current = $completion->get_data($cm, false, $record->userid);
                    
                    if ($rawgrade >= $instance->attendance_threshold) {
                        if ($current->completionstate != COMPLETION_COMPLETE) {
                            $completion->update_state($cm, COMPLETION_COMPLETE, $record->userid);
                        }
                    } else {
                        if ($current->completionstate != COMPLETION_INCOMPLETE) {
                            $completion->update_state($cm, COMPLETION_INCOMPLETE, $record->userid);
                        }
                    }
                }
            }

            // Commit della transazione
            $DB->commit_delegated_transaction($transaction);
            return true;

        } catch (\Exception $e) {
            $DB->rollback_delegated_transaction($transaction);
            throw $e;
        }

    } catch (\Exception $e) {
        return false;
    }
}

function zoomoodle_cron() {
    global $DB;
    
    // Ottieni un lock globale per il cron
    $lock = zoomoodle_get_lock('cron_execution', 300); // 5 minuti di timeout
    if (!$lock) {
        return false;
    }

    try {
        $instances = $DB->get_records('zoomoodle', null, 'id ASC');
        if (empty($instances)) {
            return true;
        }
        
        $processed = 0;
        $rate_limit = 10; // Massimo numero di webinar da processare
        $delay = 2; // Secondi di attesa tra le elaborazioni
        $errors = 0;
        $max_errors = 3; // Numero massimo di errori consecutivi prima di fermarsi
        
        foreach ($instances as $instance) {
            if ($processed >= $rate_limit) {
                break;
            }

            if ($errors >= $max_errors) {
                break;
            }
            
            try {
                if (empty($instance->webinar_id)) {
                    continue;
                }
                
                // Verifica se il corso esiste ancora
                $course = $DB->get_record('course', ['id' => $instance->course]);
                if (!$course) {
                    continue;
                }
                
                // Verifica se il course module esiste ancora
                $cm = get_coursemodule_from_instance('zoomoodle', $instance->id, $course->id);
                if (!$cm) {
                    continue;
                }

                // Ottieni un lock specifico per l'istanza
                $instance_lock = zoomoodle_get_lock('instance_' . $instance->id, 60);
                if (!$instance_lock) {
                    continue;
                }

                try {
                    $success = zoomoodle_fetch_participants($instance);
                    if ($success) {
                        $processed++;
                        $errors = 0; // Reset del contatore errori in caso di successo
                    } else {
                        $errors++;
                    }
                } finally {
                    zoomoodle_release_lock($instance_lock);
                }
                
                if ($processed < count($instances)) {
                    sleep($delay);
                }
                
            } catch (\Exception $e) {
                $errors++;
            }
        }
        
        return true;

    } catch (\Exception $e) {
        return false;
    } finally {
        zoomoodle_release_lock($lock);
    }
}

function zoomoodle_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    if ($type !== COMPLETION_TRACKING_TYPE_ACTIVITY) {
        return COMPLETION_INCOMPLETE;
    }
    $instance = $DB->get_record('zoomoodle', ['id' => $cm->instance], '*', MUST_EXIST);
    if (empty($instance->totalseconds)) {
        return COMPLETION_INCOMPLETE;
    }
    $record = $DB->get_record('zoomoodle_participants', [
        'zoomid' => $instance->id,
        'userid' => $userid
    ]);
    if (!$record) {
        return COMPLETION_INCOMPLETE;
    }
    $percent = round($record->duration / $instance->totalseconds * 100, 2);
    return ($percent >= (float)$instance->attendance_threshold)
         ? COMPLETION_COMPLETE
         : COMPLETION_INCOMPLETE;
}

function zoomoodle_get_file_areas($course, $cm, $context) {
    return [];
}
function zoomoodle_get_file_info($browser, $areas, $course, $cm,
    $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}
function zoomoodle_pluginfile($course, $cm, $context,
    $filearea, $args, $forcedownload, $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }
    require_login($course, true, $cm);
    send_file_not_found();
}

function zoomoodle_extend_navigation(navigation_node $navref,
    stdClass $course, stdClass $module, cm_info $cm) {}
function zoomoodle_extend_settings_navigation(settings_navigation $settingsnav,
    navigation_node $zoomoodlenode = null) {}
function zoomoodle_extend_navigation_course(navigation_node $parentnode,
    stdClass $course, context_course $context) {}
function zoomoodle_extend_navigation_user_settings(navigation_node $parentnode,
    stdClass $user, context_user $context, stdClass $course, context_course $coursecontext) {}
function zoomoodle_extend_navigation_category_settings(navigation_node $parentnode,
    context_coursecat $context) {}
function zoomoodle_extend_navigation_frontpage(navigation_node $parentnode,
    stdClass $course, context_course $context) {}

function zoomoodle_user_enrolled($enrol) {
    global $DB;
    
    // Verifica che l'utente sia stato effettivamente iscritto
    if (!$enrol->userid || !$enrol->courseid) {
        return;
    }

    // Recupera tutte le istanze Zoomoodle nel corso
    $instances = $DB->get_records('zoomoodle', ['course' => $enrol->courseid]);
    
    foreach ($instances as $instance) {
        // Recupera l'utente
        $user = $DB->get_record('user', ['id' => $enrol->userid]);
        if (!$user || empty($user->email)) {
            continue;
        }

        try {
            // Prepara la richiesta per registrare l'utente al webinar
            $url = "https://api.zoom.us/v2/webinars/{$instance->webinar_id}/registrants";
            $token = generate_zoom_jwt(
                trim(get_config('zoomoodle', 'zoom_apikey')),
                trim(get_config('zoomoodle', 'zoom_secret'))
            );

            $data = [
                'email' => $user->email,
                'first_name' => $user->firstname,
                'last_name' => $user->lastname,
                'auto_approve' => true
            ];

            $curl = new \curl();
            $response = $curl->post($url, json_encode($data), [
                'headers' => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]
            ]);

            $result = json_decode($response, true);

            // Salva il registrant_id nel database per riferimento futuro
            if (!empty($result['registrant_id'])) {
                $record = new stdClass();
                $record->zoomid = $instance->id;
                $record->userid = $user->id;
                $record->registrant_id = $result['registrant_id'];
                $record->timecreated = time();

                if ($existing = $DB->get_record('zoomoodle_registrants', [
                    'zoomid' => $instance->id,
                    'userid' => $user->id
                ])) {
                    $record->id = $existing->id;
                    $DB->update_record('zoomoodle_registrants', $record);
                } else {
                    $DB->insert_record('zoomoodle_registrants', $record);
                }
            }

        } catch (Exception $e) {
            // Silent fail
        }
    }
}

// End of file.
