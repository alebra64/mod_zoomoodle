<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoomoodle/lib.php');
require_once($CFG->dirroot . '/mod/zoomoodle/classes/api.php');
require_once($CFG->libdir . '/filelib.php');  // Per la classe curl

// Protezione accesso
require_login();
if (!is_siteadmin()) {
    die("Accesso negato - Solo gli amministratori possono eseguire questo script");
}

// Setup pagina
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/zoomoodle/test_integration.php', [
    'webinar_id' => optional_param('webinar_id', '', PARAM_TEXT),
    'courseid' => optional_param('courseid', 0, PARAM_INT)
]));
$PAGE->set_title('Test Integrazione Zoomoodle');
$PAGE->set_heading('Test Integrazione Zoomoodle');

// Cancella token se richiesto
$clear_token = optional_param('clear_token', 0, PARAM_INT);
if ($clear_token) {
    unset_config('accesstoken', 'mod_zoomoodle');
    unset_config('tokenexpires', 'mod_zoomoodle');
    $returnurl = $PAGE->url;
    $returnurl->remove_params('clear_token');
    redirect($returnurl);
}

echo $OUTPUT->header();
echo "<h2>Test Integrazione Zoomoodle</h2>";

// Link per cancellare il token
echo "<div style='margin-bottom: 20px;'>";
echo "<a href='" . $PAGE->url->out(false, ['clear_token' => 1]) . "' class='btn btn-warning'>Cancella token OAuth</a>";
echo "</div>";

// 1. Test configurazione
echo "<h3>1. Verifica Configurazione</h3>";
$config = get_config('mod_zoomoodle');
echo "API Key configurata: " . (!empty($config->client_id) ? "✓" : "✗") . "<br>";
echo "Secret configurato: " . (!empty($config->client_secret) ? "✓" : "✗") . "<br>";

// 2. Test connessione API
echo "<h3>2. Test Connessione API</h3>";
$webinar_id = optional_param('webinar_id', '', PARAM_TEXT);
if (!$webinar_id) {
    echo "Specifica un webinar_id nell'URL per testare: test_integration.php?webinar_id=XXX";
    die();
}

try {
    // Ottieni il token OAuth
    $token = \mod_zoomoodle\api::get_token();
    if (empty($token)) {
        throw new \moodle_exception('err_no_token', 'mod_zoomoodle');
    }

    echo "<strong>Debug token:</strong><br>";
    echo "Token ottenuto: " . (empty($token) ? "NO" : "SI") . "<br>";
    echo "Lunghezza token: " . strlen($token) . "<br><br>";

    // Test API call
    $url = "https://api.zoom.us/v2/webinars/{$webinar_id}";
    $curl = new \curl();
    $curl->setHeader('Authorization: Bearer ' . $token);
    $curl->setHeader('Content-Type: application/json');
    $response = $curl->get($url);
    
    // Decodifica e formatta la risposta
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<div class='alert alert-danger'>Errore nella decodifica JSON: " . json_last_error_msg() . "</div>";
        echo "<pre>Risposta raw:\n" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<h3>Dettagli Webinar:</h3>";
        echo "<div class='card'><div class='card-body'>";
        if (isset($data['topic'])) {
            echo "<strong>Titolo:</strong> " . htmlspecialchars($data['topic']) . "<br>";
            echo "<strong>ID:</strong> " . htmlspecialchars($data['id']) . "<br>";
            echo "<strong>Data:</strong> " . date('d/m/Y H:i', strtotime($data['start_time'])) . "<br>";
            echo "<strong>Durata:</strong> " . htmlspecialchars($data['duration']) . " minuti<br>";
            echo "<strong>Timezone:</strong> " . htmlspecialchars($data['timezone']) . "<br>";
            if (isset($data['status'])) {
                echo "<strong>Stato:</strong> " . htmlspecialchars($data['status']) . "<br>";
            }
            
            // Aggiungi altri dettagli utili
            if (isset($data['settings'])) {
                echo "<br><strong>Impostazioni:</strong><br>";
                if (isset($data['settings']['approval_type'])) {
                    echo "Tipo approvazione: " . htmlspecialchars($data['settings']['approval_type']) . "<br>";
                }
                if (isset($data['settings']['registration_type'])) {
                    echo "Tipo registrazione: " . htmlspecialchars($data['settings']['registration_type']) . "<br>";
                }
                if (isset($data['settings']['registrants_email_notification'])) {
                    echo "Notifiche email: " . ($data['settings']['registrants_email_notification'] ? "Attive" : "Disattive") . "<br>";
                }
            }
        } else {
            echo "<div class='alert alert-warning'>Dati webinar non trovati nella risposta</div>";
            echo "<pre>Risposta completa:\n";
            print_r($data);
            echo "</pre>";
        }
        echo "</div></div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Errore: " . $e->getMessage() . "</div>";
}

// 3. Test tabelle database
echo "<h3>3. Verifica Tabelle Database</h3>";
$tables = ['zoomoodle', 'zoomoodle_urls'];
foreach ($tables as $table) {
    echo "Tabella {$table}: " . ($DB->get_manager()->table_exists($table) ? "✓" : "✗") . "<br>";
}

// 4. Test completamento
echo "<h3>4. Test Logica Completamento</h3>";
echo "<div class='card'><div class='card-body'>";
echo "<h4>Istanza: Accedi al webinar</h4>";

$webinar_id = optional_param('webinar_id', '', PARAM_TEXT);
if ($webinar_id) {
    echo "<p><strong>Webinar ID:</strong> {$webinar_id}</p>";
    $participants = $DB->get_records('zoomoodle_urls', ['zoomoodleid' => $webinar_id]);
    echo "<p><strong>Partecipanti registrati:</strong> " . count($participants) . "</p>";
    
    if ($participants) {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-hover'>";
        echo "<thead class='thead-light'>";
        echo "<tr>
                <th>Nome Utente</th>
                <th>ID Utente</th>
                <th>Zoom User ID</th>
                <th>Azioni</th>
              </tr>";
        echo "</thead><tbody>";
        
        foreach ($participants as $p) {
            $user = $DB->get_record('user', ['id' => $p->userid]);
            echo "<tr>";
            echo "<td>" . fullname($user) . "</td>";
            echo "<td>" . $p->userid . "</td>";
            echo "<td>" . htmlspecialchars($p->zoom_user) . "</td>";
            echo "<td>";
            echo "<a href='" . htmlspecialchars($p->zoom_url) . "' target='_blank' class='btn btn-primary btn-sm'>
                    <i class='fa fa-external-link'></i> Accedi
                  </a>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "</div>";
    }
} else {
    echo "<div class='alert alert-info'>Specifica un webinar_id nell'URL per testare il completamento</div>";
}
echo "</div></div>";

echo $OUTPUT->footer(); 