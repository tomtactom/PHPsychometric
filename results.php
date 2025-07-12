<?php
// Debugging aktivieren
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'include.inc.php';

// Debug-Ausgabe-Hilfsfunktion
function debug_out($msg, $data = null) {
    echo "<div style='background:#fee;border:1px solid #f99; color:#900; font-size:13px; padding:7px 14px; margin:10px 0; border-radius:5px;'>";
    echo "<strong>DEBUG:</strong> $msg";
    if ($data !== null) {
        echo "<pre style='margin:0; padding:0 0 0 16px; font-size:12px; color:#900;'>";
        print_r($data);
        echo "</pre>";
    }
    echo "</div>";
}

// Nutzer-Identifikation per Cookie
function getUserIdFromCookie() {
    if (!empty($_COOKIE['profile']) && ctype_digit($_COOKIE['profile'])) {
        return intval($_COOKIE['profile']);
    }
    return false;
}

// Fragebogen-ID prüfen
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
if (!$qid) {
    http_response_code(400);
    debug_out("Kein oder ungültiger Fragebogen (qid)", $_GET);
    die('<div class="alert alert-danger m-5">Ungültige Anfrage.</div>');
}

// Fragebogen auslesen
$stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
$stmt->execute([$qid]);
$fragebogen = $stmt->fetch();
if (!$fragebogen) {
    http_response_code(404);
    debug_out("Fragebogen nicht gefunden", $qid);
    die('<div class="alert alert-danger m-5">Fragebogen nicht gefunden.</div>');
}

// Nutzer prüfen
$user_id = getUserIdFromCookie();
if (!$user_id) {
    debug_out("Kein User-Cookie gefunden oder ungültig", $_COOKIE);
    header("Location: q.php?id=$qid");
    exit;
}

// Items des Fragebogens holen (z.B. für Vollständigkeits-Check)
$stmt = $pdo->prepare("SELECT id FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
$stmt->execute([$qid]);
$item_ids = array_column($stmt->fetchAll(), 'id');
if (!$item_ids) {
    debug_out("Keine Items für diesen Fragebogen gefunden.", $qid);
    die('<div class="alert alert-danger m-5">Für diesen Fragebogen sind keine Items definiert.</div>');
}

// Alle Durchgänge dieses Nutzers holen (sortiert)
$stmt = $pdo->prepare("SELECT r.*, i.choicetype, i.negated, i.scale, i.item
    FROM results r
    JOIN items i ON r.item_id = i.id
    WHERE r.user_id = ? AND r.questionnaire_id = ?
    ORDER BY r.created_at ASC, r.id ASC");
$stmt->execute([$user_id, $qid]);
$all_responses = $stmt->fetchAll();

if (!$all_responses) {
    debug_out("Keine Antworten in results gefunden", ['user_id'=>$user_id, 'questionnaire_id'=>$qid]);
    header("Location: q.php?id=$qid");
    exit;
}

// Debug-Ausgabe: Rohdaten zeigen
debug_out("Alle gefundenen Antworten (all_responses):", $all_responses);

// Durchgänge rekonstruieren: Antworten je Durchgang (= eine Antwort je Item) gruppieren
$runs = [];
$curr_run = [];
$curr_ids = [];
foreach ($all_responses as $row) {
    $curr_run[] = $row;
    $curr_ids[] = $row['item_id'];
    // Wenn alle Item-IDs einmal beisammen, ist ein vollständiger Durchgang erreicht
    if (count($curr_run) == count($item_ids) && count(array_unique($curr_ids)) == count($item_ids)) {
        $runs[] = $curr_run;
        $curr_run = [];
        $curr_ids = [];
    }
}
debug_out("Rekonstruierte Durchgänge (runs):", $runs);

// Versuchen, den letzten vollständigen Durchgang zu nehmen
$responses = end($runs) ?: [];

// Wenn kein Durchgang gefunden, evtl. „unvollständigen“ Satz probieren?
if (!$responses && !empty($all_responses) && count($item_ids) > 0) {
    // Wir nehmen die letzten N Antworten aus $all_responses, falls alle verschiedenen Items
    $try = array_slice($all_responses, -count($item_ids));
    $test_ids = array_column($try, 'item_id');
    if (count($try) == count($item_ids) && count(array_unique($test_ids)) == count($item_ids)) {
        debug_out("Nur ein „unvollständiger“ Durchgang erkannt – nehme letzten Satz an Antworten.", $try);
        $responses = $try;
    }
}

// Immer noch keine Antworten?
if (!$responses) {
    debug_out("Keine vollständigen Durchgänge gefunden.", [
        'user_id'=>$user_id,
        'questionnaire_id'=>$qid,
        'item_ids'=>$item_ids,
        'all_responses'=>$all_responses
    ]);
    header("Location: q.php?id=$qid");
    exit;
}

// Debug-Ausgabe: Der gewertete Antwortsatz
debug_out("Zur Auswertung verwendeter Antwortsatz:", $responses);

// Skalen-Zusammenstellung: Map [scale_name] => [Items...]
$skala_map = [];
foreach ($responses as $row) {
    $scale = $row['scale'] ?? '';
    if ($scale === '' || is_null($scale)) $scale = '_gesamt';
    if (!isset($skala_map[$scale])) $skala_map[$scale] = [];
    $skala_map[$scale][] = $row;
}
if (!isset($skala_map['_gesamt'])) {
    $skala_map['_gesamt'] = $responses;
}

// Hilfsfunktion: Skalentyp als Likert identifizieren (typ 3-7)
function isLikert($choicetype) {
    return in_array(intval($choicetype), [3,4,5,6,7]);
}
function isSummiert($choicetype) {
    return in_array(intval($choicetype), [1,2]);
}
function minMaxForSkala($items) {
    $min = 0;
    $max = 0;
    foreach ($items as $item) {
        $ct = intval($item['choicetype']);
        if (isLikert($ct)) {
            $max += $ct;
        } elseif ($ct == 0) {
            $max += 100;
        } else {
            $max += 1;
        }
    }
    return [$min, $max];
}
function calcWertForSkala($items) {
    $sum = 0;
    foreach ($items as $item) {
        $val = $item['result'];
        $ct = intval($item['choicetype']);
        if (isLikert($ct)) {
            if ($item['negated']) {
                $val = $ct - intval($val);
            }
            $sum += intval($val);
        } elseif ($ct == 0) {
            if ($item['negated']) {
                $val = 100 - intval($val);
            }
            $sum += intval($val);
        } else {
            if ($item['negated']) {
                $val = intval($val) == 1 ? 0 : 1;
            }
            $sum += intval($val);
        }
    }
    return $sum;
}
function getItemMax($ct) {
    if (isLikert($ct)) return $ct;
    if ($ct == 0) return 100;
    return 1;
}
function getBarClass($percent) {
    if ($percent < 0.33) return 'bg-danger';
    if ($percent < 0.66) return 'bg-warning';
    return 'bg-success';
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dein Ergebnis | <?= htmlspecialchars($fragebogen['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8fafc;}
        .card { box-shadow: 0 4px 24px rgba(0,0,0,0.05);}
        .progress {height: 1.7rem;}
        .scale-head {font-size:1.1em; font-weight:600;}
        .subtext {font-size:0.95em; color:#7d7d7d;}
        .skala-block {margin-bottom:2.5rem;}
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
            <div class="mb-2"><?= nl2br(htmlspecialchars($fragebogen['description'])) ?></div>
        </div>
    </div>
    <?php
    foreach ($skala_map as $skala_name => $skala_items):
        if (empty($skala_items) || !isset($skala_items[0]['choicetype'])) {
            debug_out("Überspringe leeren oder fehlerhaften Skalenblock", ['skala'=>$skala_name, 'skala_items'=>$skala_items]);
            continue;
        }
        list($min, $max) = minMaxForSkala($skala_items);
        $sum = calcWertForSkala($skala_items);
        $percent = $max > $min ? ($sum-$min)/($max-$min) : 1.0;
        $barclass = getBarClass($percent);
        $label = ($skala_name === '_gesamt') ? 'Gesamtergebnis' : htmlspecialchars($skala_name);

        $ct = intval($skala_items[0]['choicetype']);
        $anzeige = '';
        if (isLikert($ct) || $ct == 0) {
            $mw = count($skala_items) > 0 ? round($sum / count($skala_items), 2) : 0;
            $anzeige = $mw . ' / ' . getItemMax($ct);
        } else {
            $anzeige = $sum . ' / ' . $max;
        }
        ?>
        <div class="skala-block">
            <div class="scale-head mb-2"><?= $label ?></div>
            <div class="progress mb-2" title="<?= round($percent*100) ?>%">
                <div class="progress-bar <?= $barclass ?>" role="progressbar"
                     style="width:<?= round($percent*100) ?>%;"
                     aria-valuenow="<?= $sum ?>" aria-valuemin="<?= $min ?>" aria-valuemax="<?= $max ?>">
                    <?= $anzeige ?>
                </div>
            </div>
            <div class="subtext">
                <?php if (isLikert($ct) || $ct == 0): ?>
                    Mittelwert aus <?= count($skala_items) ?> Item<?= count($skala_items) > 1 ? 's' : '' ?> (0 = minimal, <?= getItemMax($ct) ?> = maximal)
                <?php else: ?>
                    Summenwert aus <?= count($skala_items) ?> Item<?= count($skala_items) > 1 ? 's' : '' ?> (0 = minimal, <?= $max ?> = maximal)
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="alert alert-info mt-5 mb-0">
        <strong>Hinweis:</strong> Eine individuelle Interpretation anhand von Normwerten folgt, sobald genügend Daten vorhanden sind.<br>
        Bis dahin kannst du dein Ergebnis als numerischen Wert oder Mittelwert interpretieren.<br>
        <span class="text-muted" style="font-size:0.92em;">(Deine Angaben werden anonymisiert gespeichert.)</span>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
