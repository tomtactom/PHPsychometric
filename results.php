<?php
require_once 'include.inc.php';

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
    die('<div class="alert alert-danger m-5">Ungültige Anfrage.</div>');
}

// Fragebogen auslesen
$stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
$stmt->execute([$qid]);
$fragebogen = $stmt->fetch();
if (!$fragebogen) {
    http_response_code(404);
    die('<div class="alert alert-danger m-5">Fragebogen nicht gefunden.</div>');
}

// Nutzer prüfen
$user_id = getUserIdFromCookie();
if (!$user_id) {
    header("Location: q.php?id=$qid");
    exit;
}

// Alle Antworten des Nutzers zu diesem Fragebogen abrufen
$stmt = $pdo->prepare("SELECT r.*, i.choicetype, i.negated, i.scale, i.item
                       FROM results r
                       JOIN items i ON r.item_id = i.id
                       WHERE r.user_id = ? AND r.questionnaire_id = ?
                       ORDER BY i.id ASC");
$stmt->execute([$user_id, $qid]);
$responses = $stmt->fetchAll();

// Keine Antworten gefunden?
if (!$responses) {
    header("Location: q.php?id=$qid");
    exit;
}

// Skalen-Zusammenstellung: Map [scale_name] => [Items...]
// Wenn keine scale vergeben ist, dann kommt alles zu '_gesamt'
$skala_map = [];
foreach ($responses as $row) {
    $scale = $row['scale'] ?? '';
    if ($scale === '' || is_null($scale)) $scale = '_gesamt';
    if (!isset($skala_map[$scale])) $skala_map[$scale] = [];
    $skala_map[$scale][] = $row;
}

// Für den gesamten Fragebogen (alle Antworten), falls nicht schon unter _gesamt zusammengefasst
if (!isset($skala_map['_gesamt'])) {
    $skala_map['_gesamt'] = $responses;
}

// Hilfsfunktion: Skalentyp als Likert identifizieren (typ 3-7)
function isLikert($choicetype) {
    return in_array(intval($choicetype), [3,4,5,6,7]);
}
// Hilfsfunktion: Skalentyp als "summiert" (typ 1,2)
function isSummiert($choicetype) {
    return in_array(intval($choicetype), [1,2]);
}
// Theoretischer Min/Max Wert berechnen
function minMaxForSkala($items) {
    // Alle Items sind vom gleichen Typ; wir nehmen das erste als Referenz
    $min = 0;
    $max = 0;
    foreach ($items as $item) {
        $ct = intval($item['choicetype']);
        if (isLikert($ct)) {
            // 3-stufig: Werte 0,1,2  --> Max=2
            $max += $ct;   // z.B. type 5 = Werte 0-5
        } elseif ($ct == 0) {
            $max += 100;
        } else {
            $max += 1;
        }
        // min bleibt immer 0
    }
    return [$min, $max];
}
// Wert für die Skala berechnen
function calcWertForSkala($items) {
    $sum = 0;
    foreach ($items as $item) {
        $val = $item['result'];
        // Negierte Items: Wert invertieren (nur bei Likert und Summenwert)
        $ct = intval($item['choicetype']);
        if (isLikert($ct)) {
            if ($item['negated']) {
                $val = $ct - intval($val);
            }
            $sum += intval($val);
        } elseif ($ct == 0) {
            // Intervallskala/Slider: Negation sinnvoll?
            if ($item['negated']) {
                $val = 100 - intval($val);
            }
            $sum += intval($val);
        } else {
            // Wahr/Falsch, Stimme zu/nicht zu, etc.
            if ($item['negated']) {
                $val = intval($val) == 1 ? 0 : 1;
            }
            $sum += intval($val);
        }
    }
    return $sum;
}
// Maximum eines Items für Progressbar (Likert: z.B. 5, Slider: 100, sonst 1)
function getItemMax($ct) {
    if (isLikert($ct)) return $ct;
    if ($ct == 0) return 100;
    return 1;
}

// Farben für Progressbar (nach prozentualer Ausprägung)
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
        list($min, $max) = minMaxForSkala($skala_items);
        $sum = calcWertForSkala($skala_items);
        $percent = $max > $min ? ($sum-$min)/($max-$min) : 1.0;
        $barclass = getBarClass($percent);
        $label = ($skala_name === '_gesamt') ? 'Gesamtergebnis' : htmlspecialchars($skala_name);
        ?>
        <div class="skala-block">
            <div class="scale-head mb-2"><?= $label ?></div>
            <div class="progress mb-2" title="<?= round($percent*100) ?>%">
                <div class="progress-bar <?= $barclass ?>" role="progressbar"
                     style="width:<?= round($percent*100) ?>%;"
                     aria-valuenow="<?= $sum ?>" aria-valuemin="<?= $min ?>" aria-valuemax="<?= $max ?>">
                    <?= isLikert($skala_items[0]['choicetype']) || $skala_items[0]['choicetype'] == 0
                        ? round($sum / count($skala_items), 2) . ' / ' . getItemMax($skala_items[0]['choicetype'])
                        : $sum . ' / ' . $max ?>
                </div>
            </div>
            <div class="subtext">
                <?php if (isLikert($skala_items[0]['choicetype']) || $skala_items[0]['choicetype'] == 0): ?>
                    Mittelwert aus <?= count($skala_items) ?> Item<?= count($skala_items) > 1 ? 's' : '' ?> (0 = minimal, <?= getItemMax($skala_items[0]['choicetype']) ?> = maximal)
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
