<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

require_login();
if (!is_siteadmin()) {
    die("Accesso negato");
}

$zoomid = 160; // ID dell'istanza Zoomoodle collegata al corso 198

echo "<h3>Controllo partecipanti per l'istanza Zoomoodle ID $zoomid</h3><br>";

if (!$zoom = $DB->get_record('zoomoodle', ['id' => $zoomid])) {
    die("❌ Istanza Zoomoodle non trovata.");
}

$courseid = $zoom->course;
$cm = get_coursemodule_from_instance('zoomoodle', $zoomid, $courseid);
$context = context_module::instance($cm->id);
$users = get_enrolled_users($context);

if (empty($users)) {
    echo "❌ Nessun utente iscritto al corso ID $courseid<br>";
    exit;
}

echo "Utenti iscritti al corso ID $courseid: " . count($users) . "<br><br>";

$trovati = 0;
$validi = 0;
$mancanti = 0;

foreach ($users as $user) {
    $record = $DB->get_record('zoomoodle_participants', ['zoomid' => $zoomid, 'userid' => $user->id]);
    if ($record) {
        $trovati++;
        $durata = (int) $record->duration;
        if ($durata > 0) {
            echo "✅ Utente {$user->id} — durata valida: {$durata} secondi<br>";
            $validi++;
        } else {
            echo "⚠️ Utente {$user->id} — record trovato ma durata nulla o zero<br>";
        }
    } else {
        echo "❌ Utente {$user->id} — nessun record nella tabella zoomoodle_participants<br>";
        $mancanti++;
    }
}

echo "<br><strong>Totali:</strong><br>";
echo "🟩 Record trovati: $trovati<br>";
echo "🟦 Durate valide: $validi<br>";
echo "🟥 Record mancanti: $mancanti<br>";
?>
