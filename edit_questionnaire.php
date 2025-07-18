<?php
ob_start();
require_once 'include.inc.php';
$pageTitle       = $editing ? 'Fragebogen bearbeiten' : 'Fragebogen erstellen';
$pageDescription = 'Erstelle oder bearbeite deinen Fragebogen';

// --- CSRF-Token erzeugen ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['csrf'] = bin2hex(random_bytes(16));

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
        require_once 'navbar.inc.php'; ?>
        <div class="container py-5">
          <div class="card mx-auto" style="max-width:400px">
            <div class="card-body">
              <h5 class="card-title">Nur für Autoren/Admins</h5>
              <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
              <?php endif; ?>
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
        </body></html>
        <?php exit;
    }
}

// --- Sprachoptionen ---
$langs = [
    'DE'=>'Deutsch','EN'=>'Englisch','FR'=>'Französisch','IT'=>'Italienisch',
    'ES'=>'Spanisch','TR'=>'Türkisch','RU'=>'Russisch','AR'=>'Arabisch',
    'ZH'=>'Chinesisch','PT'=>'Portugiesisch','PL'=>'Polnisch','NL'=>'Niederländisch'
];
// --- ChoiceTypes ---
$choice_types = [
    0=>"Intervallskala (Schieberegler 0–100)",
    1=>"Dual: Wahr / Falsch",
    2=>"Dual: Stimme voll zu / Stimme nicht zu",
    3=>"3-stufige Likert-Skala",
    4=>"4-stufige Likert-Skala",
    5=>"5-stufige Likert-Skala",
    6=>"6-stufige Likert-Skala",
    7=>"7-stufige Likert-Skala"
];

// Initialisierung
$editing       = false;
$questionnaire = null;
$items         = [];
$has_results   = false;
$feedback      = null;
$operational   = ['global'=>'','subscales'=>[]];
$fieldErrors   = [];

// Laden für Bearbeitung
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing = true;
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
    // CSRF prüfen
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Ungültiger CSRF-Token');
    }

    $errors = [];

    // Meta-Felder
    $name        = trim($_POST['name'] ?? '');
    $short       = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language    = strtoupper(trim($_POST['language'] ?? ''));
    $choice_type = isset($_POST['choice_type']) ? intval($_POST['choice_type']) : null;
    $copyright   = isset($_POST['copyright']);
    $jsonOp      = $_POST['operationalization'] ?? '';

    if (!$name) {
        $fieldErrors['name'][] = "Bitte einen Namen angeben.";
    }
    if (!$description) {
        $fieldErrors['description'][] = "Bitte eine Beschreibung eingeben.";
    }
    if (!isset($langs[$language])) {
        $fieldErrors['language'][] = "Bitte eine gültige Sprache wählen.";
    }
    if (!isset($choice_types[$choice_type])) {
        $fieldErrors['choice_type'][] = "Bitte einen gültigen Skalentyp wählen.";
    }
    // validate operational JSON
    $opData = @json_decode($jsonOp, true);
    if (!is_array($opData) || !isset($opData['global'], $opData['subscales']) || !is_array($opData['subscales'])) {
        $fieldErrors['operationalization'][] = "Operationalisierung fehlerhaft.";
    }
    if (!$copyright) {
        $fieldErrors['copyright'][] = "Bitte das Copyright bestätigen.";
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
        $fieldErrors['items'][] = "Mindestens ein Item muss eingetragen werden.";
    }
    // Duplikate prüfen
    $lower = array_map(fn($it)=>mb_strtolower($it['text']), $clean_items);
    if (count($lower) !== count(array_unique($lower))) {
        $fieldErrors['items'][] = "Es sind doppelte Items vorhanden.";
    }

    // sammeln aller Feld-Errors
    foreach ($fieldErrors as $ferr) {
        $errors = array_merge($errors, $ferr);
    }

    // Speichern
    if (empty($errors)) {
        // Autor-Passwort
        $author_hash = null;
        if (isset($_POST['author_password']) && $_POST['author_password'] !== '') {
            $plain = trim($_POST['author_password']);
            if (strlen($plain) >= 8) {
                $author_hash = password_hash($plain, PASSWORD_DEFAULT);
            }
        } elseif ($editing && $has_results && empty($_SESSION['is_admin'])) {
            $author_hash = $questionnaire['author_password_hash'];
        }

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
                ? "Metadaten und Operationalisierung aktualisiert, Items gesperrt."
                : "Fragebogen, Items und Operationalisierung aktualisiert."
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
            $feedback = ['type'=>'success','msg'=>"Fragebogen erstellt."];
            $editing = false;
            $items = [];
        }
        // reload questionnaire + operationalization
        if ($editing) {
            $stmtReloadQ = $pdo->prepare("SELECT * FROM questionnaires WHERE id=?");
            $stmtReloadQ->execute([$qid]);
            $questionnaire = $stmtReloadQ->fetch();
            $operational = json_decode($questionnaire['operationalization'] ?? '{}', true)
                         ?: ['global'=>'','subscales'=>[]];
            // reload items
            $stmtReload = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
            $stmtReload->execute([$qid]);
            $items = $stmtReload->fetchAll();
        }
    }
}

// ensure blank row
if (empty($items)) {
    $items = [['item'=>'','scale'=>'','negated'=>0]];
}

require_once 'navbar.inc.php';
?>
<div class="container py-4" style="max-width:900px;">
  <h2 class="mb-3"><?= $editing ? "Fragebogen bearbeiten" : "Neuen Fragebogen erstellen" ?></h2>
  <?php if (!empty($feedback)): ?>
    <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
  <?php endif; ?>

  <form method="post" id="questionnaireForm" autocomplete="off" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
    <!-- Metadaten Card -->
    <div class="card mb-4"><div class="card-body">
      <!-- Name & Short -->
      <div class="row mb-3">
        <div class="col-md-7 mb-3 mb-md-0">
          <label class="form-label">Name *</label>
          <input name="name" id="field-name" class="form-control <?= isset($fieldErrors['name'])?'is-invalid':''?>" required maxlength="255"
                 value="<?= htmlspecialchars($questionnaire['name'] ?? '') ?>">
          <?php if (isset($fieldErrors['name'])): ?>
            <div class="invalid-feedback"><?= implode('<br>', $fieldErrors['name']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-md-5">
          <label class="form-label">Kürzel (optional)</label>
          <input name="short" id="field-short" class="form-control" maxlength="50"
                 value="<?= htmlspecialchars($questionnaire['short'] ?? '') ?>">
        </div>
      </div>

      <!-- Sprache & Skalentyp -->
      <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label">Sprache *</label>
          <select name="language" id="field-language" class="form-select <?= isset($fieldErrors['language'])?'is-invalid':''?>" required <?= $has_results?'disabled':''?>>
            <option value="">Bitte wählen</option>
            <?php foreach($langs as $code=>$lang): ?>
            <option value="<?=$code?>" <?=(($questionnaire['language']??'')===$code)?'selected':''?>>
              <?=$lang?> (<?=$code?>)
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($fieldErrors['language'])): ?>
            <div class="invalid-feedback"><?= implode('<br>', $fieldErrors['language']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skalentyp *</label>
          <select name="choice_type" id="field-choice_type" class="form-select <?= isset($fieldErrors['choice_type'])?'is-invalid':''?>" required <?= $has_results?'disabled':''?>>
            <option value="">Bitte wählen</option>
            <?php foreach($choice_types as $val=>$txt): ?>
            <option value="<?=$val?>" <?=(($questionnaire['choice_type']??'')==$val)?'selected':''?>>
              <?=$txt?>
            </option>
            <?php endforeach;?>
          </select>
          <?php if (isset($fieldErrors['choice_type'])): ?>
            <div class="invalid-feedback"><?= implode('<br>', $fieldErrors['choice_type']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Beschreibung -->
      <div class="mb-3">
        <label class="form-label">Beschreibung *</label>
        <textarea name="description" id="field-description" class="form-control <?= isset($fieldErrors['description'])?'is-invalid':''?>" required rows="2"><?= htmlspecialchars($questionnaire['description'] ?? '') ?></textarea>
        <?php if (isset($fieldErrors['description'])): ?>
          <div class="invalid-feedback"><?= implode('<br>', $fieldErrors['description']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Operationalisierung -->
      <div class="mb-3">
        <label class="form-label">Operationalisierung</label>
        <textarea id="opGlobal" class="form-control <?= isset($fieldErrors['operationalization'])?'is-invalid':''?>" placeholder="Allgemeine Skalen-Beschreibung" rows="2"><?= htmlspecialchars($operational['global']) ?></textarea>
        <?php if (isset($fieldErrors['operationalization'])): ?>
          <div class="invalid-feedback"><?= implode('<br>', $fieldErrors['operationalization']) ?></div>
        <?php endif; ?>
      </div>
      <div id="subscaleOps" class="mb-3"></div>
      <input type="hidden" name="operationalization" id="operationalization">

      <!-- Copyright -->
      <div class="form-check mb-3">
        <input name="copyright" id="field-copyright" class="form-check-input <?= isset($fieldErrors['copyright'])?'is-invalid':''?>" type="checkbox" required>
        <label class="form-check-label">Ich bestätige, dass ich Eigentümer bin.</label>
        <?php if (isset($fieldErrors['copyright'])): ?>
          <div class="invalid-feedback"><?= implode('<br>', $fieldErrors['copyright']) ?></div>
        <?php endif; ?>
      </div>
    </div></div>

    <!-- Items Card -->
    <div class="card mb-4"><div class="card-body">
      <label class="form-label mb-2">Items *</label>
      <?php if (isset($fieldErrors['items'])): ?>
        <div class="alert alert-danger"><?= implode('<br>', $fieldErrors['items']) ?></div>
      <?php elseif($has_results): ?>
        <div class="alert alert-info">Items gesperrt (Ergebnisse vorhanden).</div>
      <?php endif; ?>
      <div id="itemsList">
        <?php foreach($items as $i=>$it): ?>
        <div class="d-flex align-items-center mb-2 item-row">
          <span class="drag-handle fs-4 me-2">&#9776;</span>
          <input name="item[]" class="form-control me-2" placeholder="Text *" required <?= $has_results?'readonly':''?>
                 value="<?= htmlspecialchars($it['item']) ?>">
          <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)" <?= $has_results?'readonly':''?>
                 value="<?= htmlspecialchars($it['scale']) ?>">
          <input type="checkbox" name="negated[]" value="<?= $i ?>"
                 class="form-check-input me-2" <?= $it['negated']?'checked':''?> <?= $has_results?'disabled':''?>>
          <?php if (!$has_results): ?>
            <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>
          <?php endif; ?>
        </div>
        <?php endforeach;?>
      </div>
      <?php if (!$has_results):?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddItem">Weiteres Item hinzufügen</button>
      <?php endif;?>
      <div class="mt-3 text-muted small" id="itemStats"></div>
    </div></div>

    <div class="d-flex">
      <button type="submit" class="btn btn-success"><?= $editing?"Speichern":"Fragebogen erstellen" ?></button>
      <button type="button" class="btn btn-secondary ms-2" id="btnReset">Zurücksetzen</button>
    </div>
  </form>
</div>

<style>
  .drag-handle:hover { transform: scale(1.1); transition: transform .2s; cursor: grab; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form       = document.getElementById('questionnaireForm');
  const list       = document.getElementById('itemsList');
  const addBtn     = document.getElementById('btnAddItem');
  const stats      = document.getElementById('itemStats');
  const opGlobal   = document.getElementById('opGlobal');
  const subscaleOps= document.getElementById('subscaleOps');
  const opHidden   = document.getElementById('operationalization');
  const resetBtn   = document.getElementById('btnReset');
  const qid        = <?= json_encode($qid) ?>;
  const storageKey = 'edit_q_' + (qid||'new');
  // capture initial data for reset
  const initialData = formToObject();

  // Autosize textareas
  function autosize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }
  document.querySelectorAll('textarea').forEach(autosize);

  // Form → Object
  function formToObject(){
    const data = {
      name: form.name.value,
      short: form.short.value,
      description: form.description.value,
      language: form.language.value,
      choice_type: form.choice_type.value,
      operational_global: opGlobal.value,
      items: [],
    };
    document.querySelectorAll('.item-row').forEach((r,i)=>{
      data.items.push({
        item: r.querySelector('input[name="item[]"]').value,
        scale: r.querySelector('input[name="scale[]"]').value,
        negated: r.querySelector('input[name="negated[]"]').checked
      });
    });
    document.querySelectorAll('#subscaleOps textarea').forEach(t=>{
      data['sub_'+t.dataset.subscale] = t.value;
    });
    return data;
  }
  // Object → Form
  function objectToForm(data){
    form.name.value = data.name||'';
    form.short.value = data.short||'';
    form.description.value = data.description||'';
    form.language.value = data.language||'';
    form.choice_type.value = data.choice_type||'';
    opGlobal.value = data.operational_global||'';
    renderSubscaleFields();
    // items
    list.innerHTML = '';
    data.items.forEach((it,idx)=>{
      const div = document.createElement('div');
      div.className='d-flex align-items-center mb-2 item-row';
      div.innerHTML = `
        <span class="drag-handle fs-4 me-2">&#9776;</span>
        <input name="item[]" class="form-control me-2" placeholder="Text *" required value="${it.item||''}">
        <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)" value="${it.scale||''}">
        <input type="checkbox" name="negated[]" class="form-check-input me-2" ${it.negated?'checked':''}>
        <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>`;
      list.append(div);
    });
    updateItemStats();
    document.querySelectorAll('textarea').forEach(autosize);
  }

  // Auto‑Save to localStorage
  function saveToStorage(){
    localStorage.setItem(storageKey, JSON.stringify(formToObject()));
  }
  // Load from localStorage if exists
  const stored = localStorage.getItem(storageKey);
  if (stored) {
    try { objectToForm(JSON.parse(stored)); } catch{}
  }

  // Reset to initial DB state
  resetBtn.addEventListener('click', ()=>{
    objectToForm(initialData);
    localStorage.removeItem(storageKey);
  });

  // Update stats & subscale fields
  function updateItemStats() {
    const rows = list.querySelectorAll('.item-row');
    let count=0, scales=new Set();
    rows.forEach((r,idx)=>{
      const t = r.querySelector('input[name="item[]"]').value.trim();
      if(t) count++;
      const s = r.querySelector('input[name="scale[]"]').value.trim();
      if(s) scales.add(s);
      r.querySelector('input[name="negated[]"]').value=idx;
    });
    let info = `${count} Item${count!==1?'s':''}`;
    if(scales.size) info+= ` | ${scales.size} Subskala${scales.size!==1?'en':''}: ${[...scales].join(', ')}`;
    stats.innerText=info;
  }

  function getSubscales() {
    const set = new Set();
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
      const l=document.createElement('label'); l.className='form-label';
      l.textContent = name+' – Beschreibung';
      const ta=document.createElement('textarea');
      ta.className='form-control'; ta.rows=1; ta.dataset.subscale=name;
      ta.placeholder=`z. B. ‚${name} erfasst …‘`;
      if (initialData['sub_'+name]) ta.value=initialData['sub_'+name];
      ta.addEventListener('input', ()=>{ autosize(ta); saveToStorage(); });
      d.append(l,ta); subscaleOps.append(d);
    });
  }

  // Prepare hidden op JSON before submit
  form.addEventListener('submit', ()=>{
    const data = { global:opGlobal.value.trim(), subscales:{} };
    subscaleOps.querySelectorAll('textarea').forEach(ta=>{
      if(ta.value.trim()) data.subscales[ta.dataset.subscale]=ta.value.trim();
    });
    opHidden.value = JSON.stringify(data);
    localStorage.removeItem(storageKey);
  });

  // Item add/remove logic
  if (addBtn) addBtn.addEventListener('click', ()=>{
    const div=document.createElement('div'); div.className='d-flex align-items-center mb-2 item-row';
    div.innerHTML=`
      <span class="drag-handle fs-4 me-2">&#9776;</span>
      <input name="item[]" class="form-control me-2" placeholder="Text *" required>
      <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)">
      <input type="checkbox" name="negated[]" class="form-check-input me-2">
      <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>`;
    list.append(div);
    updateItemStats();
    renderSubscaleFields();
    saveToStorage();
  });
  list.addEventListener('click', e=>{
    if (e.target.classList.contains('btn-remove-item')) {
      e.target.closest('.item-row').remove();
      updateItemStats();
      renderSubscaleFields();
      saveToStorage();
    }
  });
  list.addEventListener('input', e=>{
    if (e.target.matches('input[name="item[]"], input[name="scale[]"]')) {
      const rows=list.querySelectorAll('.item-row');
      const last=rows[rows.length-1];
      if (e.target.matches('input[name="item[]"]') && last===e.target.closest('.item-row') && e.target.value.trim()) {
        addBtn.click();
      }
      updateItemStats();
      renderSubscaleFields();
      saveToStorage();
    }
  });

  // initial render
  updateItemStats();
  renderSubscaleFields();
  form.querySelectorAll('textarea').forEach(autosize);
});
</script>
<?php
include('footer.inc.php');
ob_end_flush();
?>
