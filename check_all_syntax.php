<?php
// File: mod/zoomoodle/check_syntax.php

$file = 'classes/api.php';
$path = __DIR__ . '/' . $file;

// Controllo esistenza
if (!file_exists($path)) {
    die("❌ File non trovato: $path");
}

// Indica esplicitamente il PHP CLI di cPanel
$phpcli = '/usr/local/bin/php';
if (!is_executable($phpcli)) {
    // fallback se non esiste
    $phpcli = 'php';
}

// Esegue il lint
exec(escapeshellarg($phpcli) . " -l " . escapeshellarg($path) . " 2>&1", $output, $status);

// Stampa array grezzo per vedere cosa c’è dentro
echo "<h3>Output grezzo di exec():</h3><pre>";
var_export($output);
echo "</pre>";

echo "<h3>Controllo sintassi: <code>$file</code></h3><pre>";
if ($status === 0) {
    echo "✔ Nessun errore di sintassi in $file\n";
} else {
    echo "❌ ERRORE di sintassi in $file (exit code $status):\n\n";
    echo implode("\n", $output);
}
echo "</pre>";
