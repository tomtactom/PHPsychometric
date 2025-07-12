<?php
require_once 'include.inc.php';

// --- Sprachoptionen ---
$langs = [
    'DE'=>'Deutsch','EN'=>'Englisch','FR'=>'Französisch','IT'=>'Italienisch',
    'ES'=>'Spanisch','TR'=>'Türkisch','RU'=>'Russisch','AR'=>'Arabisch',
    'ZH'=>'Chinesisch','PT'=>'Portugiesisch','PL'=>'Polnisch','NL'=>'Niederländisch'
];

// --- ChoiceTypes ---
$choice_types = [
    0=>"Intervallskala (Schieberegler 0-100)",
    1=>"Dual: Wahr / Falsch",
    2=>"Dual: Stimme voll zu / Stimme nicht zu",
    3=>"3-stufige Likert-Skala",
    4=>"4-stufige Likert-Skala",
    5=>"5-stufige Likert-Skala",
    6=>"6-stufige Likert-Skala",
    7=>"7-stufige Likert-Skala"
];

// Initialisierung
$editing     = false;
$qid         = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
$questionnaire = null;
$items       = [];
$has_results = false;
$feedback    = null;

// Editing laden
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id=?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing = true;
        // Items laden
        $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id=? ORDER BY id ASC");
        $stmt->execute([$qid]);
        $items = $stmt->fetchAll();
        // Prüfen auf vorhandene Ergebnisse
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE questionnaire_id=?");
        $stmt->execute([$qid]);
        $has_results = $stmt->fetchColumn()>0;
    }
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // Form-Felder
    $name        = trim($_POST['name'] ?? '');
    $short       = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language    = strtoupper(trim($_POST['language'] ?? ''));
    $choice_type = isset($_POST['choice_type']) ? intval($_POST['choice_type']) : null;
    $copyright   = isset($_POST['copyright']);
    $texts       = $_POST['item']  ?? [];
    $scales      = $_POST['scale'] ?? [];
    $negs        = $_POST['negated'] ?? [];

    // Validierung
    $errors = [];
    if (!$name)         $errors[]="Bitte einen Namen angeben.";
    if (!$description)  $errors[]="Bitte eine Beschreibung eingeben.";
    if (!isset($langs[$language]))        $errors[]="Bitte eine gültige Sprache wählen.";
    if (!isset($choice_types[$choice_type])) $errors[]="Bitte einen gültigen Skalentyp wählen.";
    if (!$copyright)    $errors[]="Bitte das Copyright bestätigen.";

    // Items zusammenbauen und prüfen
    $clean_items = [];
    foreach ($texts as $i=>$txt) {
        $txt = trim($txt);
        if ($txt==="") continue;
        $scale = trim($scales[$i] ?? '');
        $neg   = !empty($negs[$i]) ? 1 : 0;
        $clean_items[] = ['item'=>$txt,'scale'=>$scale,'negated'=>$neg];
    }
    if (count($clean_items)===0) $errors[]="Mindestens ein Item muss eingetragen werden.";

    // Duplikate
    $lower = array_map(fn($it)=>mb_strtolower($it['item']), $clean_items);
    if (count($lower)!==count(array_unique($lower))) {
        $errors[]="Es sind doppelte Items vorhanden.";
    }

    if (!$errors) {
        // Speichern
        if ($editing && $questionnaire) {
            // Update
            if ($has_results) {
                // Nur Metadaten
                $stmt = $pdo->prepare(
                  "UPDATE questionnaires
                     SET name=?, short=?, language=?, description=?, updated_at=NOW()
                   WHERE id=?"
                );
                $stmt->execute([$name,$short,$language,$description,$qid]);
                $feedback=['type'=>'success',
                  'msg'=>"Metadaten aktualisiert.<br>Items können nicht geändert werden, da bereits Ergebnisse vorliegen."];
            } else {
                // Metadaten + Items
                $stmt = $pdo->prepare(
                  "UPDATE questionnaires
                     SET name=?, short=?, language=?, choice_type=?, description=?, updated_at=NOW()
                   WHERE id=?"
                );
                $stmt->execute([$name,$short,$language,$choice_type,$description,$qid]);
                // Alte Items löschen
                $pdo->prepare("DELETE FROM items WHERE questionnaire_id=?")
                    ->execute([$qid]);
                // Neue einfügen
                $ins = $pdo->prepare(
                  "INSERT INTO items
                     (questionnaire_id,item,negated,scale,created_at,updated_at)
                   VALUES (?,?,?,?,NOW(),NOW())"
                );
                foreach ($clean_items as $it) {
                    $ins->execute([$qid,$it['item'],$it['negated'],$it['scale']]);
                }
                $feedback=['type'=>'success','msg'=>"Fragebogen aktualisiert."];
            }
        } else {
            // Neu anlegen
            $ins = $pdo->prepare(
              "INSERT INTO questionnaires
                 (name,short,language,choice_type,description,created_at,updated_at)
               VALUES (?,?,?,?,?,NOW(),NOW())"
            );
            $ins->execute([$name,$short,$language,$choice_type,$description]);
            $new_qid = $pdo->lastInsertId();
            $ins2 = $pdo->prepare(
              "INSERT INTO items
                 (questionnaire_id,item,negated,scale,created_at,updated_at)
               VALUES (?,?,?,?,NOW(),NOW())"
            );
            foreach ($clean_items as $it) {
                $ins2->execute([$new_qid,$it['item'],$it['negated'],$it['scale']]);
            }
            $feedback=['type'=>'success','msg'=>"Fragebogen gespeichert."];
            // Reset
            $editing=false; $questionnaire=null; $items=[];
        }
        // Neu laden falls edit
        if ($editing && $questionnaire) {
            $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id=? ORDER BY id ASC");
            $stmt->execute([$qid]);
            $items = $stmt->fetchAll();
        }
    } else {
        $feedback=['type'=>'danger','msg'=>implode('<br>',$errors)];
    }
}

// Mindestens eine Zeile
if (empty($items)) {
    $items = [['item'=>'','scale'=>'','negated'=>0]];
}

// Vorbefüllung der Skalen-Autocomplete
$all_scales = [];
foreach ($items as $it) {
    if ($it['scale']!=='' && !in_array($it['scale'],$all_scales)) {
        $all_scales[]=$it['scale'];
    }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $editing?"Fragebogen bearbeiten":"Neuen Fragebogen erstellen" ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
  <style>
    body{background:#f7f9fb}
    .card{box-shadow:0 6px 24px rgba(0,0,0,0.07)}
    .drag-handle{cursor:grab;color:#ccc}
    .autocomplete-list{position:absolute;background:#fff;border:1px solid #ddd;z-index:10;max-height:170px;overflow:auto}
    .autocomplete-item{padding:.3em .9em;cursor:pointer}
    .autocomplete-item:hover{background:#f1f1f1}
  </style>
</head>
<body>
<div class="container py-4" style="max-width:900px;">
  <h2 class="mb-3"><?= $editing?"Fragebogen bearbeiten":"Neuen Fragebogen erstellen" ?></h2>
  <?php if($feedback): ?>
    <div class="alert alert-<?=$feedback['type']?>"><?=$feedback['msg']?></div>
  <?php endif; ?>

  <form method="post" id="questionnaireForm" autocomplete="off">
    <div class="card mb-4"><div class="card-body">
      <div class="row mb-3">
        <div class="col-md-7 mb-3 mb-md-0">
          <label class="form-label">Name *</label>
          <input name="name" class="form-control" required maxlength="255"
            value="<?=htmlspecialchars($questionnaire['name']??$_POST['name']??'')?>"
            <?=$has_results?'readonly':''?>>
        </div>
        <div class="col-md-5">
          <label class="form-label">Kürzel</label>
          <input name="short" class="form-control" maxlength="50"
            value="<?=htmlspecialchars($questionnaire['short']??$_POST['short']??'')?>"
            <?=$has_results?'readonly':''?>>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label">Sprache *</label>
          <select name="language" class="form-select" required <?=$has_results?'disabled':''?>>
            <option value="">Bitte wählen</option>
            <?php foreach($langs as $c=>$l): ?>
              <option value="<?=$c?>" <?=((($questionnaire['language']??$_POST['language']??'')==$c)?'selected':'')?>><?=$l?> (<?=$c?>)</option>
            <?php endforeach;?>
          </select>
          <?php if($has_results): ?>
            <input type="hidden" name="language" value="<?=htmlspecialchars($questionnaire['language'])?>">
          <?php endif;?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skalentyp *</label>
          <select name="choice_type" class="form-select" required <?=$has_results?'disabled':''?>>
            <option value="">Bitte wählen</option>
            <?php foreach($choice_types as $v=>$t): ?>
              <option value="<?=$v?>" <?=((($questionnaire['choice_type']??$_POST['choice_type']??'')==$v)?'selected':'')?>><?=$t?></option>
            <?php endforeach;?>
          </select>
          <?php if($has_results): ?>
            <input type="hidden" name="choice_type" value="<?=intval($questionnaire['choice_type'])?>">
          <?php endif;?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Beschreibung *</label>
        <textarea name="description" class="form-control" required rows="2"><?=$questionnaire['description']??$_POST['description']??''?></textarea>
      </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
      <label class="form-label mb-2">Items *</label>
      <?php if($has_results): ?>
        <div class="alert alert-info">Items können nicht geändert werden, da bereits Ergebnisse vorliegen.</div>
      <?php endif;?>
      <div id="itemsList">
        <?php foreach($items as $i=>$it): ?>
          <div class="row align-items-center mb-2 item-row" draggable="true">
            <div class="col-auto pe-0">
              <?php if(!$has_results):?><span class="drag-handle fs-4">&#9776;</span><?php endif;?>
            </div>
            <div class="col-7">
              <input name="item[]" class="form-control" placeholder="Text *" required
                     value="<?=htmlspecialchars($it['item'])?>" <?=$has_results?'readonly':''?>>
            </div>
            <div class="col-3 position-relative">
              <input name="scale[]" class="form-control scale-field" placeholder="Subskala"
                     value="<?=htmlspecialchars($it['scale'])?>" <?=$has_results?'readonly':''?>>
              <div class="autocomplete-list d-none"></div>
            </div>
            <div class="col-1 text-center">
              <input type="checkbox" name="negated[]" class="form-check-input" <?=$it['negated']?'checked':''?> <?=$has_results?'disabled':''?>>
            </div>
            <?php if(!$has_results): ?>
              <div class="col-auto">
                <button type="button" class="btn btn-link text-danger btn-remove-item">&times;</button>
              </div>
            <?php endif;?>
          </div>
        <?php endforeach;?>
      </div>
      <?php if(!$has_results): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddItem">Weiteres Item</button>
      <?php endif;?>
      <div class="mt-3 text-muted small" id="itemStats"></div>
    </div></div>

    <div class="form-check mb-3">
      <input name="copyright" class="form-check-input" type="checkbox" required>
      <label class="form-check-label">Ich besitze alle Rechte am Inhalt.</label>
    </div>

    <button type="submit" class="btn btn-success"><?= $editing?"Speichern":"Erstellen" ?></button>
    <a href="edit_questionnaire.php" class="btn btn-link">Neuer Fragebogen</a>
  </form>
</div>

<template id="itemRowTemplate">
  <div class="row align-items-center mb-2 item-row" draggable="true">
    <div class="col-auto pe-0"><span class="drag-handle fs-4">&#9776;</span></div>
    <div class="col-7"><input name="item[]" class="form-control" placeholder="Text *" required></div>
    <div class="col-3 position-relative">
      <input name="scale[]" class="form-control scale-field" placeholder="Subskala">
      <div class="autocomplete-list d-none"></div>
    </div>
    <div class="col-1 text-center">
      <input name="negated[]" class="form-check-input" type="checkbox">
    </div>
    <div class="col-auto">
      <button type="button" class="btn btn-link text-danger btn-remove-item">&times;</button>
    </div>
  </div>
</template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', ()=>{
  const list  = document.getElementById('itemsList');
  const temp  = document.getElementById('itemRowTemplate').content;
  const addBtn= document.getElementById('btnAddItem');
  const stats = document.getElementById('itemStats');

  function updateStats(){
    const rows=list.querySelectorAll('.item-row');
    let count=0,scales=new Set();
    rows.forEach(r=>{
      const txt=r.querySelector('input[name="item[]"]').value.trim();
      const s=r.querySelector('input[name="scale[]"]').value.trim();
      if(txt) count++;
      if(s) scales.add(s);
    });
    let msg = `${count} Item${count!==1?'s':''}`;
    if(scales.size){
      msg+=` | ${scales.size} Subskala${scales.size!==1?'en':''}: ${[...scales].join(', ')}`;
    }
    stats.innerText=msg;
  }

  if(addBtn){
    addBtn.onclick=()=>{
      list.appendChild(temp.cloneNode(true));
      updateStats();
    };
  }

  list.addEventListener('click', e=>{
    if(e.target.classList.contains('btn-remove-item')){
      e.target.closest('.item-row').remove();
      updateStats();
    }
  });

  list.addEventListener('input', e=>{
    if(e.target.matches('input[name="item[]"]')){
      const rows=list.querySelectorAll('.item-row');
      const last=rows[rows.length-1];
      if(last.querySelector('input[name="item[]"]').value.trim()){
        list.appendChild(temp.cloneNode(true));
      }
      updateStats();
    }
    if(e.target.matches('input[name="scale[]"]')){
      updateStats();
    }
  });

  // Autocomplete Skalen
  list.addEventListener('focusin', e=>{
    if(e.target.matches('.scale-field')){
      const field=e.target;
      const listEl=field.nextElementSibling;
      const opts=[...document.querySelectorAll('.scale-field')]
        .map(f=>f.value.trim()).filter(v=>v&&v!==field.value);
      const uniq=[...new Set(opts)];
      listEl.innerHTML='';
      uniq.forEach(o=>{
        const d=document.createElement('div');
        d.className='autocomplete-item';
        d.innerText=o;
        d.onclick=()=>{ field.value=o; listEl.classList.add('d-none'); updateStats(); };
        listEl.appendChild(d);
      });
      listEl.classList.toggle('d-none',uniq.length===0);
    }
  });

  document.addEventListener('click', e=>{
    if(!e.target.matches('.scale-field')){
      document.querySelectorAll('.autocomplete-list')
        .forEach(el=>el.classList.add('d-none'));
    }
  });

  // Drag & Drop
  Sortable.create(list,{handle:'.drag-handle',animation:150,ghostClass:'bg-light'});
  updateStats();
});
</script>
</body>
</html>
