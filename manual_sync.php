<?php
// File: manual_sync.php
// Descrizione: script per sincronizzare SOLO le iscrizioni Moodle → Zoom

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/api.php');
global $DB;

// Prendo tutti i moduli Zoomoodle con sync abilitato.
$instances = $DB->get_records('zoomoodle', ['enablesync' => 1]);

foreach ($instances as $instance) {
    $cm    = get_coursemodule_from_instance('zoomoodle', $instance->id, 0, false, MUST_EXIST);
    $users = get_enrolled_users(context_module::instance($cm->id));

    $token = \mod_zoomoodle\api::get_token();
    if (empty($token)) {
        echo "❌ Impossibile ottenere token OAuth da Zoom\n";
        continue;
    }

    foreach ($users as $u) {
        try {
            list($zoomuserid, $joinurl) = \mod_zoomoodle\api::register_user_to_webinar(
                $token,
                $instance->webinar_id,
                $u->id
            );

            $record = (object)[
                'zoomoodleid' => $instance->id,
                'module'      => $cm->id,
                'userid'      => $u->id,
                'user'        => $u->id,
                'course'      => $instance->course,
                'zoom_user'   => $zoomuserid,
                'zoom_url'    => $joinurl
            ];

            $existing = $DB->get_record('zoomoodle_urls', [
                'zoomoodleid' => $instance->id,
                'userid'      => $u->id,
                'course'      => $instance->course
            ], '*', IGNORE_MISSING);

            if ($existing) {
                $existing->zoom_user = $zoomuserid;
                $existing->zoom_url  = $joinurl;
                $DB->update_record('zoomoodle_urls', $existing);
                echo "→ Aggiornato URL per user {$u->id}: {$joinurl}\n";
            } else {
                $DB->insert_record('zoomoodle_urls', $record);
                echo "→ Inserito URL per user {$u->id}: {$joinurl}\n";
            }

        } catch (\moodle_exception $e) {
            $debuginfo = $e->debuginfo ?? 'nessun debuginfo';
            echo "❌ Errore registrazione user {$u->id}: {$e->errorcode} – {$e->getMessage()}\n";
            echo "   Zoom risposta: {$debuginfo}\n\n";
        }
    }
}

echo "\nRegistrazione completata.\n";
