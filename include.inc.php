<?php
declare(strict_types=1);

// === Ganz oben: Output-Buffering starten, bevor irgendetwas ausgegeben wird ===
ob_start();

// === Sichere Session-Initialisierung (muss VOR JEDEM Output passieren) ===
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

// === Kein Whitespace oder BOM vor dieser Datei! ===

// 1. Private Konfiguration (DB-Zugangsdaten, keine Ausgaben darin!)
$private_config_file = __DIR__ . '/config.private.php';
if (!file_exists($private_config_file)) {
    http_response_code(500);
    echo 'Fehlende Konfiguration. Bitte config.private.php anlegen.';
    exit;
}
require_once $private_config_file;

// 2. Zeitzone und Encoding
date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

// 3. Fehleranzeige nach Umgebung
$devMode = (getenv('APP_ENV') === 'dev');
ini_set('display_errors',        $devMode ? '1' : '0');
ini_set('display_startup_errors', $devMode ? '1' : '0');
error_reporting($devMode ? E_ALL : 0);

// 4. Security-Header (sofern noch nicht gesendet)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}

// 5. PDO-Verbindung
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
        echo '<h1>DB-Verbindungsfehler</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    } else {
        echo 'Technisches Problem. Bitte später erneut versuchen.';
    }
    exit;
}

// 6. DB-Autoinit
(function(PDO $pdo){
    try {
        $res = $pdo->query("SHOW TABLES LIKE 'questionnaires'");
        $exists = (bool)$res && $res->fetch();
    } catch (PDOException $e) {
        $exists = false;
    }
    if (!$exists) {
        $path = __DIR__ . '/db.sql';
        if (!is_readable($path)) {
            http_response_code(500);
            echo 'DB nicht initialisiert – db.sql fehlt.';
            exit;
        }
        $sql = file_get_contents($path);
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        try {
            foreach ($stmts as $stmt) {
                if ($stmt !== '') $pdo->exec($stmt);
            }
            if (!headers_sent()) {
                header('Location: ' . $_SERVER['REQUEST_URI']);
            }
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<h1>Initialisierungsfehler</h1><pre>'
                 . htmlspecialchars($e->getMessage()) . '</pre>';
            exit;
        }
    }
})($pdo);

// 7. Site-Title
if (!defined('SITE_TITLE')) {
    define('SITE_TITLE', '📝 Online-Fragebögen');
}

// Ab hier dein Anwendungscode – **keine** Leerzeile oder `?>`!
