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

// Fragebogen auslesen (inkl. choice_type)
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

// Items-IDs für Vollständigkeits-Check holen
$stmt = $pdo->prepare("SELECT id FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
$stmt->execute([$qid]);
$item_ids = array_column($stmt->fetchAll(), 'id');
if (empty($item_ids)) {
    die('<div class="alert alert-danger m-5">Keine Items für diesen Fragebogen definiert.</div>');
}

// Alle Antworten laden
$stmt = $pdo->prepare("
    SELECT r.*, i.negated, i.scale, i.item
    FROM results r
    JOIN items i ON r.item_id = i.id
    WHERE r.user_id = ? AND r.questionnaire_id = ?
    ORDER BY r.created_at ASC, r.id ASC
");
$stmt->execute([$user_id, $qid]);
$all_responses = $stmt->fetchAll();
if (empty($all_responses)) {
    header("Location: q.php?id=$qid");
    exit;
}

// Durchgänge rekonstruieren
$runs = [];
$curr = []; $ids = [];
foreach ($all_responses as $row) {
    $curr[] = $row;
    $ids[]  = $row['item_id'];
    if (count($curr) === count($item_ids) && count(array_unique($ids)) === count($item_ids)) {
        $runs[] = $curr;
        $curr = []; $ids = [];
    }
}

// Letzten vollständigen Durchgang wählen
$responses = end($runs) ?: [];

// Fallback: letzte N-Einträge, falls oben nichts gefunden
if (empty($responses)) {
    $slice = array_slice($all_responses, -count($item_ids));
    $slice_ids = array_column($slice, 'item_id');
    if (count($slice) === count($item_ids) && count(array_unique($slice_ids)) === count($item_ids)) {
        $responses = $slice;
    }
}

// Wenn noch leer: zurück zur Eingabe
if (empty($responses)) {
    header("Location: q.php?id=$qid");
    exit;
}

// Skalen gruppieren
$skala_map = [];
foreach ($responses as $r) {
    $scale = trim($r['scale'] ?? '') ?: '_gesamt';
    $skala_map[$scale][] = $r;
}
if (!isset($skala_map['_gesamt'])) {
    $skala_map['_gesamt'] = $responses;
}

// Hilfsfunktionen (nutzen $fragebogen['choice_type'])
function isLikert($ct)   { return in_array($ct, [3,4,5,6,7], true); }
function minMaxForSkala($count, $ct) {
    $min = 0;
    $max = isLikert($ct) ? $count * $ct : ($ct === 0 ? $count * 100 : $count * 1);
    return [$min, $max];
}
function calcWert($items, $ct) {
    $sum = 0;
    foreach ($items as $it) {
        $val = intval($it['result']);
        if (isLikert($ct)) {
            $sum += ($it['negated'] ? ($ct - $val) : $val);
        } elseif ($ct === 0) {
            $sum += ($it['negated'] ? (100 - $val) : $val);
        } else {
            $sum += ($it['negated'] ? ($val===1?0:1) : $val);
        }
    }
    return $sum;
}
function getBarClass($p) {
    if ($p < 0.33) return 'bg-danger';
    if ($p < 0.66) return 'bg-warning';
    return 'bg-success';
}
function getItemMax($ct) {
    return isLikert($ct) ? $ct : ($ct===0 ? 100 : 1);
}

$ct = intval($fragebogen['choice_type']);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ergebnis | <?= htmlspecialchars($fragebogen['name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8fafc; }
    .card { box-shadow:0 4px 24px rgba(0,0,0,0.05); }
    .progress { height:1.7rem; }
    .scale-head { font-size:1.1em; font-weight:600; }
    .subtext { font-size:0.95em; color:#7d7d7d; }
    .skala-block { margin-bottom:2.5rem; }
  </style>
</head>
<body>
<div class="container py-4" style="max-width:820px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Dein Ergebnis</h3>
    <a href="index.php" class="btn btn-outline-primary btn-sm">Zur Übersicht</a>
  </div>
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-2"><?= htmlspecialchars($fragebogen['name']) ?></h5>
      <p class="mb-2 text-muted"><?= nl2br(htmlspecialchars($fragebogen['description'])) ?></p>
    </div>
  </div>

  <?php foreach ($skala_map as $scaleName => $items):
    $count = count($items);
    list($min,$max) = minMaxForSkala($count, $ct);
    $sum = calcWert($items, $ct);
    $pct = $max>0 ? ($sum-$min)/($max-$min) : 1;
    $bar = getBarClass($pct);
    $label = $scaleName === '_gesamt' ? 'Gesamtergebnis' : htmlspecialchars($scaleName);

    if (isLikert($ct) || $ct===0) {
      $display = round($sum/$count,2) . ' / ' . getItemMax($ct);
    } else {
      $display = $sum . ' / ' . $max;
    }
  ?>
    <div class="skala-block">
      <div class="scale-head mb-2"><?= $label ?></div>
      <div class="progress mb-2" title="<?= round($pct*100) ?>%">
        <div class="progress-bar <?= $bar ?>" role="progressbar"
             style="width:<?= round($pct*100) ?>%;"
             aria-valuenow="<?= $sum ?>" aria-valuemin="<?= $min ?>" aria-valuemax="<?= $max ?>">
          <?= htmlspecialchars($display) ?>
        </div>
      </div>
      <div class="subtext">
        <?php if (isLikert($ct) || $ct===0): ?>
          Mittelwert aus <?= $count ?> Item<?= $count>1?'s':'' ?> (0 minimal, <?= getItemMax($ct) ?> maximal)
        <?php else: ?>
          Summenwert aus <?= $count ?> Item<?= $count>1?'s':'' ?> (0 minimal, <?= $max ?> maximal)
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="alert alert-info mt-5 mb-0">
    <strong>Hinweis:</strong> Normwert-Interpretation folgt, sobald ausreichend Daten vorliegen.<br>
    <span class="text-muted" style="font-size:0.92em;">(Angaben bleiben anonymisiert.)</span>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
