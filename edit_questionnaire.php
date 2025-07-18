<?php
ob_start();
require_once 'include.inc.php';
$pageTitle       = $qid ? 'Fragebogen bearbeiten' : 'Neuen Fragebogen erstellen';
$pageDescription = 'Erstelle oder bearbeite deinen Fragebogen';

// --- Login-Schutz für Bearbeitung ---
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
if ($qid !== null) {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['access_password'])) {
        $pw = trim($_POST['access_password']);
        if (verify_admin($pw))        authorize($qid, true);
        elseif (verify_author($pdo,$qid,$pw)) authorize($qid);
        else                          $loginError="Ungültiges Passwort.";
    }
    if (!is_authorized($qid)) {
        require 'navbar.inc.php'; ?>
        <div class="container py-5">
          <div class="card mx-auto" style="max-width:400px">
            <div class="card-body">
              <h5 class="card-title">Nur für Autoren/Admins</h5>
              <?= !empty($loginError) ? "<div class='alert alert-danger'>{$loginError}</div>" : '' ?>
              <form method="post" autocomplete="off">
                <div class="mb-3">
                  <label class="form-label">Passwort</label>
                  <input type="password" name="access_password" class="form-control" required minlength="8">
                  <div class="form-text">Mindestens 8 Zeichen.</div>
                </div>
                <button class="btn btn-primary w-100">Anmelden</button>
              </form>
            </div>
          </div>
        </div>
        </body></html><?php
        exit;
    }
}

// Sprach- & Skalentyp‑Optionen
$langs = ['DE'=>'Deutsch','EN'=>'Englisch','FR'=>'Französisch','IT'=>'Italienisch',
         'ES'=>'Spanisch','TR'=>'Türkisch','RU'=>'Russisch','AR'=>'Arabisch',
         'ZH'=>'Chinesisch','PT'=>'Portugiesisch','PL'=>'Polnisch','NL'=>'Niederländisch'];
$choice_types = [
    0=>"Intervallskala (0–100)",1=>"Dual: Wahr/Falsch",2=>"Dual: Stimme voll zu/nicht zu",
    3=>"3‑stufige Likert",4=>"4‑stufige Likert",5=>"5‑stufige Likert",
    6=>"6‑stufige Likert",7=>"7‑stufige Likert"
];

// Initialisierung
$editing     = false;
$questionnaire = null;
$items       = [];
$has_results = false;
$feedback    = [];
$operational = ['global'=>'','subscales'=>[]];

// Laden
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id=?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing     = true;
        $has_results = $pdo->prepare("SELECT COUNT(*) FROM results WHERE questionnaire_id=?")
                            ->execute([$qid]) && $pdo->query("SELECT COUNT(*)")->fetchColumn()>0;
        // Operationalisierung aus JSON
        $operational = json_decode($questionnaire['operationalization'] ?? '{}', true)
                     ?: ['global'=>'','subscales'=>[]];
        // Items
        $items = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id=? ORDER BY id")
                     ->execute([$qid]) && $pdo->query("SELECT *")->fetchAll();
    }
}

// Verarbeiten
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['name'])) {
    $errors = [];
    // Meta
    $name        = trim($_POST['name'] ?? '');
    $short       = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language    = strtoupper($_POST['language'] ?? '');
    $choice_type = isset($_POST['choice_type']) ? intval($_POST['choice_type']) : null;
    $copyright   = isset($_POST['copyright']);
    $jsonOp      = $_POST['operationalization'] ?? '';

    $errors = array_merge($errors,
        !$name        ? ["Name fehlt."] :
        !$description ? ["Beschreibung fehlt."] : [],
        !isset($langs[$language])        ? ["Sprache ungültig."] : [],
        !isset($choice_types[$choice_type]) ? ["Skalentyp ungültig."] : [],
        !$copyright   ? ["Copyright bestätigen."] : []
    );
    // Operationalisierung validieren
    $opData = json_decode($jsonOp, true);
    if (!is_array($opData) || !isset($opData['global'], $opData['subscales'])) {
        $errors[] = "Operationalisierung fehlerhaft.";
    }
    // Passwort
    $author_hash = null;
    if (!empty($_POST['author_password'])) {
        $plain = trim($_POST['author_password']);
        if (strlen($plain)<8) $errors[]="Autor-Passwort min. 8 Zeichen.";
        else                  $author_hash = password_hash($plain,PASSWORD_DEFAULT);
    } elseif ($editing && $has_results) {
        $author_hash = $questionnaire['author_password_hash'];
    }
    // Items
    $texts      = $_POST['item']  ?? [];
    $scalesArr  = $_POST['scale'] ?? [];
    $negs       = $_POST['negated'] ?? [];
    $clean_items=[];
    foreach ($texts as $i=>$txt) {
        $t=trim($txt); if($t==='') continue;
        $s=trim($scalesArr[$i]??'');
        $n = in_array($i,$negs,true) ? 1 : 0;
        $clean_items[]=['text'=>$t,'scale'=>$s,'negated'=>$n];
    }
    if (empty($clean_items)) $errors[]="Mindestens ein Item.";
    // Duplikate
    $low=array_map(fn($it)=>mb_strtolower($it['text']),$clean_items);
    if (count($low)!==count(array_unique($low))) $errors[]="Duplikate in Items.";

    if (empty($errors)) {
        // Insert/Update
        if ($editing) {
            $fields = $has_results
              ? "name,short,language,description,operationalization,author_password_hash,updated_at"
              : "name,short,language,choice_type,description,operationalization,author_password_hash,updated_at";
            $ph = $has_results
              ? "?,?,?,?,?,?,NOW()"
              : "?,?,?,?,?,?,?,NOW()";
            $params = $has_results
              ? [$name,$short,$language,$description,$jsonOp,$author_hash,$qid]
              : [$name,$short,$language,$choice_type,$description,$jsonOp,$author_hash,$qid];
            $pdo->prepare("UPDATE questionnaires SET $fields=? WHERE id=?")
                 ->execute($params);
            if (!$has_results) {
                $pdo->prepare("DELETE FROM items WHERE questionnaire_id=?")->execute([$qid]);
                $ins = $pdo->prepare("INSERT INTO items(questionnaire_id,item,negated,scale,created_at,updated_at) VALUES(?,?,?,? ,NOW(),NOW())");
                foreach ($clean_items as $it) {
                    $ins->execute([$qid,$it['text'],$it['negated'],$it['scale']]);
                }
            }
            $feedback=['type'=>'success','msg'=>'Erfolgreich aktualisiert.'];
        } else {
            $pdo->prepare("INSERT INTO questionnaires(name,short,language,choice_type,author_password_hash,description,operationalization,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())")
                ->execute([$name,$short,$language,$choice_type,$author_hash,$description,$jsonOp]);
            $new_qid = $pdo->lastInsertId();
            $ins2 = $pdo->prepare("INSERT INTO items(questionnaire_id,item,negated,scale,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())");
            foreach ($clean_items as $it) {
                $ins2->execute([$new_qid,$it['text'],$it['negated'],$it['scale']]);
            }
            $feedback=['type'=>'success','msg'=>'Fragebogen erstellt.'];
            $editing=false;
            $questionnaire=null;
            $items=[];
        }
        // Reload
        if ($editing) {
            $items = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id=? ORDER BY id")
                         ->execute([$qid]) && $pdo->query("SELECT *")->fetchAll();
            $operational = $opData;
        }
    } else {
        $feedback=['type'=>'danger','msg'=>implode('<br>',$errors)];
    }
}

// Mindestens eine Zeile
if (empty($items)) $items=[['text'=>'','scale'=>'','negated'=>0]];

require 'navbar.inc.php';
?>

<div class="container py-4" style="max-width:900px;">
  <h2 class="mb-3"><?= $editing?'Fragebogen bearbeiten':'Neuen Fragebogen erstellen' ?></h2>
  <?php if (!empty($feedback)): ?>
    <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
  <?php endif; ?>

  <form method="post" id="questionnaireForm" autocomplete="off">
    <div class="card mb-4"><div class="card-body">
      <div class="row mb-3">
        <div class="col-md-7">
          <label class="form-label">Name *</label>
          <input name="name" class="form-control" required maxlength="255"
                 value="<?= htmlspecialchars($questionnaire['name']??'') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Kürzel</label>
          <input name="short" class="form-control" maxlength="50"
                 value="<?= htmlspecialchars($questionnaire['short']??'') ?>">
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Sprache *</label>
          <select name="language" class="form-select" required>
            <?php foreach($langs as $c=>$l): ?>
              <option value="<?=$c?>" <?= (($questionnaire['language']??'')===$c)?'selected':'' ?>>
                <?=$l?> (<?=$c?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skalentyp *</label>
          <select name="choice_type" class="form-select" <?= $editing&&$has_results?'disabled':''?> required>
            <?php foreach($choice_types as $v=>$t): ?>
              <option value="<?=$v?>" <?= (($questionnaire['choice_type']??'')==$v)?'selected':'' ?>>
                <?=$t?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if($editing&&$has_results): ?>
            <input type="hidden" name="choice_type" value="<?= intval($questionnaire['choice_type']) ?>">
          <?php endif; ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Beschreibung *</label>
        <textarea name="description" class="form-control" required rows="2"><?= htmlspecialchars($questionnaire['description']??'') ?></textarea>
      </div>
      <?php if (!$has_results||!empty($_SESSION['is_admin'])): ?>
      <div class="mb-3">
        <label class="form-label"><?= $editing?'Autor-Passwort ändern':'Autor-Passwort' ?> <small>(8+ Zeichen)</small></label>
        <input type="password" name="author_password" class="form-control" <?= $editing?'':'required'?> minlength="8">
        <?php if($editing&&$questionnaire['author_password_hash']): ?>
          <div class="form-text">Leer lassen, um beizubehalten</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="form-check mb-3">
        <input name="copyright" class="form-check-input" type="checkbox" required>
        <label class="form-check-label">Ich bestätige, dass ich Eigentümer bin.</label>
      </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
      <label class="form-label mb-2">Items *</label>
      <?php if($editing&&$has_results): ?>
        <div class="alert alert-info">Items können nicht geändert werden (Ergebnisse vorhanden).</div>
      <?php endif; ?>
      <div id="itemsList">
        <?php foreach($items as $i=>$it): ?>
        <div class="d-flex align-items-center mb-2 item-row">
          <input name="item[]" class="form-control me-2" placeholder="Text *" required
                 value="<?= htmlspecialchars($it['item'])?>" <?= $editing&&$has_results?'readonly':''?>>
          <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)"
                 value="<?= htmlspecialchars($it['scale'])?>" <?= $editing&&$has_results?'readonly':''?>>
          <input type="checkbox" name="negated[]" value="<?=$i?>"
                 class="form-check-input me-2" <?= $it['negated']?'checked':''?> <?= $editing&&$has_results?'disabled':''?>>
          <?php if(!$editing||!$has_results):?>
            <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>
          <?php endif;?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if(!$editing||!$has_results):?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddItem">Weiteres Item</button>
      <?php endif;?>
      <div class="mt-3 text-muted small" id="itemStats"></div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
      <h5 class="form-label">Operationalisierung</h5>
      <div class="mb-3">
        <label class="form-label">Allgemeine Beschreibung</label>
        <textarea id="opGlobal" class="form-control" rows="2"><?= htmlspecialchars($operational['global']??'') ?></textarea>
      </div>
      <div id="subscaleOps"></div>
      <input type="hidden" name="operationalization" id="operationalization">
    </div></div>

    <button type="submit" class="btn btn-success"><?= $editing?'Speichern':'Erstellen'?></button>
    <a href="edit_questionnaire.php" class="btn btn-link">Abbrechen</a>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const list=document.getElementById('itemsList'),
        addBtn=document.getElementById('btnAddItem'),
        stats=document.getElementById('itemStats'),
        opGlobal=document.getElementById('opGlobal'),
        subOps=document.getElementById('subscaleOps'),
        hiddenOp=document.getElementById('operationalization'),
        form=document.getElementById('questionnaireForm'),
        existing=<?= json_encode($operational) ?>;

  // Helper: Subskalen-Unique
  function getSubscales(){
    const set=new Set();
    list.querySelectorAll('input[name="scale[]"]').forEach(inp=>{
      const v=inp.value.trim();
      if(v) set.add(v);
    });
    return [...set];
  }
  // Render Subskala-Felder
  function renderSubscaleFields(){
    subOps.innerHTML='';
    getSubscales().forEach(sc=>{
      const d=document.createElement('div'); d.className='mb-3';
      d.innerHTML=`
        <label class="form-label">${sc} – Beschreibung</label>
        <textarea class="form-control sub-op" data-name="${sc}" rows="1"
          placeholder="z.B. ‚${sc} erfasst…‘">${existing.subscales[sc]||''}</textarea>`;
      subOps.append(d);
    });
  }
  // Stats
  function updateStats(){
    let cnt=0, set=new Set();
    list.querySelectorAll('.item-row').forEach((r,i)=>{
      if(r.querySelector('input[name="item[]"]').value.trim()) cnt++;
      const sc=r.querySelector('input[name="scale[]"]').value.trim();
      if(sc) set.add(sc);
      r.querySelector('input[type="checkbox"]').value=i;
    });
    let txt=`${cnt} Item${cnt!==1?'s':''}`;
    if(set.size) txt+=` | ${set.size} Subskala${set.size!==1?'en':''}: ${[...set].join(', ')}`;
    stats.textContent=txt;
  }
  // Add Row
  if(addBtn) addBtn.onclick=()=>{
    const div=document.createElement('div'); div.className='d-flex align-items-center mb-2 item-row';
    div.innerHTML=`
      <input name="item[]" class="form-control me-2" placeholder="Text *" required>
      <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)">
      <input type="checkbox" name="negated[]" class="form-check-input me-2" value="0">
      <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>`;
    list.append(div);
    updateStats(); renderSubscaleFields();
  };
  // Remove Row
  list.addEventListener('click',e=>{
    if(e.target.classList.contains('btn-remove-item')){
      e.target.closest('.item-row').remove();
      updateStats(); renderSubscaleFields();
    }
  });
  // Input Handler
  list.addEventListener('input',e=>{
    if(e.target.matches('input[name="item[]"],input[name="scale[]"]')){
      const rows=list.querySelectorAll('.item-row'),
            last=rows[rows.length-1];
      if(e.target.matches('input[name="item[]"]') && last===e.target.closest('.item-row') && e.target.value.trim()){
        addBtn.click();
      }
      updateStats(); renderSubscaleFields();
    }
  });
  // On Submit → JSON
  form.addEventListener('submit',()=>{
    const data={global:opGlobal.value.trim(),subscales:{}};
    subOps.querySelectorAll('.sub-op').forEach(ta=>{
      const v=ta.value.trim();
      if(v) data.subscales[ta.dataset.name]=v;
    });
    hiddenOp.value=JSON.stringify(data);
  });

  // Initial
  updateStats();
  renderSubscaleFields();
});
</script>

<?php include('footer.inc.php'); ob_end_flush(); ?>
