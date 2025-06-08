<?php
// File: mod/zoomoodle/classes/api.php
// License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe per la gestione delle chiamate API a Zoom
 * 
 * Questa classe gestisce tutte le interazioni con l'API di Zoom,
 * inclusa l'autenticazione, la creazione/modifica di meeting e
 * la sincronizzazione dei dati di partecipazione
 */
class api {
    /** @var string URL base per le chiamate API di Zoom */
    private $api_url = 'https://api.zoom.us/v2/';
    
    /** @var string Token JWT per l'autenticazione */
    private $jwt_token;
    
    /** @var array Cache delle risposte API per ottimizzare le prestazioni */
    private static $cache = array();

    /**
     * Costruttore della classe API
     * 
     * @param string $jwt_token Token JWT per l'autenticazione con Zoom
     * @throws \Exception se il token non è valido
     */
    public function __construct($jwt_token) {
        if (empty($jwt_token)) {
            throw new \Exception('Token JWT non valido');
        }
        $this->jwt_token = $jwt_token;
    }

    /**
     * Esegue una richiesta HTTP all'API di Zoom
     * 
     * @param string $endpoint Endpoint API da chiamare
     * @param string $method Metodo HTTP (GET, POST, PATCH, DELETE)
     * @param array $data Dati da inviare con la richiesta
     * @return object Risposta dell'API decodificata
     * @throws \Exception in caso di errori nella chiamata
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $url = $this->api_url . ltrim($endpoint, '/');
        
        $headers = array(
            'Authorization: Bearer ' . $this->jwt_token,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 400) {
            throw new \Exception('Errore API Zoom: ' . $response);
        }

        return json_decode($response);
    }

    /**
     * Crea un nuovo meeting su Zoom
     * 
     * @param array $meeting_data Dati del meeting da creare
     * @return object Dati del meeting creato
     */
    public function create_meeting($meeting_data) {
        return $this->make_request('users/me/meetings', 'POST', $meeting_data);
    }

    /**
     * Recupera i dettagli di un meeting
     * 
     * @param string $meeting_id ID del meeting
     * @return object Dettagli del meeting
     */
    public function get_meeting($meeting_id) {
        $cache_key = 'meeting_' . $meeting_id;
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }

        $meeting = $this->make_request('meetings/' . $meeting_id);
        self::$cache[$cache_key] = $meeting;
        
        return $meeting;
    }

    /**
     * Recupera i partecipanti di un meeting
     * 
     * @param string $meeting_id ID del meeting
     * @return array Lista dei partecipanti
     */
    public function get_meeting_participants($meeting_id) {
        return $this->make_request('report/meetings/' . $meeting_id . '/participants');
    }

    /**
     * Aggiorna i dettagli di un meeting esistente
     * 
     * @param string $meeting_id ID del meeting
     * @param array $meeting_data Nuovi dati del meeting
     * @return object Risposta dell'aggiornamento
     */
    public function update_meeting($meeting_id, $meeting_data) {
        return $this->make_request('meetings/' . $meeting_id, 'PATCH', $meeting_data);
    }

    /**
     * Elimina un meeting
     * 
     * @param string $meeting_id ID del meeting da eliminare
     * @return bool true se l'eliminazione è riuscita
     */
    public function delete_meeting($meeting_id) {
        try {
            $this->make_request('meetings/' . $meeting_id, 'DELETE');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Pulisce la cache delle risposte API
     */
    public static function clear_cache() {
        self::$cache = array();
    }

    /**
     * Recupera un access token valido da Zoom via Server-to-Server OAuth,
     * salvandolo in cache Moodle per riutilizzo fino alla scadenza.
     *
     * @return string|null  il token Bearer, o null se errore.
     */
    public static function get_token(): ?string {
        $config = get_config('mod_zoomoodle');
        $now = time();

        if (!empty($config->accesstoken) && !empty($config->tokenexpires) && $config->tokenexpires > $now) {
            return $config->accesstoken;
        }

        $clientid     = trim($config->clientid);
        $clientsecret = trim($config->clientsecret);
        $accountid    = trim($config->accountid);
        if (empty($clientid) || empty($clientsecret) || empty($accountid)) {
            return null;
        }

        $url = 'https://zoom.us/oauth/token'
             . '?grant_type=account_credentials'
             . '&account_id=' . urlencode($accountid);
        $auth = base64_encode("{$clientid}:{$clientsecret}");
        $headers = [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp     = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            return null;
        }
        $data = json_decode($resp, true);
        if (empty($data['access_token']) || empty($data['expires_in'])) {
            return null;
        }

        $expires = $now + intval($data['expires_in']) - 60;
        set_config('accesstoken',  $data['access_token'], 'mod_zoomoodle');
        set_config('tokenexpires', $expires,           'mod_zoomoodle');

        return $data['access_token'];
    }

    /**
     * Registra un utente al webinar su Zoom via POST /webinars/{webinarId}/registrants.
     *
     * @param string $token Token di accesso Zoom
     * @param int $webinarid ID del webinar
     * @param int $userid ID dell'utente Moodle
     * @return array Array con [registrant_id, join_url] o [null, null] se errore
     */
    public static function register_user_to_webinar(string $token, int $webinarid, int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], 'email,firstname,lastname', MUST_EXIST);
        $apiurl = trim(get_config('mod_zoomoodle','apiurl'), '/') ?: 'https://api.zoom.us/v2';

        // 0) Controllo prima se l'utente è già registrato
        $list = self::call_zoom_api(
            "{$apiurl}/webinars/{$webinarid}/registrants?status=approved&page_size=300",
            $token,
            null,
            'GET'
        );
        if ($list['httpcode'] === 200) {
            $data = json_decode($list['body'], true);
            foreach ($data['registrants'] ?? [] as $r) {
                if (strcasecmp($r['email'], $user->email) === 0) {
                    return [(string)$r['id'], $r['join_url']];
                }
            }
        }

        $doPost = function($tok) use ($apiurl, $webinarid, $user) {
            return self::call_zoom_api(
                "{$apiurl}/webinars/{$webinarid}/registrants",
                $tok,
                ['email' => $user->email, 'first_name' => $user->firstname, 'last_name' => $user->lastname]
            );
        };

        $result = $doPost($token);
        if ($result['httpcode'] === 401) {
            debugging("Zoom API 401: token scaduto, rigenero e riprovo", DEBUG_DEVELOPER);
            $newtoken = self::get_token();
            if (!empty($newtoken) && $newtoken !== $token) {
                $result = $doPost($newtoken);
            }
        }
        if ($result['httpcode'] === 429 && stripos($result['body'], 'rate limit') !== false) {
            debugging("Zoom API 429: rate limit, fallback GET", DEBUG_DEVELOPER);
            $list2 = self::call_zoom_api(
                "{$apiurl}/webinars/{$webinarid}/registrants?status=approved&page_size=300",
                $token,
                null,
                'GET'
            );
            $data2 = json_decode($list2['body'], true) ?: [];
            foreach ($data2['registrants'] ?? [] as $r) {
                if (strcasecmp($r['email'], $user->email) === 0) {
                    return [(string)$r['id'], $r['join_url']];
                }
            }
            return [null, null];
        }
        if ($result['httpcode'] === 201) {
            $data3 = json_decode($result['body'], true) ?: [];
            return [(string)($data3['registrant_id'] ?? ''), $data3['join_url'] ?? null];
        }

        debugging("Zoom API error ({$result['httpcode']}): {$result['body']}", DEBUG_DEVELOPER);
        return [null, null];
    }

    /**
     * Wrapper per chiamate Zoom API (POST o GET).
     */
    protected static function call_zoom_api(string $url, string $token, ?array $payload, string $method='POST'): array {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($ch, $opts);
        $body     = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['httpcode' => $httpcode, 'body' => $body];
    }

    /**
     * Recupera i partecipanti e la loro durata a un webinar (report).
     */
    public static function get_webinar_participants(int $webinarid): array {
        $token  = self::get_token();
        if (empty($token)) {
            debugging("Zoomoodle DEBUG: nessun token in get_webinar_participants", DEBUG_DEVELOPER);
            return [];
        }
        $apiurl = trim(get_config('mod_zoomoodle','apiurl'), '/') ?: 'https://api.zoom.us/v2';
        $url    = "{$apiurl}/report/webinars/{$webinarid}/participants?page_size=300";

        // --- CHIAMATA API ---
        $resp   = self::call_zoom_api($url, $token, null, 'GET');
        $data = json_decode($resp['body'], true);
        return $data['participants'] ?? [];
    }

}
