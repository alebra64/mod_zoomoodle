<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');

$courseid = optional_param('course', 0, PARAM_INT);
$userid = optional_param('user', 0, PARAM_INT);

if (!$courseid) {
    die("Errore: specifica l'id del corso via ?course=ID");
}

require_login();
if (!is_siteadmin()) {
    die("Accesso negato");
}

$course = get_course($courseid);
$completion = new completion_info($course);

echo "<h2>Debug Completamento Zoomoodle - Corso {$courseid}</h2>";

global $DB;
$instances = $DB->get_records('zoomoodle', ['course' => $courseid]);

foreach ($instances as $instance) {
    $cm = get_coursemodule_from_instance('zoomoodle', $instance->id, $courseid);
    if (!$cm) {
        echo "<p>Istanza {$instance->id}: CM non trovato</p>";
        continue;
    }

    echo "<h3>Istanza {$instance->id}: {$instance->name}</h3>";
    
    // 1. Verifica configurazione completamento
    echo "<h4>1. Configurazione Completamento</h4>";
    echo "<ul>";
    echo "<li>Completion abilitato: " . ($completion->is_enabled($cm) ? 'Sì' : 'No') . "</li>";
    echo "<li>Tracking type: " . $cm->completion . "</li>";
    echo "<li>Soglia presenza: " . $instance->attendance_threshold . "%</li>";
    echo "</ul>";

    // 2. Verifica voti
    echo "<h4>2. Voti</h4>";
    $grades = grade_get_grades($course->id, 'mod', 'zoomoodle', $instance->id);
    if ($userid) {
        if (isset($grades->items[0]->grades[$userid])) {
            $grade = $grades->items[0]->grades[$userid];
            echo "<p>Voto utente $userid: " . $grade->str_grade . "</p>";
        } else {
            echo "<p>Nessun voto trovato per l'utente $userid</p>";
        }
    } else {
        echo "<p>Tutti i voti:</p><ul>";
        if (isset($grades->items[0]->grades)) {
            foreach ($grades->items[0]->grades as $uid => $grade) {
                echo "<li>Utente $uid: " . $grade->str_grade . "</li>";
            }
        }
        echo "</ul>";
    }

    // 3. Verifica record completamento
    echo "<h4>3. Record Completamento</h4>";
    $sql = "
        SELECT 
            u.id AS userid,
            u.email,
            u.firstname,
            u.lastname,
            cmc.completionstate,
            cmc.viewed,
            cmc.timemodified
        FROM {user} u
        LEFT JOIN {course_modules_completion} cmc ON 
            cmc.userid = u.id AND 
            cmc.coursemoduleid = :cmid
        WHERE u.deleted = 0
        " . ($userid ? "AND u.id = :userid" : "");

    $params = ['cmid' => $cm->id];
    if ($userid) {
        $params['userid'] = $userid;
    }

    $records = $DB->get_records_sql($sql, $params);
    
    echo "<table border='1'>";
    echo "<tr><th>User ID</th><th>Email</th><th>Nome</th><th>Stato</th><th>Viewed</th><th>Ultima modifica</th></tr>";
    
    foreach ($records as $r) {
        $state = '';
        switch($r->completionstate) {
            case COMPLETION_INCOMPLETE:
                $state = 'Incompleto';
                break;
            case COMPLETION_COMPLETE:
                $state = 'Completo';
                break;
            case COMPLETION_COMPLETE_PASS:
                $state = 'Superato';
                break;
            case COMPLETION_COMPLETE_FAIL:
                $state = 'Non superato';
                break;
            default:
                $state = 'Sconosciuto (' . $r->completionstate . ')';
        }
        
        echo "<tr>";
        echo "<td>{$r->userid}</td>";
        echo "<td>{$r->email}</td>";
        echo "<td>{$r->firstname} {$r->lastname}</td>";
        echo "<td>{$state}</td>";
        echo "<td>" . ($r->viewed ? 'Sì' : 'No') . "</td>";
        echo "<td>" . ($r->timemodified ? userdate($r->timemodified) : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} 