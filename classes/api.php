<?php
// File: mod/zoomoodle/classes/api.php
// License: http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

class api {

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
     * Gestisce GET iniziale, retry su 401 e fallback su 429.
     *
     * @param string $token     Bearer token OAuth
     * @param int    $webinarid ID del webinar Zoom
     * @param int    $userid    ID dell’utente Moodle
     * @return array            [zoomregistrantid, join_url]
     */
    public static function register_user_to_webinar(string $token, int $webinarid, int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], 'email,firstname,lastname', MUST_EXIST);
        $apiurl = trim(get_config('mod_zoomoodle','apiurl'), '/') ?: 'https://api.zoom.us/v2';

        // 0) Controllo prima se l’utente è già registrato
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

        // Primo tentativo POST
        $result = $doPost($token);

        // Se 401 (token scaduto), rigenero
        if ($result['httpcode'] === 401) {
            debugging("Zoom API 401: token scaduto, rigenero e riprovo", DEBUG_DEVELOPER);
            $newtoken = self::get_token();
            if (!empty($newtoken) && $newtoken !== $token) {
                $result = $doPost($newtoken);
            }
        }

        // Se 429 (rate limit), fallback GET list
        if ($result['httpcode'] === 429 && stripos($result['body'], 'rate limit') !== false) {
            debugging("Zoom API 429: rate limit, passo al fallback GET list", DEBUG_DEVELOPER);
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

        // Se creato
        if ($result['httpcode'] === 201) {
            $data3 = json_decode($result['body'], true) ?: [];
            return [(string)($data3['registrant_id'] ?? ''), $data3['join_url'] ?? null];
        }

        // Altri errori
        debugging("Zoom API error ({$result['httpcode']}): {$result['body']}", DEBUG_DEVELOPER);
        return [null, null];
    }

    /**
     * Wrapper per chiamate Zoom API (POST o GET).
     *
     * @param string     $url
     * @param string     $token
     * @param array|null $payload
     * @param string     $method
     * @return array     ['httpcode'=>int,'body'=>string]
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
     *
     * @param int $webinarid
     * @return array Array di registranti JSON-decoded
     */
    public static function get_webinar_participants(int $webinarid): array {
        $token  = self::get_token();
        if (empty($token)) {
            debugging("Zoomoodle DEBUG: nessun token in get_webinar_participants", DEBUG_DEVELOPER);
            return [];
        }
        $apiurl = trim(get_config('mod_zoomoodle','apiurl'), '/') ?: 'https://api.zoom.us/v2';
        $url    = "{$apiurl}/report/webinars/{$webinarid}/participants?page_size=300";

        $resp   = self::call_zoom_api($url, $token, null, 'GET');
        debugging("Zoomoodle DEBUG: API call {$url} → httpcode={$resp['httpcode']}, body=" . substr($resp['body'], 0, 200), DEBUG_DEVELOPER);

        if ($resp['httpcode'] !== 200) {
            return [];
        }
        $data = json_decode($resp['body'], true);
        return $data['participants'] ?? [];
    }

}
