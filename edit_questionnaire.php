<?php
ob_start();
require_once 'include.inc.php';
$pageTitle       = 'Fragebogen bearbeiten';
$pageDescription = 'Erstelle oder bearbeite deinen Fragebogen';

// --- Login-Schutz für Bearbeitung ---
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
if ($qid !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
        $pw = trim($_POST['access_password']);
        if (verify_admin($pw)) {
            authorize($qid, true);
        } elseif (verify_author($pdo, $qid, $pw)) {
            authorize($qid);
        } else {
            $loginError = "Ungültiges Passwort.";
        }
    }
    if (!is_authorized($qid)) {
        require_once 'navbar.inc.php';
        ?>
        <div class="container py-5">
          <div class="card mx-auto sparkle-card" style="max-width:400px;"><!-- ✨ -->
            <div class="card-body">
              <h5 class="card-title">Nur für Autoren/Admins</h5>
              <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
              <?php endif; ?>
              <form method="post" autocomplete="off">
                <div class="mb-3">
                  <label class="form-label">Passwort</label>
                  <input type="password" name="access_password" class="form-control shimmer-input" required minlength="8"><!-- ✨ -->
                  <div class="form-text">Mindestens 8 Zeichen.</div>
                </div>
                <button class="btn btn-primary w-100 btn-wave">Anmelden</button><!-- ✨ -->
              </form>
            </div>
          </div>
        </div>
        </body></html>
        <?php
        exit;
    }
}

// --- Sprachoptionen ---
$langs = [ 'DE'=>'Deutsch','EN'=>'Englisch','FR'=>'Französisch','IT'=>'Italienisch',
          'ES'=>'Spanisch','TR'=>'Türkisch','RU'=>'Russisch','AR'=>'Arabisch',
          'ZH'=>'Chinesisch','PT'=>'Portugiesisch','PL'=>'Polnisch','NL'=>'Niederländisch' ];
// --- ChoiceTypes ---
$choice_types = [
    0=>"Intervallskala (Schieberegler 0–100)",
    1=>"Dual: Wahr / Falsch",
    2=>"Dual: Stimme voll zu / Stimme nicht zu",
    3=>"3‑stufige Likert‑Skala",
    4=>"4‑stufige Likert‑Skala",
    5=>"5‑stufige Likert‑Skala",
    6=>"6‑stufige Likert‑Skala",
    7=>"7‑stufige Likert‑Skala"
];

// Initialisierung
$editing       = false;
$questionnaire = null;
$items         = [];
$has_results   = false;
$feedback      = null;
$operational   = ['global'=>'','subscales'=>[]];

// Laden für Bearbeitung
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing     = true;
        // decode existing operationalization
        $operational = json_decode($questionnaire['operationalization'] ?? '{}', true)
                     ?: ['global'=>'','subscales'=>[]];
        // load items
        $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
        $stmt->execute([$qid]);
        $items = $stmt->fetchAll();
        // check results
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE questionnaire_id = ?");
        $stmt->execute([$qid]);
        $has_results = $stmt->fetchColumn() > 0;
    }
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $errors = [];

    // Meta-Felder
    $name        = trim($_POST['name'] ?? '');
    $short       = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language    = strtoupper(trim($_POST['language'] ?? ''));
    $choice_type = isset($_POST['choice_type']) ? intval($_POST['choice_type']) : null;
    $copyright   = isset($_POST['copyright']);
    $jsonOp      = $_POST['operationalization'] ?? '';

    if (!$name)        $errors[] = "Bitte einen Namen angeben.";
    if (!$description) $errors[] = "Bitte eine Beschreibung eingeben.";
    if (!isset($langs[$language]))           $errors[] = "Bitte eine gültige Sprache wählen.";
    if (!isset($choice_types[$choice_type])) $errors[] = "Bitte einen gültigen Skalentyp wählen.";
    if (!$copyright)   $errors[] = "Bitte das Copyright bestätigen.";

    // validate operational JSON
    $opData = @json_decode($jsonOp, true);
    if (!is_array($opData) || !isset($opData['global'], $opData['subscales']) || !is_array($opData['subscales'])) {
        $errors[] = "Operationalisierung fehlerhaft.";
    }

    // Autor-Passwort
    $author_hash = null;
    if (isset($_POST['author_password']) && $_POST['author_password'] !== '') {
        $plain = trim($_POST['author_password']);
        if (strlen($plain) < 8) {
            $errors[] = "Autor‑Passwort muss mindestens 8 Zeichen lang sein.";
        } else {
            $author_hash = password_hash($plain, PASSWORD_DEFAULT);
        }
    } elseif ($editing && $has_results && empty($_SESSION['is_admin'])) {
        $author_hash = $questionnaire['author_password_hash'];
    }

    // Items
    $texts      = $_POST['item']  ?? [];
    $scales     = $_POST['scale'] ?? [];
    $negIndices = isset($_POST['negated'])
                  ? array_map('intval', $_POST['negated'])
                  : [];

    $clean_items = [];
    foreach ($texts as $i => $text) {
        $t = trim($text);
        if ($t === '') continue;
        $s = trim($scales[$i] ?? '');
        $n = in_array($i, $negIndices, true) ? 1 : 0;
        $clean_items[] = ['text'=>$t,'scale'=>$s,'negated'=>$n];
    }
    if (empty($clean_items)) {
        $errors[] = "Mindestens ein Item muss eingetragen werden.";
    }
    // Duplikate prüfen
    $lower = array_map(fn($it)=>mb_strtolower($it['text']), $clean_items);
    if (count($lower) !== count(array_unique($lower))) {
        $errors[] = "Es sind doppelte Items vorhanden.";
    }

    // Speichern
    if (empty($errors)) {
        if ($editing) {
            // Update questionnaires
            $updSQL = $has_results
                ? "UPDATE questionnaires SET name=?, short=?, language=?, description=?, operationalization=?, author_password_hash=?, updated_at=NOW() WHERE id=?"
                : "UPDATE questionnaires SET name=?, short=?, language=?, choice_type=?, description=?, operationalization=?, author_password_hash=?, updated_at=NOW() WHERE id=?";
            $params = $has_results
                ? [$name,$short,$language,$description,$jsonOp,$author_hash,$qid]
                : [$name,$short,$language,$choice_type,$description,$jsonOp,$author_hash,$qid];
            $pdo->prepare($updSQL)->execute($params);

            if (!$has_results) {
                // replace items
                $pdo->prepare("DELETE FROM items WHERE questionnaire_id=?")->execute([$qid]);
                $ins = $pdo->prepare("
                    INSERT INTO items (questionnaire_id,item,negated,scale,created_at,updated_at)
                    VALUES (?,?,?,?,NOW(),NOW())
                ");
                foreach ($clean_items as $it) {
                    $ins->execute([$qid,$it['text'],$it['negated'],$it['scale']]);
                }
            }

            $feedback = ['type'=>'success','msg'=> $has_results
                ? "✨ Metadaten und Operationalisierung aktualisiert!"  // ✨
                : "🎉 Fragebogen, Items und Operationalisierung aktualisiert!" // ✨
            ];
        } else {
            // Insert new questionnaire
            $pdo->prepare("
                INSERT INTO questionnaires
                  (name,short,language,choice_type,author_password_hash,description,operationalization,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,NOW(),NOW())
            ")->execute([$name,$short,$language,$choice_type,$author_hash,$description,$jsonOp]);
            $new_qid = $pdo->lastInsertId();
            $ins2 = $pdo->prepare("
                INSERT INTO items
                  (questionnaire_id,item,negated,scale,created_at,updated_at)
                VALUES (?,?,?,?,NOW(),NOW())
            ");
            foreach ($clean_items as $it) {
                $ins2->execute([$new_qid,$it['text'],$it['negated'],$it['scale']]);
            }
            $feedback = ['type'=>'success','msg'=>"🎉 Fragebogen erstellt!"];// ✨
            $editing = false;
            $items = [];
        }
        // reload items
        if ($editing) {
            $stmtReload = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
            $stmtReload->execute([$qid]);
            $items = $stmtReload->fetchAll();
        }
    } else {
        $feedback = ['type'=>'danger','msg'=> implode('<br>',$errors)];
    }
}

// ensure blank row
if (empty($items)) {
    $items = [['text'=>'','scale'=>'','negated'=>0]];
}

require_once 'navbar.inc.php';
?>

<style>
/* ==== Tiny‑Sparkle CSS ==== */
/* shimmer on inputs */
.shimmer-input:focus {
  background: linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,0.4) 50%,rgba(255,255,255,0)100%);
  background-size:200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer {
  0% { background-position:200% 0; }
  100% { background-position:-200% 0; }
}
/* wave‑hover buttons */
.btn-wave:hover {
  position: relative;
  overflow: hidden;
}
.btn-wave:hover::after {
  content:'';
  position:absolute; width:200%; height:200%; background:rgba(255,255,255,0.2);
  top:-50%; left:-50%; transform:rotate(45deg) scale(0);
  animation: wave 0.8s ease-out forwards;
}
@keyframes wave {
  to { transform:rotate(45deg) scale(1); opacity:0; }
}
/* sparkle cards */
.sparkle-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(6px);
  position: relative;
}
.sparkle-card::before {
  content:'✨'; position:absolute; top:8px; right:12px; font-size:1.2em; opacity:0;
  animation: sparkle 2s infinite;
}
@keyframes sparkle {
  0%,100%{opacity:0;}
  50%{opacity:1;}
}
/* callout doodle divider */
.callout { border-left:3px dashed #FF8A65; padding-left:1em; font-style:italic; }
/* doodle separator */
.card + .card {
  margin-top:2rem;
  position:relative;
}
.card + .card::before {
  content:'✎ ✿ ✎'; position:absolute; top:-16px; left:50%; transform:translateX(-50%);
  font-size:1.1em; color:#50C9CE;
}

</style>

<div class="container py-4" style="max-width:900px;">
  <h2 class="mb-3"><?= $editing ? "Fragebogen bearbeiten" : "Neuen Fragebogen erstellen" ?></h2>
  <?php if (!empty($feedback)): ?>
    <div class="alert alert-<?= $feedback['type'] ?> sparkle-card"><?= $feedback['msg'] ?></div><!-- ✨ -->
  <?php endif; ?>

  <form method="post" id="questionnaireForm" autocomplete="off">
    <!-- Metadaten Card -->
    <div class="card mb-4"><div class="card-body">
      <!-- Name & Short -->
      <div class="row mb-3">
        <div class="col-md-7 mb-3 mb-md-0">
          <label class="form-label">Name *</label>
          <input name="name" class="form-control shimmer-input" required maxlength="255"
                 value="<?= htmlspecialchars($questionnaire['name'] ?? '') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Kürzel (optional)</label>
          <input name="short" class="form-control shimmer-input" maxlength="50"
                 value="<?= htmlspecialchars($questionnaire['short'] ?? '') ?>">
        </div>
      </div>
      <!-- Sprache & Skalentyp -->
      <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label">Sprache *</label>
          <select name="language" class="form-select shimmer-input" required <?= $has_results?'disabled':''?>>
            <option value="">Bitte wählen</option>
            <?php foreach($langs as $code=>$lang): ?>
            <option value="<?=$code?>" <?=(($questionnaire['language']??'')===$code)?'selected':''?>>
              <?=$lang?> (<?=$code?>)
            </option>
            <?php endforeach; ?>
          </select>
          <?php if($has_results):?>
            <input type="hidden" name="language" value="<?=htmlspecialchars($questionnaire['language'])?>">
          <?php endif;?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skalentyp *</label>
          <select name="choice_type" class="form-select shimmer-input" required <?= $has_results?'disabled':''?>>
            <option value="">Bitte wählen</option>
            <?php foreach($choice_types as $val=>$txt): ?>
            <option value="<?=$val?>" <?=(($questionnaire['choice_type']??'')==$val)?'selected':''?>>
              <?=$txt?>
            </option>
            <?php endforeach;?>
          </select>
          <?php if($has_results):?>
            <input type="hidden" name="choice_type" value="<?=intval($questionnaire['choice_type'])?>">
          <?php endif;?>
        </div>
      </div>
      <!-- Beschreibung -->
      <div class="mb-3">
        <label class="form-label">Beschreibung *</label>
        <textarea name="description" class="form-control shimmer-input" required rows="2"><?=htmlspecialchars($questionnaire['description']??'')?></textarea>
      </div>
      <!-- Autor-Passwort -->
      <?php if (!$has_results||!empty($_SESSION['is_admin'])): ?>
      <div class="mb-3">
        <label class="form-label"><?= $editing?"Autor-Passwort ändern":"Autor-Passwort" ?> <small>(mind. 8 Zeichen)</small></label>
        <input type="password" name="author_password" class="form-control shimmer-input" <?= $editing?'':'required' ?> minlength="8">
        <?php if($editing && $questionnaire['author_password_hash']): ?>
          <div class="form-text">Leer lassen, um nicht zu ändern.</div>
        <?php endif;?>
      </div>
      <?php endif;?>
      <!-- Copyright -->
      <div class="form-check mb-3">
        <input name="copyright" class="form-check-input" type="checkbox" required>
        <label class="form-check-label">Ich bestätige, dass ich Eigentümer bin.</label>
      </div>
    </div></div>

    <!-- Items Card -->
    <div class="card mb-4"><div class="card-body">
      <label class="form-label mb-2">Items *</label>
      <?php if($has_results):?>
        <div class="alert alert-info sparkle-card">Items gesperrt (Ergebnisse vorhanden).</div>
      <?php endif;?>
      <div id="itemsList">
        <?php foreach($items as $i=>$it): ?>
        <div class="d-flex align-items-center mb-2 item-row">
          <input name="item[]" class="form-control shimmer-input me-2" placeholder="Text *" required
                 value="<?=htmlspecialchars($it['item'])?>" <?= $has_results?'readonly':''?>>
          <input name="scale[]" class="form-control shimmer-input me-2" placeholder="Subskala (optional)"
                 value="<?=htmlspecialchars($it['scale'])?>" <?= $has_results?'readonly':''?>>
          <input type="checkbox" name="negated[]" value="<?=$i?>"
                 class="form-check-input me-2 sparkle-card" <?=($it['negated']?'checked':'')?> <?= $has_results?'disabled':''?>>
          <?php if(!$has_results):?>
            <button type="button" class="btn btn-link text-danger btn-remove-item px-2 btn-wave">&times;</button><!-- ✨ -->
          <?php endif;?>
        </div>
        <?php endforeach;?>
      </div>
      <?php if(!$has_results):?>
        <button type="button" class="btn btn-outline-secondary btn-sm btn-wave" id="btnAddItem">Weiteres Item hinzufügen</button>
      <?php endif;?>
      <div class="mt-3 text-muted small" id="itemStats"></div>
    </div></div>

    <!-- Operationalisierung Card -->
    <div class="card mb-4"><div class="card-body">
      <h5 class="form-label">Operationalisierung</h5>
      <div class="mb-3">
        <label for="opGlobal" class="form-label">Allgemeine Skalen‑Beschreibung</label>
        <textarea id="opGlobal" class="form-control shimmer-input" rows="2" placeholder="z. B. ‚Diese Skala misst …‘"><?=htmlspecialchars($operational['global'])?></textarea>
      </div>
      <div id="subscaleOps" class="mb-3"></div>
      <input type="hidden" name="operationalization" id="operationalization">
    </div></div>

    <!-- Buttons -->
    <div class="d-flex gap-2 mb-5">
      <button type="submit" class="btn btn-success btn-wave">Speichern</button>
      <button type="button" class="btn btn-outline-danger btn-wave" id="btnReset">Zurücksetzen</button><!-- ✨ -->
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ==== Sparkle‑JS & Interactivity ====

document.addEventListener('DOMContentLoaded', () => {
  // Elemente
  const list       = document.getElementById('itemsList');
  const addBtn     = document.getElementById('btnAddItem');
  const stats      = document.getElementById('itemStats');
  const opGlobal   = document.getElementById('opGlobal');
  const subscaleOps= document.getElementById('subscaleOps');
  const opHidden   = document.getElementById('operationalization');
  const resetBtn   = document.getElementById('btnReset');
  const form       = document.getElementById('questionnaireForm');
  const existing   = <?= json_encode($operational) ?>;

  // === Stats / Subscale Fields ===
  function updateItemStats() {
    const rows = list.querySelectorAll('.item-row');
    let count=0, scales=new Set();
    rows.forEach((r,i)=>{
      const t=r.querySelector('input[name="item[]"]').value.trim();
      if(t) count++;
      const s=r.querySelector('input[name="scale[]"]').value.trim();
      if(s) scales.add(s);
      r.querySelector('input[type="checkbox"]').value=i;
    });
    let info = `${count} Item${count!==1?'s':''}`;
    if(scales.size) info+= ` | ${scales.size} Subskala${scales.size!==1?'en':''}: ${[...scales].join(', ')}`;
    stats.innerText=info;
  }
  function getSubscales() {
    const set=new Set();
    list.querySelectorAll('input[name="scale[]"]').forEach(i=>{
      const v=i.value.trim();
      if(v) set.add(v);
    });
    return [...set];
  }
  function renderSubscaleFields(){
    subscaleOps.innerHTML='';
    getSubscales().forEach(name=>{
      const d=document.createElement('div'); d.className='mb-3';
      const l=document.createElement('label'); l.className='form-label'; l.textContent=name+' – Beschreibung';
      const ta=document.createElement('textarea'); ta.className='form-control shimmer-input'; ta.rows=1;
      ta.dataset.subscale=name; ta.placeholder=`z. B. ‚${name} erfasst …‘`;
      if(existing.subscales[name]) ta.value=existing.subscales[name];
      d.append(l,ta); subscaleOps.append(d);
    });
  }

  // === Form Submit: pack JSON ===
  form.addEventListener('submit',()=>{
    const data={ global:opGlobal.value.trim(), subscales:{} };
    subscaleOps.querySelectorAll('textarea').forEach(ta=>{
      if(ta.value.trim()) data.subscales[ta.dataset.subscale]=ta.value.trim();
    });
    opHidden.value=JSON.stringify(data);
    // 🎉 Konfetti‑Burst
    confetti({ particleCount: 50, spread: 60 }); // requires canvas-confetti.js loaded separately
  });

  // === Reset Form ===
  resetBtn.addEventListener('click',()=>{
    form.reset();
    updateItemStats();
    renderSubscaleFields();
    // Zauberstab‑Partikel
    sparkleBurst(list);
  });

  // === Item Logic ===
  if(addBtn) addBtn.addEventListener('click',()=>{
    const div=document.createElement('div'); div.className='d-flex align-items-center mb-2 item-row';
    div.innerHTML=`
      <input name="item[]" class="form-control shimmer-input me-2" placeholder="Text *" required>
      <input name="scale[]" class="form-control shimmer-input me-2" placeholder="Subskala (optional)">
      <input type="checkbox" name="negated[]" class="form-check-input me-2" value="0">
      <button type="button" class="btn btn-link text-danger btn-remove-item px-2 btn-wave">&times;</button>`;
    list.append(div); updateItemStats(); renderSubscaleFields();
    sparkleOnce(div);
  });
  list.addEventListener('click',e=>{
    if(e.target.classList.contains('btn-remove-item')){
      const row=e.target.closest('.item-row'); row.remove();
      updateItemStats(); renderSubscaleFields();
      sparkleOnce(row);
    }
  });
  list.addEventListener('input',e=>{
    if(e.target.matches('input[name="item[]"],input[name="scale[]"]')){
      const rows=list.querySelectorAll('.item-row');
      const last=rows[rows.length-1];
      if(e.target.matches('input[name="item[]"]') && last===e.target.closest('.item-row') && e.target.value.trim()){
        addBtn.click();
      }
      updateItemStats(); renderSubscaleFields();
    }
  });

  // Initial
  updateItemStats();
  renderSubscaleFields();
});

/* ==== Sparkle Helper Functions ==== */
function sparkleOnce(el){
  const sparkle = document.createElement('span');
  sparkle.textContent='✨'; sparkle.className='sparkle-temp';
  sparkle.style.position='absolute';
  sparkle.style.left='10px'; sparkle.style.top='-10px';
  el.style.position='relative'; el.append(sparkle);
  sparkle.animate([{opacity:1, transform:'scale(0.5)'},{opacity:0,transform:'scale(1.5)'}],{duration:600}).onfinish=()=>sparkle.remove();
}
function sparkleBurst(target){
  for(let i=0;i<15;i++) sparkleOnce(target);
}
</script>

<?php
include('footer.inc.php');
ob_end_flush();
?>
