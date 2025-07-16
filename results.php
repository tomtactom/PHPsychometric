<?php
require_once 'include.inc.php';
$pageTitle       = 'Ergebnis';
$pageDescription = 'Hier siehst du dein persönliches Ergebnis mit allen Details.';
require_once 'navbar.inc.php';

// Schwellenwerte
define('NORM_THRESHOLD', 30);
define('CRONBACH_THRESHOLD', 30);

// Nutzer‑ID aus Cookie
function getUserIdFromCookie() {
    return (!empty($_COOKIE['profile']) && ctype_digit($_COOKIE['profile']))
        ? intval($_COOKIE['profile'])
        : false;
}

// Fragebogen‑ID laden
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
if (!$qid) {
    http_response_code(400);
    die('<div class="alert alert-danger m-5">Ungültige Anfrage.</div>');
}

// Fragebogen inkl. Operationalisierung
$stmt = $pdo->prepare("SELECT *, operationalization FROM questionnaires WHERE id = ?");
$stmt->execute([$qid]);
$Q = $stmt->fetch();
if (!$Q) {
    http_response_code(404);
    die('<div class="alert alert-danger m-5">Fragebogen nicht gefunden.</div>');
}

// JSON‑Operationalisierung parsen
$ops = json_decode($Q['operationalization'] ?: '{}', true);

// Sicherstellen, dass Nutzer existiert
$user_id = getUserIdFromCookie();
if (!$user_id) {
    header("Location: q.php?id={$qid}");
    exit;
}

// Antworten des Nutzers
$stmt = $pdo->prepare("
    SELECT i.id, i.scale, i.item, i.negated,
           r.result, r.created_at
      FROM items i
      JOIN results r ON r.item_id = i.id
     WHERE i.questionnaire_id = ?
       AND r.user_id = ?
     ORDER BY r.created_at, r.id
");
$stmt->execute([$qid, $user_id]);
$responses = $stmt->fetchAll();
if (empty($responses)) {
    header("Location: q.php?id={$qid}");
    exit;
}

// Letzten vollständigen Durchgang extrahieren
$itemIds = array_unique(array_column($responses, 'id'));
$temp = []; $seen = []; $fullRun = [];
foreach ($responses as $r) {
    $temp[] = $r;
    $seen[] = $r['id'];
    if (count($seen) === count($itemIds)) {
        $fullRun = $temp;
        $temp = []; $seen = [];
    }
}
$R = (count($fullRun) === count($itemIds)) ? $fullRun : array_slice($responses, -count($itemIds));

// Gruppieren nach Subskala
$byScale = [];
foreach ($R as $r) {
    $scale = trim($r['scale'] ?: '_Gesamt');
    $byScale[$scale][] = $r;
}

// Hilfsfunktionen
$ct = intval($Q['choice_type']);
function isLikert($c) { return in_array($c, [3,4,5,6,7], true); }
function minMax($n, $c) {
    if (isLikert($c)) return [0, $n * $c];
    if ($c === 0)     return [0, $n * 100];
    return [0, $n];
}
function calcSum($arr, $c) {
    $sum = 0;
    foreach ($arr as $i) {
        $v = intval($i['result']);
        if (isLikert($c))      $sum += $i['negated'] ? ($c - $v) : $v;
        elseif ($c === 0)       $sum += $i['negated'] ? (100 - $v) : $v;
        else                    $sum += $i['negated'] ? ($v === 1 ? 0 : 1) : $v;
    }
    return $sum;
}
function barClass($p) { return $p < .33 ? 'bg-danger' : ($p < .66 ? 'bg-warning' : 'bg-success'); }
function itemMax($c)  { return isLikert($c) ? $c : ($c === 0 ? 100 : 1); }

/**
 * Intuitive Fünf‑Stufen‑Label ohne Normvorgabe
 */
function interpretLabel($value, $min, $max) {
    $ratio = ($max - $min) > 0 ? ($value - $min) / ($max - $min) : 0.5;
    if ($ratio >= 0.80) return "Sehr hoch";
    if ($ratio >= 0.60) return "Hoch";
    if ($ratio >= 0.40) return "Mittel";
    if ($ratio >= 0.20) return "Niedrig";
    return "Sehr niedrig";
}

// Gesamt‑Werte für das Radial‑Chart
list($overallMin, $overallMax) = minMax(count($R), $ct);
$totalSum = calcSum($R, $ct);
$overallPct = ($overallMax > $overallMin)
            ? ($totalSum - $overallMin) / ($overallMax - $overallMin)
            : 0;
$overallLabel = interpretLabel($totalSum, $overallMin, $overallMax);
$displayRaw   = number_format($totalSum / count($R), 1, ',', '') . ' von ' . itemMax($ct);

// Teilnehmerzahl
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM results WHERE questionnaire_id = ?");
$stmt->execute([$qid]);
$participants = intval($stmt->fetchColumn());

// Share‑Link & Text
$shareUrl  = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST']
           . dirname($_SERVER['REQUEST_URI'])
           . "/q.php?id={$qid}";
$shareText = rawurlencode("🎉 Mein Ergebnis bei „{$Q['name']}“: {$displayRaw}! " . $shareUrl);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ergebnis | <?=htmlspecialchars($Q['name'])?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f2f4f8; }
    .radial-progress {
      --size: 140px;
      --thickness: 12px;
      --value: <?= round($overallPct*100) ?>;
      width: var(--size);
      height: var(--size);
      border-radius: 50%;
      background:
        conic-gradient(
          #0d6efd calc(var(--value) * 1%),
          #e9ecef 0
        );
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      color: #0d6efd;
      margin: 0 auto 1rem;
      position: relative;
    }
    .radial-progress::before {
      content: '';
      position: absolute;
      width: calc(var(--size) - var(--thickness)*2);
      height: calc(var(--size) - var(--thickness)*2);
      background: #f2f4f8;
      border-radius: 50%;
    }
    .radial-progress span {
      position: relative;
      z-index: 1;
      font-size: 1.2rem;
    }
    .hero {
      background: #fff; padding: 1.5rem; border-radius: .5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      text-align: center; margin-bottom: 2rem;
    }
    .subcard {
      margin-bottom: 1.5rem; padding: 1rem; background: #fff;
      border-radius: .5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    }
    .subheader { font-weight: 600; margin-bottom: .5rem; }
    .subdesc  { color: #555; font-size: .95rem; margin-bottom: .75rem; }
    .interpret { font-weight: bold; margin-top: .5rem; color: #333; }
    .normcard, .sharecard {
      background: #fff; padding: 1.5rem; border-radius: .5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.04); margin-bottom: 2rem;
    }
  </style>
</head>
<body>
<div class="container py-5" style="max-width:800px;">

  <!-- Gesamt‑Ergebnis -->
  <div class="hero">
    <h2><?= htmlspecialchars($Q['name']) ?></h2>
    <div class="radial-progress"><span><?= round($overallPct*100) ?> %</span></div>
    <p class="h5 mb-1"><?= htmlspecialchars($displayRaw) ?></p>
    <p class="interpret"><?= htmlspecialchars($overallLabel) ?></p>
    <p class="small text-muted">Dies ist eine Roh‑Messung ohne Normvergleich.</p>
  </div>

  <!-- Subskalen -->
  <?php foreach ($byScale as $scale => $arr):
    $n  = count($arr);
    list($mn, $mx) = minMax($n, $ct);
    $sum = calcSum($arr, $ct);
    $pct = ($mx > $mn) ? ($sum - $mn) / ($mx - $mn) : 0;
    $cls = barClass($pct);
    $label = ($scale === '_Gesamt') ? 'Gesamtergebnis' : htmlspecialchars($scale);
    $disp  = isLikert($ct) || $ct === 0
           ? number_format($sum/$n,1,',','').' / '.itemMax($ct)
           : "{$sum} / {$mx}";
    $subdesc = $ops['subscales'][$scale] ?? '';
    $interp  = interpretLabel($sum, $mn, $mx);
  ?>
    <div class="subcard">
      <div class="subheader"><?= $label ?></div>
      <?php if ($subdesc): ?>
        <div class="subdesc"><?= nl2br(htmlspecialchars($subdesc)) ?></div>
      <?php endif; ?>
      <div class="progress mb-2">
        <div class="progress-bar <?= $cls ?>" style="width:<?= round($pct*100) ?>%">
          <?= htmlspecialchars($disp) ?>
        </div>
      </div>
      <div class="interpret"><?= htmlspecialchars($interp) ?></div>
    </div>
  <?php endforeach; ?>

  <!-- Normwert‑Statistik -->
  <div class="normcard">
    <h5>Normwert‑Statistik</h5>
    <p>Teilnahmen bisher: <strong><?= $participants ?></strong></p>
    <?php if ($participants < NORM_THRESHOLD): ?>
      <p>Noch <strong><?= NORM_THRESHOLD - $participants ?></strong> für aussagekräftige Normwerte.</p>
    <?php else: ?>
      <p class="text-success">Genügend für Normwerte (≥<?= NORM_THRESHOLD ?>).</p>
    <?php endif; ?>
    <?php if ($participants < CRONBACH_THRESHOLD): ?>
      <p>Noch <strong><?= CRONBACH_THRESHOLD - $participants ?></strong> für Cronbach’s Alpha.</p>
    <?php else: ?>
      <p><strong>Cronbach’s Alpha</strong> wird berechnet.</p>
    <?php endif; ?>
  </div>

  <!-- Share -->
  <div class="sharecard text-center">
    <h5>Teile dein Ergebnis</h5>
    <p>Fordere Freunde heraus &amp; verbessere die Normwerte!</p>
    <a href="mailto:?subject=Mein Ergebnis&body=<?= $shareText ?>"
       class="btn btn-primary me-2">E‑Mail</a>
    <a href="https://api.whatsapp.com/send?text=<?= $shareText ?>"
       target="_blank" class="btn btn-success">WhatsApp</a>
  </div>

  <div class="alert alert-info text-center">
    Ausführliche Interpretation folgt, sobald genügend Daten vorliegen.<br>
    Alle Angaben bleiben anonym.
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include('footer.inc.php'); ?>
