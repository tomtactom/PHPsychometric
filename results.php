<?php
// =======================================================
// results.php
// Diagnose- und Produktionsmodus in einem: fängt Fatal Errors,
// Exceptions und alle PHP-Warnings/Notices ab und zeigt sie.
// =======================================================

// === 1. Debugging ON (nur zum Debuggen; später auskommentieren!) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === 2. Fatal Error Handler ===
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo "<h1>Fataler Fehler</h1><pre>"
             . htmlspecialchars($err['message'])
             . "\nin " . htmlspecialchars($err['file'])
             . " on line " . $err['line']
             . "</pre>";
    }
});

// === 3. Globales Try/Catch für Exceptions ===
try {
    require_once 'include.inc.php';

    // --- Hilfsfunktion: user_id aus Cookie holen ---
    function getUserIdFromCookie() {
        if (!empty($_COOKIE['profile']) && ctype_digit($_COOKIE['profile'])) {
            return intval($_COOKIE['profile']);
        }
        return false;
    }

    // --- Fragebogen-ID prüfen ---
    $qid = $_GET['id'] ?? null;
    if (!ctype_digit((string)$qid)) {
        http_response_code(400);
        throw new RuntimeException("Ungültige oder fehlende Parameter: id={$qid}");
    }
    $qid = intval($qid);

    // --- Fragebogen laden ---
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $fragebogen = $stmt->fetch();
    if (!$fragebogen) {
        http_response_code(404);
        throw new RuntimeException("Fragebogen mit ID {$qid} nicht gefunden.");
    }

    // --- Nutzer prüfen ---
    $user_id = getUserIdFromCookie();
    if (!$user_id) {
        header("Location: q.php?id={$qid}");
        exit;
    }

    // --- Items-IDs für diesen Fragebogen holen ---
    $stmt = $pdo->prepare("SELECT id FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
    $stmt->execute([$qid]);
    $item_ids = array_column($stmt->fetchAll(), 'id');
    if (empty($item_ids)) {
        throw new RuntimeException("Keine Items für Fragebogen ID {$qid} definiert.");
    }

    // --- Alle Antworten laden (chronologisch) ---
    $stmt = $pdo->prepare("
        SELECT r.*, i.choicetype, i.negated, i.scale, i.item
        FROM results r
        JOIN items i ON r.item_id = i.id
        WHERE r.user_id = ? AND r.questionnaire_id = ?
        ORDER BY r.created_at ASC, r.id ASC
    ");
    $stmt->execute([$user_id, $qid]);
    $all_responses = $stmt->fetchAll();
    if (empty($all_responses)) {
        header("Location: q.php?id={$qid}");
        exit;
    }

    // --- Durchgänge rekonstruieren ---
    $runs = [];
    $curr_run = [];
    $curr_ids = [];
    foreach ($all_responses as $row) {
        $curr_run[] = $row;
        $curr_ids[] = $row['item_id'];
        if (count($curr_run) === count($item_ids)
            && count(array_unique($curr_ids)) === count($item_ids)) {
            $runs[] = $curr_run;
            $curr_run = [];
            $curr_ids = [];
        }
    }

    // --- Letzten vollständigen Durchgang wählen ---
    $responses = end($runs) ?: [];

    // --- Fallback: Wenn kein vollständiger Durchgang, versuche letzten N-Einträge ---
    if (empty($responses)) {
        $slice = array_slice($all_responses, -count($item_ids));
        $slice_ids = array_column($slice, 'item_id');
        if (count($slice) === count($item_ids)
            && count(array_unique($slice_ids)) === count($item_ids)) {
            $responses = $slice;
        }
    }

    // --- Wenn immer noch nichts, zurück zum Ausfüllen ---
    if (empty($responses)) {
        header("Location: q.php?id={$qid}");
        exit;
    }

    // --- Skalen-Gruppierung ---
    $skala_map = [];
    foreach ($responses as $r) {
        $scale = trim($r['scale'] ?? '');
        if ($scale === '') $scale = '_gesamt';
        $skala_map[$scale][] = $r;
    }
    if (!isset($skala_map['_gesamt'])) {
        $skala_map['_gesamt'] = $responses;
    }

    // --- Hilfsfunktionen ---
    function isLikert($ct)   { return in_array(intval($ct), [3,4,5,6,7]); }
    function minMaxForSkala($items) {
        $min = 0; $max = 0;
        foreach ($items as $it) {
            $ct = intval($it['choicetype']);
            if (isLikert($ct)) { $max += $ct; }
            elseif ($ct === 0)   { $max += 100; }
            else                { $max += 1; }
        }
        return [$min, $max];
    }
    function calcWertForSkala($items) {
        $sum = 0;
        foreach ($items as $it) {
            $val = intval($it['result']);
            $ct  = intval($it['choicetype']);
            if (isLikert($ct)) {
                if ($it['negated']) { $val = $ct - $val; }
            } elseif ($ct === 0) {
                if ($it['negated']) { $val = 100 - $val; }
            } else {
                if ($it['negated']) { $val = $val===1 ? 0 : 1; }
            }
            $sum += $val;
        }
        return $sum;
    }
    function getItemMax($ct) {
        if (isLikert($ct)) return $ct;
        if ($ct===0)       return 100;
        return 1;
    }
    function getBarClass($pct) {
        if ($pct < 0.33) return 'bg-danger';
        if ($pct < 0.66) return 'bg-warning';
        return 'bg-success';
    }

    // === Ende der PHP-Logik; Ausgabe starten ===
    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dein Ergebnis | <?= htmlspecialchars($fragebogen['name']) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background:#f8fafc; }
            .card { box-shadow: 0 4px 24px rgba(0,0,0,0.05); }
            .progress { height:1.7rem; }
            .scale-head { font-size:1.1em; font-weight:600; }
            .subtext    { font-size:0.95em; color:#7d7d7d; }
            .skala-block { margin-bottom:2.5rem; }
        </style>
    </head>
    <body>
    <div class="container py-4" style="max-width:820px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Dein Ergebnis</h3>
            <a href="index.php" class="btn btn-outline-primary btn-sm">Zurück zur Übersicht</a>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-2"><?= htmlspecialchars($fragebogen['name']) ?></h5>
                <p class="mb-2 text-muted"><?= nl2br(htmlspecialchars($fragebogen['description'])) ?></p>
            </div>
        </div>

        <?php foreach ($skala_map as $scaleName => $items):
            if (empty($items)) continue;
            list($min,$max) = minMaxForSkala($items);
            $sum = calcWertForSkala($items);
            $pct = ($max>$min) ? ($sum-$min)/($max-$min) : 1;
            $barclass = getBarClass($pct);
            $label = ($scaleName === '_gesamt') ? 'Gesamtergebnis' : htmlspecialchars($scaleName);

            // Anzeige-Logik
            $ct = intval($items[0]['choicetype']);
            if (isLikert($ct) || $ct===0) {
                $anzeige = round($sum/count($items),2) . ' / ' . getItemMax($ct);
            } else {
                $anzeige = $sum . ' / ' . $max;
            }
        ?>
        <div class="skala-block">
            <div class="scale-head mb-2"><?= $label ?></div>
            <div class="progress mb-2" title="<?= round($pct*100) ?>%">
                <div class="progress-bar <?= $barclass ?>" role="progressbar"
                     style="width:<?= round($pct*100) ?>%;"
                     aria-valuenow="<?= $sum ?>" aria-valuemin="<?= $min ?>" aria-valuemax="<?= $max ?>">
                    <?= htmlspecialchars($anzeige) ?>
                </div>
            </div>
            <div class="subtext">
                <?php if (isLikert($ct) || $ct===0): ?>
                    Mittelwert aus <?= count($items) ?> Item<?= count($items)>1?'s':'' ?> (0 minimal, <?= getItemMax($ct) ?> maximal)
                <?php else: ?>
                    Summenwert aus <?= count($items) ?> Item<?= count($items)>1?'s':'' ?> (0 minimal, <?= $max ?> maximal)
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="alert alert-info mt-5 mb-0">
            <strong>Hinweis:</strong> Normwert-Interpretation folgt, sobald ausreichend Daten vorliegen.<br>
            <span class="text-muted" style="font-size:0.92em;">(Deine Angaben bleiben anonymisiert.)</span>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php

} catch (Throwable $e) {
    // Fängt alle Exceptions
    http_response_code(500);
    echo "<h1>Unerwarteter Fehler</h1><pre>"
         . htmlspecialchars($e->getMessage())
         . "</pre>";
    exit;
}
?>
