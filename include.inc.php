<?php
// include.inc.php
// Globale Settings, sichere Initialisierung, DB-Autoinit

// 1. Zugangsdaten privat halten
$private_config_file = __DIR__ . '/config.private.php';
if (!file_exists($private_config_file)) {
    http_response_code(500);
    echo 'Fehlende Konfiguration. Bitte config.private.php anlegen.';
    exit;
}
require_once $private_config_file;

// 2. Sichere Session-Initialisierung (HTTP-Only, Secure, SameSite)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', // Default: aktueller Host
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 3. Zeitzone und Encoding
date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

// 4. Fehleranzeige nach Umgebung (DEV/PROD)
$devMode = (getenv('APP_ENV') === 'dev');
ini_set('display_errors', $devMode ? '1' : '0');
ini_set('display_startup_errors', $devMode ? '1' : '0');
error_reporting($devMode ? E_ALL : 0);

// 5. HTTP-Header für Security (Clickjacking, XSS, etc.)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// 6. PDO-Verbindung mit robustem Fehler-Handling
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    if ($devMode) {
        echo '<b>Fehler bei der Datenbankverbindung:</b> ' . htmlspecialchars($e->getMessage());
    } else {
        echo 'Technisches Problem. Bitte später erneut versuchen.';
    }
    exit;
}

// 7. Automatische Initialisierung der DB (falls noch nicht vorhanden)
function initialize_database_if_needed(PDO $pdo, $initFile = 'db.sql') {
    // Prüfen, ob die zentrale Tabelle existiert (hier: questionnaires)
    $exists = false;
    try {
        $res = $pdo->query("SHOW TABLES LIKE 'questionnaires'");
        $exists = $res && $res->fetch();
    } catch (PDOException $e) { /* Ignorieren */ }

    if (!$exists) {
        $path = __DIR__ . '/' . $initFile;
        if (!is_readable($path)) {
            http_response_code(500);
            echo "Datenbank nicht initialisiert – und db.sql fehlt!";
            exit;
        }
        $sql = file_get_contents($path);

        // Statements grob trennen (für die meisten SQL-Files ausreichend)
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        try {
            foreach ($statements as $stmt) {
                if ($stmt !== '') $pdo->exec($stmt);
            }
            // Optional: Erfolgshinweis einmalig anzeigen
            if (!headers_sent()) {
                header("Refresh:0"); // Seite neu laden, damit DB-Struktur erkannt wird
            }
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo "<b>Fehler bei der Initialisierung:</b><br><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            exit;
        }
    }
}
initialize_database_if_needed($pdo);

// 8. Globale UX-Einstellung
if (!defined('SITE_TITLE')) define('SITE_TITLE', '📝 Online-Fragebögen');

// 9. (Optional) HTTPS erzwingen
// if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
//     // HTTPS aktiv
// } else if (!empty($_SERVER['HTTP_HOST'])) {
//     // Optional: Automatische Umleitung auf HTTPS
//     // header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
//     // exit;
// }

// --- Ende der Initialisierung ---
?>
