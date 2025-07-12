<?php
declare(strict_types=1);

// ---------------------------------------------------------
// include.inc.php
// Globale Settings, sichere Initialisierung, DB-Autoinit
// ---------------------------------------------------------

// 0. Output-Buffering starten, damit wir Header nach Ausgaben ändern können
ob_start();

// 1. Zugangsdaten privat halten
$private_config_file = __DIR__ . '/config.private.php';
if (!file_exists($private_config_file)) {
    http_response_code(500);
    echo 'Fehlende Konfiguration. Bitte config.private.php anlegen.';
    exit;
}
require_once $private_config_file;

// 2. Sichere Session-Initialisierung (HTTP-Only, Secure, SameSite)
//    Muss VOR session_start() und VOR allen Ausgaben erfolgen
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'],
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
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
ini_set('display_errors',       $devMode ? '1' : '0');
ini_set('display_startup_errors',$devMode ? '1' : '0');
error_reporting($devMode ? E_ALL : 0);

// 5. HTTP-Header für Security (Clickjacking, XSS, etc.)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}

// 6. PDO-Verbindung mit robustem Fehler-Handling
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    if ($devMode) {
        echo '<h1>Fehler bei der Datenbankverbindung</h1>'
           . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    } else {
        echo 'Technisches Problem. Bitte später erneut versuchen.';
    }
    exit;
}

// 7. Automatische Initialisierung der DB (falls noch nicht vorhanden)
function initialize_database_if_needed(PDO $pdo, string $initFile = 'db.sql'): void {
    try {
        $res = $pdo->query("SHOW TABLES LIKE 'questionnaires'");
        $exists = (bool) $res && $res->fetch();
    } catch (PDOException $e) {
        $exists = false;
    }

    if (!$exists) {
        $path = __DIR__ . '/' . $initFile;
        if (!is_readable($path)) {
            http_response_code(500);
            echo 'Datenbank nicht initialisiert – db.sql fehlt.';
            exit;
        }
        $sql = file_get_contents($path);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        try {
            foreach ($stmts as $stmt) {
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }
            // Nach Init neu laden, bevor weitere Scripts laufen
            if (!headers_sent()) {
                header('Location: ' . $_SERVER['REQUEST_URI']);
            }
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<h1>Fehler bei der DB-Initialisierung</h1>'
               . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            exit;
        }
    }
}
initialize_database_if_needed($pdo);

// 8. Globale UX-Konstante
if (!defined('SITE_TITLE')) {
    define('SITE_TITLE', '📝 Online-Fragebögen');
}

// --- Ende include.inc.php (keine Leerzeilen oder Ausgaben danach) ---
