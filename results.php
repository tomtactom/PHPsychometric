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
if (!$qid) { http_response_code(400); die('Ungültige Anfrage.'); }

// Fragebogen & choice_type laden
$stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
$stmt->execute([$qid]);
$fragebogen = $stmt->fetch();
if (!$fragebogen) { http_response_code(404); die('Fragebogen nicht gefunden.'); }

$user_id = getUserIdFromCookie();
if (!$user_id) {
    header("Location: q.php?id={$qid}");
    exit;
}

// Item-IDs laden
$stmt = $pdo->prepare("SELECT id FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
$stmt->execute([$qid]);
$item_ids = array_column($stmt->fetchAll(), 'id');
if (empty($item_ids)) die('Keine Items definiert.');

// Alle Antworten laden
$stmt = $pdo->prepare("
  SELECT r.*, i.negated, i.scale, i.item
    FROM results r
    JOIN items i ON r.item_id = i.id
   WHERE r.user_id=? AND r.questionnaire_id=?
ORDER BY r.created_at, r.id
");
$stmt->execute([$user_id, $qid]);
$all_responses = $stmt->fetchAll();
if (empty($all_responses)) {
    header("Location: q.php?id={$qid}");
    exit;
}

// Durchgänge rekonstruieren
$runs=[]; $curr=[]; $ids=[];
foreach ($all_responses as $r) {
    $curr[]=$r; $ids[]=$r['item_id'];
    if (count($curr)===count($item_ids) && count(array_unique($ids))===count($item_ids)) {
        $runs[]=$curr; $curr=[]; $ids=[];
    }
}
$responses = end($runs)?:[];
if (!$responses) {
    $slice=array_slice($all_responses,-count($item_ids));
    $sids=array_column($slice,'item_id');
    if (count($slice)===count($item_ids)&& count(array_unique($sids))===count($item_ids)) {
        $responses=$slice;
    }
}
if (!$responses) { header("Location: q.php?id={$qid}"); exit; }

// Skalen gruppieren
$skala_map=[];
foreach($responses as $r){
    $scale=trim($r['scale']?:'_gesamt');
    $skala_map[$scale][]=$r;
}
if (!isset($skala_map['_gesamt'])) $skala_map['_gesamt']=$responses;

// Helper
$ct = intval($fragebogen['choice_type']);
function isLikert($c){return in_array($c,[3,4,5,6,7],true);}
function minMax($n,$c){ return [0, isLikert($c)?$n*$c:($c===0?100*$n:$n)];}
function calcSum($items,$c){
  $sum=0;
  foreach($items as $it){
    $v=intval($it['result']);
    if(isLikert($c))      $sum+= $it['negated']?($c-$v):$v;
    elseif($c===0)        $sum+= $it['negated']?(100-$v):$v;
    else                  $sum+= $it['negated']?($v===1?0:1):$v;
  }
  return $sum;
}
function barClass($p){ return $p<.33?'bg-danger':($p<.66?'bg-warning':'bg-success');}
function itemMax($c){ return isLikert($c)?$c:($c===0?100:1); }

// Anzahl bisheriger Teilnehmer:innen
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) AS cnt FROM results WHERE questionnaire_id = ?");
$stmt->execute([$qid]);
$participants = intval($stmt->fetchColumn());

// Schwellenwerte
define('NORM_THRESHOLD', 30);
define('CRONBACH_THRESHOLD', 30);

// Share-URL und Text
$currentUrl = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$shareText = rawurlencode("Ich habe bei \"{$fragebogen['name']}\" ein Ergebnis von ".
    round(calcSum($skala_map['_gesamt'],$ct)/count($skala_map['_gesamt']),2).
    " erzielt – mach mit: {$currentUrl}");
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ergebnis | <?=htmlspecialchars($fragebogen['name'])?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f8fafc} .card{box-shadow:0 4px 24px rgba(0,0,0,0.05)} .progress{height:1.7rem}
    .scale-head{font-size:1.1em;font-weight:600} .subtext{font-size:0.95em;color:#7d7d7d}
    .skala-block{margin-bottom:2.5rem}
    .share-btn{margin-right:.5rem;}
  </style>
</head><body>
<div class="container py-4" style="max-width:820px;">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Dein Ergebnis</h3>
    <a href="index.php" class="btn btn-outline-primary btn-sm">Zur Übersicht</a>
  </div>
  <div class="card mb-4"><div class="card-body">
    <h5><?=htmlspecialchars($fragebogen['name'])?></h5>
    <p class="text-muted"><?=nl2br(htmlspecialchars($fragebogen['description']))?></p>
  </div></div>

  <?php foreach($skala_map as $scale=>$items):
    $n = count($items);
    list($min,$max) = minMax($n,$ct);
    $sum = calcSum($items,$ct);
    $pct= $max>$min?($sum-$min)/($max-$min):1;
    $cls=barClass($pct);
    $label = $scale===' _gesamt'?'Gesamtergebnis':htmlspecialchars($scale);
    $disp = isLikert($ct)||$ct===0
            ? round($sum/$n,2).' / '.itemMax($ct)
            : $sum.' / '.$max;
  ?>
    <div class="skala-block">
      <div class="scale-head mb-2"><?=$label?></div>
      <div class="progress mb-2" title="<?=round($pct*100)?>%">
        <div class="progress-bar <?=$cls?>" role="progressbar"
             style="width:<?=round($pct*100)?>%;"
             aria-valuenow="<?=$sum?>" aria-valuemin="<?=$min?>" aria-valuemax="<?=$max?>">
          <?=htmlspecialchars($disp)?>
        </div>
      </div>
      <div class="subtext">
        <?php if(isLikert($ct)||$ct===0): ?>
          Mittelwert aus <?=$n?> Item<?=$n>1?'s':''?> (0 minimal, <?=itemMax($ct)?> maximal)
        <?php else: ?>
          Summenwert aus <?=$n?> Item<?=$n>1?'s':''?> (0 minimal, <?=$max?> maximal)
        <?php endif;?>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Statistik & Cronbach -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Normwert-Statistik</h5>
      <p>Bisherige Teilnahmen: <strong><?=$participants?></strong></p>
      <?php if($participants< NORM_THRESHOLD): ?>
        <p>Noch <strong><?= NORM_THRESHOLD - $participants ?></strong> Teilnahmen bis zu aussagekräftigen Normwerten (mindestens <?=NORM_THRESHOLD?> gesamt).</p>
      <?php else: ?>
        <p>Ausreichend Teilnahmen für Normwerte (≥<?=NORM_THRESHOLD?>).</p>
      <?php endif; ?>
      <?php if($participants< CRONBACH_THRESHOLD): ?>
        <p>Noch <strong><?= CRONBACH_THRESHOLD - $participants ?></strong> Teilnahmen bis zur Berechnung von Cronbach’s Alpha (mindestens <?=CRONBACH_THRESHOLD?>).</p>
      <?php else: ?>
        <?php
        // Platzhalter für Cronbach’s Alpha – hier könnte man berechnen
        $alpha = '–';
        ?>
        <p><strong>Cronbach’s Alpha:</strong> <?=$alpha?> (wird ab <?=CRONBACH_THRESHOLD?> Teilnahmen berechnet)</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Share-Links -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Teile dein Ergebnis</h5>
      <a class="btn btn-outline-primary share-btn"
         href="mailto:?subject=Mein Ergebnis bei <?=rawurlencode($fragebogen['name'])?>&body=<?=$shareText?>">
        E-Mail
      </a>
      <a class="btn btn-outline-success share-btn"
         href="https://api.whatsapp.com/send?text=<?=$shareText?>" target="_blank">
        WhatsApp
      </a>
      <!-- weitere Kanäle nach Bedarf -->
    </div>
  </div>

  <div class="alert alert-info">
    Normwert-Interpretation folgt, sobald ausreichend Daten vorliegen.<br>
    Angaben bleiben anonymisiert.
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
