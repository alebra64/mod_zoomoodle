<?php
/**
 * run_webinar_via_web.php
 *
 * SCRIPT FTP-ONLY che, aperto da browser,
 * forza l’esecuzione di sync_manager::run().
 *
 * URL esatto (da browser):
 *    https://fad2.mcrconference.it/mod/zoomoodle/run_webinar_via_web.php
 */

// 1) Indichiamo a Moodle che non siamo in CLI, ma in chiamata web.
define('CLI_SCRIPT', false);

// 2) Carichiamo l’ambiente di Moodle (config.php si trova due livelli sopra):
require_once(__DIR__ . '/../../config.php');

// 3) Se vuoi maggiore memoria o evitare timeout PHP (opzionale):
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// 4) A questo punto possiamo chiamare direttamente il manager che fa il lavoro:
echo '<pre>';
echo date('Y-m-d H:i:s') . " - Sto per eseguire sync_manager::run()\n";
try {
    \mod_zoomoodle\sync_manager::run();
    echo date('Y-m-d H:i:s') . " - sync_manager::run() completato correttamente.\n";
} catch (\Throwable $e) {
    // In caso di errori, li visualizziamo a schermo (e nel log del web server)
    echo date('Y-m-d H:i:s') . " - ERRORE in sync_manager::run(): " 
         . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString() . "\n";
}
echo '</pre>';
exit;
