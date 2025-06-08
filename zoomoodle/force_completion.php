<?php
// Script per forzare il completamento delle attività Zoomoodle
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

// Protezione accesso
require_login();
if (!is_siteadmin()) {
    die("Accesso negato - Solo gli amministratori possono eseguire questo script");
}

// Parametri
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$mingrade = optional_param('mingrade', 50, PARAM_FLOAT);

// Recupera il corso
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$cm = get_coursemodule_from_id('zoomoodle', $cmid, $courseid, false, MUST_EXIST);

// Inizializza completion info
$completion = new completion_info($course);
if (!$completion->is_enabled($cm)) {
    die("Il completamento non è abilitato per questa attività");
}

// Recupera tutti gli utenti iscritti al corso
$context = context_course::instance($courseid);
$enrolled_users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);

echo "<h2>Forzatura Completamento Zoomoodle</h2>";
echo "<p>Corso: {$course->fullname} (ID: {$courseid})<br>";
echo "Attività: {$cm->name} (CM ID: {$cmid})<br>";
echo "Voto minimo richiesto: {$mingrade}</p>";

$updated = 0;
foreach ($enrolled_users as $user) {
    // Recupera il voto dell'utente
    $grade = grade_get_grades($courseid, 'mod', 'zoomoodle', $cm->instance, $user->id);
    
    if (!empty($grade->items[0]->grades[$user->id])) {
        $usergrade = $grade->items[0]->grades[$user->id]->grade;
        
        if ($usergrade >= $mingrade) {
            // Forza il completamento
            $completion = new completion_info($course);
            $current = $completion->get_data($cm, false, $user->id);
            
            // Imposta il completamento solo se non è già completato
            if ($current->completionstate != COMPLETION_COMPLETE) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $user->id);
                echo "<p>Utente {$user->username}: Completamento forzato (voto: {$usergrade})</p>";
                $updated++;
            }
        }
    }
}

echo "<h3>Completato</h3>";
echo "<p>Aggiornati {$updated} utenti.</p>"; 