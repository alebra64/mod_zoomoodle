<?php
// File: mod/zoomoodle/classes/oauth.php

namespace mod_zoomoodle;

defined('MOODLE_INTERNAL') || die();

class oauth {

    /**
     * Verifica che il token OAuth sia valido e lo rinnova se necessario.
     *
     * @return void
     */
    public static function ensure_token(): void {
        $config = get_config('mod_zoomoodle');
        if (empty($config->accesstoken) || time() >= (int)$config->tokenexpiry) {
            self::refresh_token();
        }
    }

    /**
     * Richiede un nuovo access token tramite Server-to-Server OAuth.
     * Aggiorna le configurazioni del plugin con il nuovo token e la scadenza.
     *
     * @return void
     * @throws \Exception se la richiesta fallisce
     */
    protected static function refresh_token(): void {
        $config = get_config('mod_zoomoodle');
        $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($config->accountid);

        $headers = [
            'Authorization: Basic ' . base64_encode($config->clientid . ':' . $config->clientsecret),
            'Content-Type: application/json'
        ];

        $curl = new \curl();
        $response = $curl->post($url, [], [
            'headers' => $headers
        ]);

        $data = json_decode($response, true);
        if (empty($data['access_token']) || empty($data['expires_in'])) {
            throw new \Exception('Zoom OAuth token refresh failed: ' . $response);
        }

        // Calcola il timestamp di scadenza
        $expiry = time() + intval($data['expires_in']) - 60; // 60s di buffer

        // Salva in config
        set_config('accesstoken', $data['access_token'], 'mod_zoomoodle');
        set_config('tokenexpiry', $expiry, 'mod_zoomoodle');
    }

}
