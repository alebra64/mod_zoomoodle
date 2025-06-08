<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_login();

if (!is_siteadmin()) {
    die("Accesso negato.");
}

$updated = 0;

echo "<pre>";
echo "Avvio aggiornamento valori in tabella zoomoodle_userdata...\n";

// Recupera tutte le istanze di attività Zoomoodle
$modules = $DB->get_records('zoomoodle');

foreach ($modules as $module) {
    $cm = get_coursemodule_from_instance('zoomoodle', $module->id);
    if (!$cm) continue;

    $context = context_module::instance($cm->id);
    $grades = grade_get_grades($cm->course, 'mod', 'zoomoodle', $module->id);

    if (!empty($grades->items[0]->grades)) {
        foreach ($grades->items[0]->grades as $userid => $gradeinfo) {
            $grade = round((float)$gradeinfo->grade, 2);

            // Ricava il tempo totale e la soglia da dati calcolati esternamente (per ora mettiamo dummy values)
            // In futuro potremmo calcolare da logs Zoom o duration webinar
            $totalseconds = 300; // Supponiamo durata webinar 5 min
            $threshold = 150;    // Supponiamo soglia 50%

            // Cerca record esistente
            $record = $DB->get_record('zoomoodle_userdata', [
                'userid' => $userid,
                'zoomoodleid' => $module->id
            ]);

            if ($record) {
                $record->grade = $grade;
                $record->totalseconds = $totalseconds;
                $record->thresholdseconds = $threshold;
                $DB->update_record('zoomoodle_userdata', $record);
                echo "Aggiornato utente ID {$userid} (voto: {$grade})\n";
                $updated++;
            } else {
                // Se il record non esiste, lo crea
                $new = new stdClass();
                $new->userid = $userid;
                $new->zoomoodleid = $module->id;
                $new->grade = $grade;
                $new->totalseconds = $totalseconds;
                $new->thresholdseconds = $threshold;
                $DB->insert_record('zoomoodle_userdata', $new);
                echo "Creato utente ID {$userid} (voto: {$grade})\n";
                $updated++;
            }
        }
    }
}

echo "\nAggiornamento completato. Record modificati o creati: {$updated}";
echo "</pre>";

?>
