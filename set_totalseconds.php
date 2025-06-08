<?php
require_once(__DIR__ . '/../../config.php');

// Controlla che l'utente sia un amministratore
require_login();
if (!is_siteadmin()) {
    die("Accesso negato");
}

$instanceid = 160;
$totalseconds = 300;

$record = $DB->get_record('zoomoodle', ['id' => $instanceid]);
if ($record) {
    $record->totalseconds = $totalseconds;
    $DB->update_record('zoomoodle', $record);
    echo "✅ Campo totalseconds aggiornato a {$totalseconds} per l'istanza ID {$instanceid}.";
} else {
    echo "❌ Istanza Zoomoodle con ID {$instanceid} non trovata.";
}
?>
