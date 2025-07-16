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
// Operationalisierung parsen
$ops = json_decode($Q['operationalization'] ?: '{}', true);

// Sicherstellen, dass User existiert
$user_id = getUserIdFromCookie();
if (!$user_id) {
    header("Location: q.php?id={$qid}");
    exit;
}

// Antworten laden
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

// Letzten vollständigen Run extrahieren
$itemIds = array_unique(array_column($responses, 'id'));
$temp = []; $seen = []; $full = [];
foreach ($responses as $r) {
    $temp[] = $r;
    $seen[] = $r['id'];
    if (count($seen) === count($itemIds)) {
        $full = $temp;
        $temp = []; $seen = [];
    }
}
$R = (count($full) === count($itemIds)) ? $full : array_slice($responses, -count($itemIds));

// Gruppieren
$byScale = [];
foreach ($R as $r) {
    $scale = trim($r['scale'] ?: '_Gesamt');
    $byScale[$scale][] = $r;
}

// Helpers
$ct = intval($Q['choice_type']);
function isLikert($c){ return in_array($c,[3,4,5,6,7],true); }
function minMax($n,$c){
    if (isLikert($c)) return [0,$n*$c];
    if ($c===0)      return [0,$n*100];
    return [0,$n];
}
function calcSum($arr,$c){
    $s=0;
    foreach($arr as $i){
        $v=intval($i['result']);
        if (isLikert($c)) $s += $i['negated'] ? ($c-$v) : $v;
        elseif ($c===0)   $s += $i['negated'] ? (100-$v) : $v;
        else              $s += $i['negated'] ? ($v===1?0:1) : $v;
    }
    return $s;
}
function barClass($p){ return $p<.33?'bg-danger':($p<.66?'bg-warning':'bg-success'); }
function itemMax($c){ return isLikert($c)?$c:($c===0?100:1); }
// Automatische Interpretation
function interpret($value, $min, $max) {
    $ratio = ($max-$min)>0 ? ($value-$min)/($max-$min) : 1;
    if ($ratio >= 0.8) {
        return "Dein Wert liegt sehr hoch im Bereich der Skala.";
    } elseif ($ratio >= 0.6) {
        return "Dein Wert liegt über dem Durchschnitt.";
    } elseif ($ratio >= 0.4) {
        return "Dein Wert liegt im Mittelfeld.";
    } elseif ($ratio >= 0.2) {
        return "Dein Wert liegt unter dem Durchschnitt.";
    } else {
        return "Dein Wert liegt sehr niedrig im Bereich der Skala.";
    }
}

// Teilnehmer
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM results WHERE questionnaire_id = ?");
$stmt->execute([$qid]);
$participants = intval($stmt->fetchColumn());

// Share
$shareUrl = (isset($_SERVER['HTTPS'])?'https':'http')
          .'://'.$_SERVER['HTTP_HOST']
          .dirname($_SERVER['REQUEST_URI'])
          ."/q.php?id={$qid}";
$totalSum = calcSum($R,$ct);
$avg = number_format($totalSum/count($R),1,',','');
$display = "{$avg} von ".itemMax($ct);
$shareText = rawurlencode(
  "🎉 Mein Ergebnis bei „{$Q['name']}“: {$display}! ".$shareUrl
);

?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ergebnis | <?=htmlspecialchars($Q['name'])?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f2f4f8; }
    .hero { background:#fff; padding:1.5rem; border-radius:.5rem; box-shadow:0 4px 20px rgba(0,0,0,0.05); }
    .subcard { margin-bottom:1.5rem; }
    .subheader { font-weight:600; }
    .subdesc { color:#5a5a5a; font-size:.95rem; margin-bottom:.5rem; }
    .interpret { font-style:italic; color:#333; margin-top:.5rem; }
    .normcard { margin-bottom:2rem; }
    .sharecard { text-align:center; margin-bottom:2rem; }
  </style>
</head>
<body>
<div class="container py-5" style="max-width:800px;">

  <!-- Hero -->
  <div class="hero mb-5 text-center">
    <h2><?=htmlspecialchars($Q['name'])?></h2>
    <?php if(!empty($ops['global'])): ?>
      <p class="text-muted"><?=nl2br(htmlspecialchars($ops['global']))?></p>
    <?php endif; ?>
  </div>

  <!-- Subskalen -->
  <?php foreach($byScale as $scale=>$arr):
    $n=count($arr);
    list($mn,$mx)=minMax($n,$ct);
    $sum=calcSum($arr,$ct);
    $pct=($mx>$mn)?($sum-$mn)/($mx-$mn):1;
    $cls=barClass($pct);
    $label=($scale==='_Gesamt'?'Gesamtergebnis':htmlspecialchars($scale));
    $disp=isLikert($ct)||$ct===0
          ?number_format($sum/$n,1,',','')." / ".itemMax($ct)
          : "{$sum} / {$mx}";
    $subdesc = $ops['subscales'][$scale] ?? '';
  ?>
    <div class="card subcard p-3">
      <div class="subheader"><?=$label?></div>
      <?php if($subdesc): ?>
        <div class="subdesc"><?=nl2br(htmlspecialchars($subdesc))?></div>
      <?php endif; ?>
      <div class="progress mb-2">
        <div class="progress-bar <?=$cls?>" style="width:<?=round($pct*100)?>%">
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
      <div class="interpret"><?=interpret($sum,$mn,$mx)?></div>
    </div>
  <?php endforeach; ?>

  <!-- Normwert -->
  <div class="card normcard p-4">
    <h5>Normwert‑Statistik</h5>
    <p>Teilnahmen bisher: <strong><?=$participants?></strong></p>
    <?php if($participants < NORM_THRESHOLD): ?>
      <p>Noch <strong><?=NORM_THRESHOLD-$participants?></strong> für aussagekräftige Normwerte.</p>
    <?php else: ?>
      <p class="text-success">Genügend für Normwerte (≥<?=NORM_THRESHOLD?>).</p>
    <?php endif; ?>
    <?php if($participants < CRONBACH_THRESHOLD): ?>
      <p>Noch <strong><?=CRONBACH_THRESHOLD-$participants?></strong> für Cronbach’s Alpha.</p>
    <?php else: ?>
      <p class="mb-0"><strong>Cronbach’s Alpha</strong> wird berechnet.</p>
    <?php endif; ?>
  </div>

  <!-- Share -->
  <div class="card sharecard p-4">
    <h5>Teile dein Ergebnis</h5>
    <p>Fordere Freunde heraus & verbesser die Normwerte!</p>
    <a href="mailto:?subject=Mein Ergebnis&body=<?=$shareText?>" class="btn btn-primary me-2">E‑Mail</a>
    <a href="https://api.whatsapp.com/send?text=<?=$shareText?>" target="_blank"
       class="btn btn-success">WhatsApp</a>
  </div>

  <div class="alert alert-info text-center">
    Ausführliche Interpretation folgt, sobald genügend Daten vorliegen.<br>
    Alle Angaben bleiben anonym.
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include('footer.inc.php'); ?>
