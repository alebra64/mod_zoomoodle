<?php
// File: mod/zoomoodle/classes/handler.php
// License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

/**
 * Classe per la gestione degli eventi e delle operazioni di Zoomoodle
 * 
 * Questa classe si occupa di gestire tutti gli eventi relativi ai meeting Zoom,
 * inclusa la sincronizzazione dei dati di partecipazione e la gestione delle
 * notifiche agli utenti
 */
class handler {
    /** @var \mod_zoomoodle\api Istanza della classe API */
    private $api;
    
    /** @var object Istanza del corso corrente */
    private $course;
    
    /** @var object Istanza del modulo corrente */
    private $cm;

    /**
     * Costruttore della classe handler
     * 
     * @param object $course Oggetto corso
     * @param object $cm Course module
     * @throws \Exception se mancano parametri essenziali
     */
    public function __construct($course, $cm) {
        global $DB;
        
        if (empty($course) || empty($cm)) {
            throw new \Exception('Parametri mancanti per l\'inizializzazione dell\'handler');
        }
        
        $this->course = $course;
        $this->cm = $cm;
        
        // Inizializza l'API con il token JWT dalle impostazioni
        $config = get_config('mod_zoomoodle');
        $this->api = new \mod_zoomoodle\api($config->jwt_token);
    }

    /**
     * Gestisce la creazione di un nuovo meeting
     * 
     * @param array $meeting_data Dati del meeting da creare
     * @return object Dettagli del meeting creato
     * @throws \Exception in caso di errori nella creazione
     */
    public function handle_meeting_creation($meeting_data) {
        try {
            // Crea il meeting su Zoom
            $meeting = $this->api->create_meeting($meeting_data);
            
            // Salva i dati del meeting nel database
            $this->save_meeting_data($meeting);
            
            // Invia notifiche agli utenti interessati
            $this->notify_users_meeting_created($meeting);
            
            return $meeting;
        } catch (\Exception $e) {
            throw new \Exception('Errore nella creazione del meeting: ' . $e->getMessage());
        }
    }

    /**
     * Salva i dati del meeting nel database
     * 
     * @param object $meeting Dati del meeting da salvare
     * @return bool true se il salvataggio è riuscito
     */
    private function save_meeting_data($meeting) {
        global $DB;
        
        $record = new \stdClass();
        $record->courseid = $this->course->id;
        $record->cmid = $this->cm->id;
        $record->meeting_id = $meeting->id;
        $record->topic = $meeting->topic;
        $record->start_time = strtotime($meeting->start_time);
        $record->duration = $meeting->duration;
        $record->timemodified = time();
        
        return $DB->insert_record('zoomoodle_meetings', $record);
    }

    /**
     * Invia notifiche agli utenti del corso per un nuovo meeting
     * 
     * @param object $meeting Dati del meeting creato
     */
    private function notify_users_meeting_created($meeting) {
        global $DB;
        
        // Recupera tutti gli utenti iscritti al corso
        $context = \context_course::instance($this->course->id);
        $enrolled_users = get_enrolled_users($context);
        
        foreach ($enrolled_users as $user) {
            // Prepara il messaggio di notifica
            $message = new \core\message\message();
            $message->component = 'mod_zoomoodle';
            $message->name = 'meeting_created';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string('meeting_created_subject', 'zoomoodle');
            $message->fullmessage = get_string('meeting_created_body', 'zoomoodle', [
                'topic' => $meeting->topic,
                'date' => userdate($meeting->start_time),
                'duration' => $meeting->duration
            ]);
            $message->fullmessageformat = FORMAT_HTML;
            
            // Invia la notifica
            message_send($message);
        }
    }

    /**
     * Sincronizza i dati di partecipazione di un meeting
     * 
     * @param string $meeting_id ID del meeting
     * @return bool true se la sincronizzazione è riuscita
     */
    public function sync_meeting_attendance($meeting_id) {
        try {
            // Recupera i dati di partecipazione da Zoom
            $participants = $this->api->get_meeting_participants($meeting_id);
            
            if (empty($participants)) {
                return true; // Nessun partecipante da sincronizzare
            }
            
            // Elabora i dati di ogni partecipante
            foreach ($participants as $participant) {
                $this->process_participant_data($meeting_id, $participant);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Elabora i dati di un singolo partecipante
     * 
     * @param string $meeting_id ID del meeting
     * @param object $participant Dati del partecipante
     */
    private function process_participant_data($meeting_id, $participant) {
        global $DB;
        
        // Cerca l'utente Moodle corrispondente
        $user = $DB->get_record('user', ['email' => $participant->user_email]);
        
        if (!$user) {
            return; // Utente non trovato nel sistema Moodle
        }
        
        // Calcola il tempo di partecipazione
        $duration = ($participant->leave_time - $participant->join_time) / 60; // in minuti
        
        // Aggiorna o inserisce il record di partecipazione
        $record = new \stdClass();
        $record->meeting_id = $meeting_id;
        $record->userid = $user->id;
        $record->duration = $duration;
        $record->timemodified = time();
        
        if ($existing = $DB->get_record('zoomoodle_attendance', 
            ['meeting_id' => $meeting_id, 'userid' => $user->id])) {
            $record->id = $existing->id;
            $DB->update_record('zoomoodle_attendance', $record);
        } else {
            $DB->insert_record('zoomoodle_attendance', $record);
        }
        
        // Aggiorna lo stato di completamento se necessario
        $this->update_completion_if_needed($user->id, $duration);
    }

    /**
     * Aggiorna lo stato di completamento se necessario
     * 
     * @param int $userid ID dell'utente
     * @param float $duration Durata della partecipazione in minuti
     */
    private function update_completion_if_needed($userid, $duration) {
        $instance = $DB->get_record('zoomoodle', ['id' => $this->cm->instance]);
        
        if (!empty($instance->completion_on_duration)) {
            $threshold = $instance->completion_duration_minutes;
            $score = ($duration / $threshold) * 100;
            
            \mod_zoomoodle_update_completion(
                $this->course,
                $this->cm,
                $userid,
                $score
            );
        }
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

        // Cancello sempre il record precedente
        $DB->delete_records('zoomoodle_urls', [
            'zoomoodleid' => $instanceid,
            'userid'      => $userid
        ]);

        $zm = $DB->get_record('zoomoodle', ['id' => $instanceid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('zoomoodle', $instanceid, 0, false, MUST_EXIST);

        $token = \mod_zoomoodle\api::get_token();
        if (empty($token)) {
            throw new \moodle_exception('err_no_token', 'mod_zoomoodle');
        }

        list($zoomuser, $joinurl) = api::register_user_to_webinar(
            $token,
            $zm->webinar_id,
            $userid
        );

        if (empty($zoomuser) || empty($joinurl)) {
            return;
        }

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
            }
        }
    }

    /**
     * Gestisce gli eventi di iscrizione/aggiornamento
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function events(\core\event\base $event): void {
        global $DB;

        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        
        if (!$userid || !$courseid) {
            return;
        }

        $instances = $DB->get_records('zoomoodle', ['course' => $courseid]);
        if (!$instances) {
            return;
        }

        foreach ($instances as $instance) {
            try {
                self::sync_enrolment_to_zoom($instance->id, $userid, $courseid);
            } catch (\Exception $e) {
                // Silent fail
            }
        }
    }
}
