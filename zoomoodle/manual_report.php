<?php
// File: manual_report.php
// Descrizione: script per sincronizzare il report Zoom → Moodle (completion)

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/sync_manager.php');

global $DB, $CFG;

// Per vedere subito gli eventuali debug sul browser
if (defined('DEBUG_DEVELOPER')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Esegui la sincronizzazione del report e la marcatura della completion.
\mod_zoomoodle\sync_manager::run();

echo "Sincronizzazione report Zoom completata.\n";
