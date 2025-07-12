<?php
ob_start();
require_once 'include.inc.php';
$pageTitle       = 'Übersicht';
$pageDescription = 'Du möchtest Fragen stellen? Du möchtest Fragen beantworten? PHPsychometric macht\'s möglich!';
require_once 'navbar.inc.php';

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
        ?>
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

// Laden für Bearbeitung
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing = true;
        $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
        $stmt->execute([$qid]);
        $items = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE questionnaire_id = ?");
        $stmt->execute([$qid]);
        $has_results = $stmt->fetchColumn() > 0;
    }
}

// Formularverarbeitung — nur, wenn das eigentliche Formular abgeschickt wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $errors = [];

    // Meta-Felder
    $name        = trim($_POST['name'] ?? '');
    $short       = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language    = strtoupper(trim($_POST['language'] ?? ''));
    $choice_type = isset($_POST['choice_type']) ? intval($_POST['choice_type']) : null;
    $copyright   = isset($_POST['copyright']);

    if (!$name)        $errors[] = "Bitte einen Namen angeben.";
    if (!$description) $errors[] = "Bitte eine Beschreibung eingeben.";
    if (!isset($langs[$language]))          $errors[] = "Bitte eine gültige Sprache wählen.";
    if (!isset($choice_types[$choice_type]))$errors[] = "Bitte einen gültigen Skalentyp wählen.";
    if (!$copyright)   $errors[] = "Bitte das Copyright bestätigen.";

    // Autor-Passwort
    $author_hash = null;
    if (isset($_POST['author_password']) && $_POST['author_password'] !== '') {
        $plain = trim($_POST['author_password']);
        if (strlen($plain) < 8) {
            $errors[] = "Autor-Passwort muss mindestens 8 Zeichen lang sein.";
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
        $text = trim($text);
        if ($text === '') continue;
        $scale = trim($scales[$i] ?? '');
        $neg   = in_array($i, $negIndices, true) ? 1 : 0;
        $clean_items[] = ['text'=>$text,'scale'=>$scale,'negated'=>$neg];
    }
    if (count($clean_items) === 0) {
        $errors[] = "Mindestens ein Item muss eingetragen werden.";
    }
    // Duplikate verhindern
    $lower = array_map(fn($it)=>mb_strtolower($it['text']), $clean_items);
    if (count($lower) !== count(array_unique($lower))) {
        $errors[] = "Es sind doppelte Items vorhanden.";
    }

    // Wenn keine Fehler, speichern
    if (empty($errors)) {
        if ($editing) {
            // Update Meta + Passwort + Items (je nach $has_results)
            $updFields = $has_results
                ? "name=?, short=?, language=?, description=?, author_password_hash=?, updated_at=NOW()"
                : "name=?, short=?, language=?, choice_type=?, description=?, author_password_hash=?, updated_at=NOW()";

            $params = $has_results
                ? [$name,$short,$language,$description,$author_hash,$qid]
                : [$name,$short,$language,$choice_type,$description,$author_hash,$qid];

            $pdo->prepare("UPDATE questionnaires SET $updFields WHERE id=?")
                ->execute($params);

            if (!$has_results) {
                $pdo->prepare("DELETE FROM items WHERE questionnaire_id=?")->execute([$qid]);
                $ins = $pdo->prepare("
                    INSERT INTO items
                      (questionnaire_id,item,negated,scale,created_at,updated_at)
                    VALUES(?,?,?,?,NOW(),NOW())
                ");
                foreach ($clean_items as $it) {
                    $ins->execute([$qid,$it['text'],$it['negated'],$it['scale']]);
                }
            }

            $feedback = ['type'=>'success','msg'=> $has_results
                ? "Metadaten aktualisiert, Items gesperrt."
                : "Fragebogen und Items aktualisiert."
            ];
        } else {
            // Neu anlegen
            $pdo->prepare("
                INSERT INTO questionnaires
                  (name,short,language,choice_type,author_password_hash,description,created_at,updated_at)
                VALUES(?,?,?,?,?,?,NOW(),NOW())
            ")->execute([$name,$short,$language,$choice_type,$author_hash,$description]);

            $new_qid = $pdo->lastInsertId();
            $ins2 = $pdo->prepare("
                INSERT INTO items
                  (questionnaire_id,item,negated,scale,created_at,updated_at)
                VALUES(?,?,?,?,NOW(),NOW())
            ");
            foreach ($clean_items as $it) {
                $ins2->execute([$new_qid,$it['text'],$it['negated'],$it['scale']]);
            }
            $feedback = ['type'=>'success','msg'=>"Fragebogen erstellt."];
            $editing = false;
            $questionnaire = null;
            $items = [];
        }
        // Daten neu laden
        if ($editing) {
            $items = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id=? ORDER BY id")
                         ->execute([$qid])
                         ->fetchAll();
        }
    } else {
        $feedback = ['type'=>'danger','msg'=> implode('<br>',$errors)];
    }
}

// Ensure at least one blank row
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
  <?php if (!empty($feedback)): ?>
    <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
  <?php endif; ?>

  <form method="post" id="questionnaireForm" autocomplete="off">
    <div class="card mb-4"><div class="card-body">
      <!-- Name & Short -->
      <div class="row mb-3">
        <div class="col-md-7 mb-3 mb-md-0">
          <label class="form-label">Name *</label>
          <input name="name" class="form-control" required maxlength="255"
                 value="<?= htmlspecialchars($questionnaire['name'] ?? '') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Kürzel (optional)</label>
          <input name="short" class="form-control" maxlength="50"
                 value="<?= htmlspecialchars($questionnaire['short'] ?? '') ?>">
        </div>
      </div>

      <!-- Sprache & Skalentyp -->
      <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label">Sprache *</label>
          <select name="language" class="form-select" required <?= $has_results ? 'disabled' : '' ?>>
            <option value="">Bitte wählen</option>
            <?php foreach ($langs as $code => $lang): ?>
              <option value="<?= $code ?>"
                <?= (($questionnaire['language'] ?? '') === $code) ? 'selected' : '' ?>>
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
              <option value="<?= $val ?>"
                <?= ((($questionnaire['choice_type'] ?? '') == $val) ? 'selected' : '') ?>>
                <?= $txt ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($has_results): ?>
            <input type="hidden" name="choice_type" value="<?= intval($questionnaire['choice_type']) ?>">
          <?php endif; ?>
        </div>
      </div>

      <!-- Description -->
      <div class="mb-3">
        <label class="form-label">Beschreibung *</label>
        <textarea name="description" class="form-control" required rows="2"><?= htmlspecialchars($questionnaire['description'] ?? '') ?></textarea>
      </div>

      <!-- Author Password -->
      <?php if (!$has_results || !empty($_SESSION['is_admin'])): ?>
      <div class="mb-3">
        <label class="form-label"><?= $editing ? "Autor-Passwort ändern" : "Autor-Passwort" ?> <small>(min. 8 Zeichen)</small></label>
        <input type="password" name="author_password" class="form-control" <?= $editing ? '' : 'required' ?> minlength="8">
        <?php if ($editing && $questionnaire['author_password_hash']): ?>
          <div class="form-text">Leer lassen, um es nicht zu ändern.</div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Copyright -->
      <div class="form-check mb-3">
        <input name="copyright" class="form-check-input" type="checkbox" required>
        <label class="form-check-label">Ich bestätige, dass ich Eigentümer bin.</label>
      </div>
    </div></div>

    <!-- Items -->
    <div class="card mb-4"><div class="card-body">
      <label class="form-label mb-2">Items *</label>
      <?php if ($has_results): ?>
        <div class="alert alert-info">Items gesperrt (Ergebnisse vorhanden).</div>
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
      r.querySelector('input[type="checkbox"]').value = idx;
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
