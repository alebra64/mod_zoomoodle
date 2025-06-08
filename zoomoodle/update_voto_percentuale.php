<?php
/**
 * File: mod/zoomoodle/update_voto_percentuale.php
 * Aggiorna i voti in Gradebook basati su durata effettiva (duration) e marca completion.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

// Parametri e permessi
$courseid = optional_param('course', 0, PARAM_INT);
if (!$courseid) {
    die("Errore: specifica l'id del corso via ?course=ID");
}
require_login();
if (!is_siteadmin()) {
    die("Accesso negato");
}

echo "=== Inizio aggiornamento voti e completion per corso {$courseid} ===<br><br>";
$updated = 0;
$skipped = 0;

// Recupera tutte le istanze Zoomoodle del corso
$instances = $DB->get_records('zoomoodle', ['course' => $courseid]);
foreach ($instances as $instance) {
    // Recupera il course module
    $cm = get_coursemodule_from_instance('zoomoodle', $instance->id, $courseid);
    if (!$cm) {
        echo "⚠️ CM non trovato per istanza {$instance->id}<br>";
        continue;
    }
    $context = context_module::instance($cm->id);
    $users   = get_enrolled_users($context);

    // Recupera il grade_item
    $gradeitem = grade_item::fetch([
        'itemtype'     => 'mod',
        'itemmodule'   => 'zoomoodle',
        'iteminstance' => $instance->id,
        'courseid'     => $courseid
    ]);
    if (!$gradeitem) {
        echo "⚠️ Nessun grade_item per istanza {$instance->id}<br>";
        continue;
    }
    // Forza 2 decimali
    $DB->set_field('grade_items', 'decimals', 2, ['id' => $gradeitem->id]);

    $threshold = (float)$instance->attendance_threshold;   // soglia in %
    $totalsec  = (int)$instance->totalseconds;            // durata webinar
    if ($totalsec <= 0) {
        echo "⚠️ totalseconds non valido per istanza {$instance->id}<br>";
        continue;
    }

    // Cicla sugli utenti iscritti
    foreach ($users as $user) {
        // Recupera record di partecipazione
        $rec = $DB->get_record('zoomoodle_participants', [
            'zoomid' => $instance->id,
            'userid' => $user->id
        ]);
        if (!$rec) {
            echo "— Utente {$user->id}: record non trovato<br>";
            $skipped++;
            continue;
        }
        // Calcola durata usando il campo duration fornito da Zoom
        $duration = (int)$rec->duration;
        if ($duration <= 0) {
            echo "— Utente {$user->id}: durata non valida ({$duration})<br>";
            $skipped++;
            continue;
        }
        // Debug: mostra durata
        echo "User {$user->id}: duration={$duration}s";

        // Calcola percentuale
        $percent = round($duration / $totalsec * 100, 2);
        echo " → percent={$percent}%<br>";

        // Salva il voto (override)
        $gradeitem->update_final_grade($user->id, $percent, 'override');
        echo "   ✅ Voto aggiornato a " . number_format($percent, 2, '.', '') . "<br>";
        $updated++;

        // --- MARCA MANUALMENTE L'ACTIVITY COMPLETION ---
        $cmid = $cm->id;
        $now  = time();
        // Rimuovi eventuali record preesistenti
        $DB->delete_records('course_modules_completion', [
            'coursemoduleid' => $cmid,
            'userid'         => $user->id
        ]);
        // Costruisci il nuovo record
        $comp = (object)[
            'coursemoduleid'  => $cmid,
            'userid'          => $user->id,
            'completionstate' => ($percent >= $threshold)
                                    ? COMPLETION_COMPLETE
                                    : COMPLETION_INCOMPLETE,
            'timecompleted'   => ($percent >= $threshold) ? $now : 0,
            'timemodified'    => $now
        ];
        $DB->insert_record('course_modules_completion', $comp);
        echo "   → spunta manuale: " . ($percent >= $threshold ? 'GREEN' : 'EMPTY') . "<br>";
    }
}

echo "<br>=== Fine ===<br>";
echo "Aggiornati: {$updated}, Saltati: {$skipped}<br>";
?>
