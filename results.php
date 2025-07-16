<?php
require_once 'include.inc.php';
$pageTitle       = 'Ergebnis';
$pageDescription = 'Hier siehst du dein persönliches Ergebnis mit allen Details.';
require_once 'navbar.inc.php';

// Schwellen
define('NORM_THRESHOLD', 30);
define('CRONBACH_THRESHOLD', 30);

// User‑ID aus Cookie
function getUserIdFromCookie() {
    return (!empty($_COOKIE['profile']) && ctype_digit($_COOKIE['profile']))
        ? intval($_COOKIE['profile'])
        : false;
}

// Fragebogen‑ID & Daten laden
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
if (!$qid) {
    http_response_code(400);
    die('<div class="alert alert-danger m-5">Ungültige Anfrage.</div>');
}
$stmt = $pdo->prepare("SELECT *, operationalization FROM questionnaires WHERE id = ?");
$stmt->execute([$qid]);
$Q = $stmt->fetch();
if (!$Q) {
    http_response_code(404);
    die('<div class="alert alert-danger m-5">Fragebogen nicht gefunden.</div>');
}
// Operationalisierung parsen (JSON: ['global'=>string, 'subscales'=>[scale=>string]])
$ops = json_decode($Q['operationalization'] ?: '{}', true);

// Sicherstellen, dass User existiert
$user_id = getUserIdFromCookie();
if (!$user_id) {
    header("Location: q.php?id={$qid}");
    exit;
}

// Alle Items & Antworten laden
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

// Nur letzter vollständiger Run
$itemIds = array_unique(array_column($responses, 'id'));
$fullRuns = [];
$temp = [];
$seen = [];
foreach ($responses as $r) {
    $temp[] = $r;
    $seen[] = $r['id'];
    if (count($seen) === count($itemIds)) {
        $fullRuns = $temp;
        $temp = [];
        $seen = [];
    }
}
$R = count($fullRuns) === count($itemIds) ? $fullRuns : array_slice($responses, -count($itemIds));

// Gruppieren nach Subskala
$byScale = [];
foreach ($R as $r) {
    $scale = trim($r['scale'] ?: '_Gesamt');
    $byScale[$scale][] = $r;
}

// Hilfsfunktionen
$ct = intval($Q['choice_type']);
function isLikert($c){ return in_array($c, [3,4,5,6,7], true); }
function minMax($n,$c){
    if (isLikert($c)) return [0, $n*$c];
    if ($c===0)      return [0, $n*100];
    return [0, $n];
}
function calcSum($arr,$c){
    $sum=0;
    foreach($arr as $i){
        $v = intval($i['result']);
        if (isLikert($c))      $sum += $i['negated'] ? ($c-$v) : $v;
        elseif ($c===0)        $sum += $i['negated'] ? (100-$v) : $v;
        else                    $sum += $i['negated'] ? ($v===1?0:1) : $v;
    }
    return $sum;
}
function barClass($p){ return $p<.33?'bg-danger':($p<.66?'bg-warning':'bg-success'); }
function itemMax($c){ return isLikert($c)?$c:($c===0?100:1); }

// Teilnehmer
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM results WHERE questionnaire_id = ?");
$stmt->execute([$qid]);
$participants = intval($stmt->fetchColumn());

// Share‑Link & Text
$shareUrl = (isset($_SERVER['HTTPS'])?'https':'http')
          .'://'.$_SERVER['HTTP_HOST']
          .dirname($_SERVER['REQUEST_URI'])
          ."/q.php?id={$qid}";
$totalSum = calcSum($R, $ct);
$avg = number_format($totalSum/count($R),1,',','');
$display = "{$avg} von ".itemMax($ct);
$shareText = rawurlencode(
  "🎉 Mein Ergebnis bei „{$Q['name']}“: {$display}! ".
  "Teste dich selbst: {$shareUrl}"
);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ergebnis | <?=htmlspecialchars($Q['name'])?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f2f4f8; }
    .hero-card { background:#fff; border-radius:.5rem; box-shadow:0 4px 20px rgba(0,0,0,0.04); }
    .hero-card h2 { font-size:1.5rem; }
    .subscale-card { margin-bottom:1.5rem; }
    .subscale-header { display:flex; align-items:center; font-weight:600; }
    .subscale-header .icon { font-size:1.2rem; margin-right:.5rem; color:#5a8dee; }
    .info-pop { cursor:pointer; margin-left:.5rem; color:#6c757d; }
    .progress { height:1.6rem; border-radius:.4rem; }
    .share-panel { background:#fff; border-radius:.5rem; box-shadow:0 4px 20px rgba(0,0,0,0.04); }
  </style>
</head>
<body>
<div class="container py-5" style="max-width:800px;">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dein Ergebnis</h1>
    <a href="index.php" class="btn btn-outline-primary">Zur Übersicht</a>
  </div>

  <!-- Hero‑Card mit globaler Operationalisierung -->
  <div class="hero-card p-4 mb-5">
    <h2><?=htmlspecialchars($Q['name'])?></h2>
    <?php if(!empty($ops['global'])): ?>
      <p class="text-muted mb-0"><?=nl2br(htmlspecialchars($ops['global']))?></p>
    <?php endif; ?>
  </div>

  <!-- Subskalen -->
  <?php foreach($byScale as $scale => $arr):
    $n = count($arr);
    list($mn,$mx) = minMax($n,$ct);
    $sum = calcSum($arr,$ct);
    $pct = $mx>$mn? ($sum-$mn)/($mx-$mn) : 1;
    $cls = barClass($pct);
    $label = ($scale===' _Gesamt'||$scale==='_gesamt') ? 'Gesamtergebnis' : htmlspecialchars($scale);
    $disp = isLikert($ct)||$ct===0
          ? number_format($sum/$n,1,',','')." / ".itemMax($ct)
          : "{$sum} / {$mx}";
    $subdesc = $ops['subscales'][$scale] ?? '';
  ?>
    <div class="subscale-card card p-3">
      <div class="subscale-header mb-2">
        <span class="icon">🔍</span>
        <span><?=$label?></span>
        <?php if($subdesc): ?>
          <span tabindex="0"
                class="info-pop"
                data-bs-toggle="popover"
                data-bs-trigger="focus"
                title="<?=$label?>"
                data-bs-content="<?=htmlspecialchars($subdesc)?>">
            ℹ️
          </span>
        <?php endif; ?>
      </div>
      <div class="progress mb-2">
        <div class="progress-bar <?=$cls?>" role="progressbar"
             style="width:<?=round($pct*100)?>%;"
             aria-valuenow="<?=$sum?>" aria-valuemin="<?=$mn?>" aria-valuemax="<?=$mx?>">
          <?=$disp?>
        </div>
      </div>
      <div class="text-secondary small">
        <?php if(isLikert($ct)||$ct===0): ?>
          Mittelwert aus <?=$n?> Item<?=$n>1?'s':''?> (0 minimal, <?=itemMax($ct)?> maximal)
        <?php else: ?>
          Summenwert aus <?=$n?> Item<?=$n>1?'s':''?> (0 minimal, <?=$mx?> maximal)
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Normwert‑Statistik -->
  <div class="card p-4 mb-5">
    <h5>Normwert‑Statistik</h5>
    <p>Teilnahmen bisher: <strong><?=$participants?></strong></p>
    <?php if($participants < NORM_THRESHOLD): ?>
      <p class="mb-1">Noch <strong><?=NORM_THRESHOLD-$participants?></strong> Teilnahmen bis aussagekräftigen Normwerten.</p>
    <?php else: ?>
      <p class="mb-1 text-success">Ausreichend Teilnahmen für Normwerte (≥<?=NORM_THRESHOLD?>).</p>
    <?php endif; ?>
    <?php if($participants < CRONBACH_THRESHOLD): ?>
      <p>Noch <strong><?=CRONBACH_THRESHOLD-$participants?></strong> Teilnahmen bis Cronbach’s Alpha.</p>
    <?php else: ?>
      <p class="mb-0"><strong>Cronbach’s Alpha</strong> wird berechnet (ab <?=CRONBACH_THRESHOLD?> Teilnahmen).</p>
    <?php endif; ?>
  </div>

  <!-- Share‑Panel -->
  <div class="share-panel p-4 mb-3 text-center">
    <h5>Teile dein Ergebnis</h5>
    <p class="mb-3">🎯 Fordere deine Freunde heraus &amp; hilf mit, die Normwerte zu verbessern!</p>
    <a href="mailto:?subject=Mein Ergebnis bei <?=rawurlencode($Q['name'])?>&body=<?=$shareText?>"
       class="btn btn-primary me-2">E‑Mail</a>
    <a href="https://api.whatsapp.com/send?text=<?=$shareText?>" target="_blank"
       class="btn btn-success">WhatsApp</a>
  </div>

  <!-- Footer‑Hinweis -->
  <div class="alert alert-info text-center">
    Normwert‑Interpretation folgt, sobald genügend Daten vorliegen.<br>
    Alle Angaben bleiben anonymisiert.
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Popovers initialisieren
document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el=>{
  new bootstrap.Popover(el);
});
</script>
<?php include('footer.inc.php'); ?>
