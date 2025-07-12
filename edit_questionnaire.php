<?php
ob_start();
require_once __DIR__ . '/include.inc.php';

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
$qid           = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
$questionnaire = null;
$items         = [];
$has_results   = false;
$feedback      = null;

// Laden für Bearbeitung
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing   = true;
        $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
        $stmt->execute([$qid]);
        $items = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE questionnaire_id = ?");
        $stmt->execute([$qid]);
        $has_results = $stmt->fetchColumn() > 0;
    }
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Meta-Felder
    $name        = trim($_POST['name'] ?? '');
    $short       = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language    = strtoupper(trim($_POST['language'] ?? ''));
    $choice_type = isset($_POST['choice_type']) ? intval($_POST['choice_type']) : null;
    $copyright   = isset($_POST['copyright']);

    // Item-Felder
    $texts      = $_POST['item']    ?? [];
    $scales     = $_POST['scale']   ?? [];
    $negIndices = isset($_POST['negated'])
                  ? array_map('intval', $_POST['negated'])
                  : [];

    $errors = [];
    if (!$name)         $errors[] = "Bitte einen Namen angeben.";
    if (!$description)  $errors[] = "Bitte eine Beschreibung eingeben.";
    if (!isset($langs[$language]))          $errors[] = "Bitte eine gültige Sprache wählen.";
    if (!isset($choice_types[$choice_type]))$errors[] = "Bitte einen gültigen Skalentyp wählen.";
    if (!$copyright)    $errors[] = "Bitte das Copyright bestätigen.";

    // Items aufbauen und validieren
    $clean_items = [];
    foreach ($texts as $i => $text) {
        $text = trim($text);
        if ($text === '') continue;
        $scale = trim($scales[$i] ?? '');
        $neg   = in_array($i, $negIndices, true) ? 1 : 0;
        $clean_items[] = [
            'text'    => $text,
            'scale'   => $scale,
            'negated' => $neg
        ];
    }
    if (count($clean_items) === 0) {
        $errors[] = "Mindestens ein Item muss eingetragen werden.";
    }
    // Duplikate verhindern
    $lower = array_map(fn($it) => mb_strtolower($it['text']), $clean_items);
    if (count($lower) !== count(array_unique($lower))) {
        $errors[] = "Es sind doppelte Items vorhanden.";
    }

    // Speichern
    if (empty($errors)) {
        if ($editing && $questionnaire) {
            if ($has_results) {
                // Nur Metadaten updaten
                $upd = $pdo->prepare("
                    UPDATE questionnaires
                       SET name=?, short=?, language=?, description=?, updated_at=NOW()
                     WHERE id=?
                ");
                $upd->execute([$name, $short, $language, $description, $qid]);
                $feedback = [
                    'type'=>'success',
                    'msg'=>"Metadaten aktualisiert.<br>Items können nicht geändert werden (Ergebnisse vorhanden)."
                ];
            } else {
                // Metadaten + Items
                $upd = $pdo->prepare("
                    UPDATE questionnaires
                       SET name=?, short=?, language=?, choice_type=?, description=?, updated_at=NOW()
                     WHERE id=?
                ");
                $upd->execute([$name, $short, $language, $choice_type, $description, $qid]);
                // Alte Items löschen
                $pdo->prepare("DELETE FROM items WHERE questionnaire_id=?")
                    ->execute([$qid]);
                // Neue Items einfügen
                $ins = $pdo->prepare("
                    INSERT INTO items
                      (questionnaire_id,item,negated,scale,created_at,updated_at)
                    VALUES (?,?,?,?,NOW(),NOW())
                ");
                foreach ($clean_items as $it) {
                    $ins->execute([$qid, $it['text'], $it['negated'], $it['scale']]);
                }
                $feedback = ['type'=>'success','msg'=>"Fragebogen und Items aktualisiert."];
            }
        } else {
            // Neu anlegen
            $ins = $pdo->prepare("
                INSERT INTO questionnaires
                  (name,short,language,choice_type,description,created_at,updated_at)
                VALUES (?,?,?,?,?,NOW(),NOW())
            ");
            $ins->execute([$name, $short, $language, $choice_type, $description]);
            $new_qid = $pdo->lastInsertId();
            // Items anfügen
            $ins2 = $pdo->prepare("
                INSERT INTO items
                  (questionnaire_id,item,negated,scale,created_at,updated_at)
                VALUES (?,?,?,?,NOW(),NOW())
            ");
            foreach ($clean_items as $it) {
                $ins2->execute([$new_qid, $it['text'], $it['negated'], $it['scale']]);
            }
            $feedback = ['type'=>'success','msg'=>"Fragebogen erstellt."];
            // Reset form state
            $editing = false;
            $questionnaire = null;
            $items = [];
        }
        // Reload Items nach Update
        if ($editing && $questionnaire) {
            $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id=? ORDER BY id ASC");
            $stmt->execute([$qid]);
            $items = $stmt->fetchAll();
        }
    } else {
        $feedback = ['type'=>'danger','msg'=>implode('<br>', $errors)];
    }
}

// Mindestens eine Zeile
if (empty($items)) {
    $items = [['text'=>'','scale'=>'','negated'=>0]];
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $editing ? "Fragebogen bearbeiten" : "Neuen Fragebogen erstellen" ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
  <style>
    body { background:#f7f9fb; }
    .card { box-shadow:0 6px 24px rgba(0,0,0,0.07); }
    .form-check-input { margin-top:0.3em; }
  </style>
</head>
<body>
<div class="container py-4" style="max-width:900px;">
  <h2 class="mb-3"><?= $editing ? "Fragebogen bearbeiten" : "Neuen Fragebogen erstellen" ?></h2>
  <?php if ($feedback): ?>
    <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
  <?php endif; ?>

  <form method="post" id="questionnaireForm" autocomplete="off">
    <!-- Metadaten -->
    <div class="card mb-4"><div class="card-body">
      <div class="row mb-3">
        <div class="col-md-7 mb-3 mb-md-0">
          <label class="form-label">Name *</label>
          <input name="name" class="form-control" required maxlength="255"
                 value="<?= htmlspecialchars($questionnaire['name'] ?? $_POST['name'] ?? '') ?>"
                 <?= $has_results ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-5">
          <label class="form-label">Kürzel (optional)</label>
          <input name="short" class="form-control" maxlength="50"
                 value="<?= htmlspecialchars($questionnaire['short'] ?? $_POST['short'] ?? '') ?>"
                 <?= $has_results ? 'readonly' : '' ?>>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label">Sprache *</label>
          <select name="language" class="form-select" required <?= $has_results ? 'disabled' : '' ?>>
            <option value="">Bitte wählen</option>
            <?php foreach ($langs as $code => $lang): ?>
              <option value="<?= $code ?>" <?= (($questionnaire['language'] ?? $_POST['language'] ?? '') === $code) ? 'selected' : '' ?>>
                <?= $lang ?> (<?= $code ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($has_results): ?>
            <input type="hidden" name="language" value="<?= htmlspecialchars($questionnaire['language']) ?>">
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skalentyp *</label>
          <select name="choice_type" class="form-select" required <?= $has_results ? 'disabled' : '' ?>>
            <option value="">Bitte wählen</option>
            <?php foreach ($choice_types as $val => $txt): ?>
              <option value="<?= $val ?>" <?= ((($questionnaire['choice_type'] ?? $_POST['choice_type'] ?? '') == $val) ? 'selected' : '') ?>>
                <?= $txt ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($has_results): ?>
            <input type="hidden" name="choice_type" value="<?= intval($questionnaire['choice_type']) ?>">
          <?php endif; ?>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Beschreibung *</label>
        <textarea name="description" class="form-control" required rows="2"><?= htmlspecialchars($questionnaire['description'] ?? $_POST['description'] ?? '') ?></textarea>
      </div>
    </div></div>

    <!-- Items -->
    <div class="card mb-4"><div class="card-body">
      <label class="form-label mb-2">Items *</label>
      <?php if ($has_results): ?>
        <div class="alert alert-info">Items können nicht geändert werden, da bereits Ergebnisse vorliegen.</div>
      <?php endif; ?>
      <div id="itemsList">
        <?php foreach ($items as $i => $it): ?>
          <div class="d-flex align-items-center mb-2 item-row">
            <input name="item[]" class="form-control me-2" placeholder="Text *" required
                   value="<?= htmlspecialchars($it['item']) ?>" <?= $has_results ? 'readonly' : '' ?>>
            <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)"
                   value="<?= htmlspecialchars($it['scale']) ?>" <?= $has_results ? 'readonly' : '' ?>>
            <input type="checkbox" name="negated[]" value="<?= $i ?>"
                   class="form-check-input me-2"
                   <?= ($it['negated'] ? 'checked' : '') ?> <?= $has_results ? 'disabled' : '' ?>>
            <?php if (!$has_results): ?>
              <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (!$has_results): ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddItem">Weiteres Item hinzufügen</button>
      <?php endif; ?>
      <div class="mt-3 text-muted small" id="itemStats"></div>
    </div></div>

    <div class="form-check mb-3">
      <input name="copyright" class="form-check-input" type="checkbox" required>
      <label class="form-check-label">
        Ich bestätige, dass ich der Eigentümer dieses Fragebogens bin und alle Rechte am Inhalt besitze.
      </label>
    </div>

    <button type="submit" class="btn btn-success"><?= $editing ? "Speichern" : "Fragebogen erstellen" ?></button>
    <a href="edit_questionnaire.php" class="btn btn-link">Neuen Fragebogen anlegen</a>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const list   = document.getElementById('itemsList');
  const addBtn = document.getElementById('btnAddItem');
  const stats  = document.getElementById('itemStats');

  function updateItemStats() {
    const rows = list.querySelectorAll('.item-row');
    let count = 0, scales = new Set();
    rows.forEach((r, idx) => {
      const txt = r.querySelector('input[name="item[]"]').value.trim();
      if (txt) count++;
      const sc = r.querySelector('input[name="scale[]"]').value.trim();
      if (sc) scales.add(sc);
      const cb = r.querySelector('input[type="checkbox"]');
      if (cb) cb.value = idx;
    });
    let info = count + " Item" + (count !== 1 ? "s" : "");
    if (scales.size) {
      info += " | " + scales.size + " Subskala" + (scales.size !== 1 ? "en" : "") + ": " + [...scales].join(", ");
    }
    stats.innerText = info;
  }

  if (addBtn) {
    addBtn.addEventListener('click', () => {
      const div = document.createElement('div');
      div.className = 'd-flex align-items-center mb-2 item-row';
      div.innerHTML = `
        <input name="item[]" class="form-control me-2" placeholder="Text *" required>
        <input name="scale[]" class="form-control me-2" placeholder="Subskala (optional)">
        <input type="checkbox" name="negated[]" class="form-check-input me-2" value="0">
        <button type="button" class="btn btn-link text-danger btn-remove-item px-2">&times;</button>
      `;
      list.appendChild(div);
      updateItemStats();
    });
  }

  list.addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove-item')) {
      e.target.closest('.item-row').remove();
      updateItemStats();
    }
  });

  list.addEventListener('input', e => {
    if (e.target.matches('input[name="item[]"], input[name="scale[]"]')) {
      const rows = list.querySelectorAll('.item-row');
      const last = rows[rows.length - 1];
      if (
        e.target.matches('input[name="item[]"]') &&
        last === e.target.closest('.item-row') &&
        e.target.value.trim()
      ) {
        addBtn.click();
      }
      updateItemStats();
    }
  });

  updateItemStats();
});
</script>
</body>
</html>
