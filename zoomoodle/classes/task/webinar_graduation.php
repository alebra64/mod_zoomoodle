<?php
/**
 * Task schedulato per la gestione della chiusura dei webinar
 * 
 * @package    mod_zoomoodle
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomoodle\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

use core\task\scheduled_task;
use mod_zoomoodle\sync_manager;

/**
 * Task per la sincronizzazione e chiusura dei webinar
 */
class webinar_graduation extends scheduled_task {
    /**
     * Nome mostrato in "Scheduled tasks"
     *
     * @return string
     */
    public function get_name() {
        return get_string('webinar_graduation_task', 'mod_zoomoodle');
    }

    /**
     * Esegue la sincronizzazione del report Zoom e la marcatura della completion
     */
    public function execute() {
        global $CFG, $DB;

        try {
            mtrace('Inizio esecuzione task di chiusura webinar');
            
            // Verifica che il modulo sia abilitato
            if (!$DB->get_manager()->table_exists('zoomoodle')) {
                mtrace('Modulo Zoomoodle non installato correttamente');
                return;
            }
            
            // Esegui la sincronizzazione tramite sync_manager
            if (sync_manager::run()) {
                mtrace('Task completato con successo');
            } else {
                mtrace('Task completato con errori - controllare i log per dettagli');
            }
            
        } catch (\Exception $e) {
            mtrace('Errore durante l\'esecuzione del task: ' . $e->getMessage());
            if ($CFG->debug === DEBUG_DEVELOPER) {
                mtrace('Stack trace: ' . $e->getTraceAsString());
            }
        }
    }
}
