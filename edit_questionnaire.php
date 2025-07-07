<?php
require_once 'include.inc.php';

// --- Sprachoptionen (Beispiel, kann erweitert werden) ---
$langs = [
    'DE' => 'Deutsch',
    'EN' => 'Englisch',
    'FR' => 'Französisch',
    'IT' => 'Italienisch',
    'ES' => 'Spanisch',
    'TR' => 'Türkisch',
    'RU' => 'Russisch',
    'AR' => 'Arabisch',
    'ZH' => 'Chinesisch',
    'PT' => 'Portugiesisch',
    'PL' => 'Polnisch',
    'NL' => 'Niederländisch'
];

// --- ChoiceTypes ---
$choice_types = [
    0 => "Intervallskala (Schieberegler 0-100)",
    1 => "Dual: Wahr / Falsch",
    2 => "Dual: Stimme voll zu / Stimme nicht zu",
    3 => "3-stufige Likert-Skala",
    4 => "4-stufige Likert-Skala",
    5 => "5-stufige Likert-Skala",
    6 => "6-stufige Likert-Skala",
    7 => "7-stufige Likert-Skala"
];

// --- Variablen initialisieren ---
$editing = false;
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
$questionnaire = null;
$items = [];
$has_results = false;
$feedback = null;

// --- Laden bei Bearbeitung ---
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $questionnaire = $stmt->fetch();
    if ($questionnaire) {
        $editing = true;
        // Items laden
        $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
        $stmt->execute([$qid]);
        $items = $stmt->fetchAll();
        // Prüfen ob schon Ergebnisse vorhanden sind
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM results WHERE questionnaire_id = ?");
        $stmt->execute([$qid]);
        $has_results = $stmt->fetchColumn() > 0;
    }
}

// --- Formularverarbeitung ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pflichtfelder prüfen (serverseitig!)
    $name = trim($_POST['name'] ?? '');
    $short = trim($_POST['short'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $language = strtoupper(trim($_POST['language'] ?? ''));
    $choice_type = $_POST['choice_type'] ?? null;
    $copyright = isset($_POST['copyright']);
    $item_texts = $_POST['item'] ?? [];
    $item_scales = $_POST['scale'] ?? [];
    $item_negs = $_POST['negated'] ?? [];
    $all_items_valid = true;

    $errors = [];
    if (!$name) $errors[] = "Bitte einen Namen für den Fragebogen angeben.";
    if (!$description) $errors[] = "Bitte eine Beschreibung eingeben.";
    if (!isset($langs[$language])) $errors[] = "Bitte eine gültige Sprache auswählen.";
    if (!isset($choice_types[$choice_type])) $errors[] = "Bitte einen gültigen Skalentyp auswählen.";
    if (!$copyright) $errors[] = "Bitte das Copyright bestätigen.";
    // Mindestens ein Item und alle Pflichtfelder je Item prüfen
    $clean_items = [];
    foreach ($item_texts as $i => $text) {
        $text = trim($text);
        $scale = trim($item_scales[$i] ?? '');
        $neg = isset($item_negs[$i]) ? 1 : 0;
        if ($text) {
            $clean_items[] = [
                'item' => $text,
                'scale' => $scale,
                'negated' => $neg
            ];
        }
    }
    if (count($clean_items) == 0) $errors[] = "Mindestens ein Item muss eingetragen werden.";

    // Prüfung auf Duplikate in Items (optional)
    $item_texts_only = array_map(fn($it) => mb_strtolower($it['item']), $clean_items);
    if (count($item_texts_only) !== count(array_unique($item_texts_only))) {
        $errors[] = "Es sind doppelte Items vorhanden.";
    }

    if (empty($errors)) {
        // --- Speichern ---
        if ($editing && $questionnaire) {
            // BEARBEITEN
            if ($has_results) {
                // Nur Meta ändern (Name, Beschreibung, Sprache, short)
                $stmt = $pdo->prepare("UPDATE questionnaires SET name=?, short=?, language=?, description=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$name, $short, $language, $description, $qid]);
                $feedback = ['type' => 'success', 'msg' => "Die Metadaten des Fragebogens wurden aktualisiert.<br>Items können nicht mehr geändert werden, da bereits Ergebnisse vorliegen."];
            } else {
                // Alles ändern, Items ersetzen
                $stmt = $pdo->prepare("UPDATE questionnaires SET name=?, short=?, language=?, choice_type=?, description=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$name, $short, $language, $choice_type, $description, $qid]);
                // Alte Items löschen
                $stmt = $pdo->prepare("DELETE FROM items WHERE questionnaire_id=?");
                $stmt->execute([$qid]);
                // Neue Items speichern
                $stmt = $pdo->prepare("INSERT INTO items (questionnaire_id, item, negated, scale, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                foreach ($clean_items as $it) {
                    $stmt->execute([$qid, $it['item'], $it['negated'], $it['scale']]);
                }
                $feedback = ['type' => 'success', 'msg' => "Der Fragebogen wurde aktualisiert."];
            }
        } else {
            // NEU ANLEGEN
            $stmt = $pdo->prepare("INSERT INTO questionnaires (name, short, language, choice_type, description, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $short, $language, $choice_type, $description]);
            $new_qid = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO items (questionnaire_id, item, negated, scale, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            foreach ($clean_items as $it) {
                $stmt->execute([$new_qid, $it['item'], $it['negated'], $it['scale']]);
            }
            $feedback = ['type' => 'success', 'msg' => "Der Fragebogen wurde gespeichert. Du kannst direkt einen weiteren anlegen."];
            // Für neue leeres Formular zeigen
            $editing = false;
            $questionnaire = null;
            $items = [];
        }
        // Reload Daten nach Bearbeitung
        if ($editing && $questionnaire) {
            $stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
            $stmt->execute([$qid]);
            $items = $stmt->fetchAll();
        }
    } else {
        $feedback = ['type'=>'danger','msg'=>implode('<br>',$errors)];
    }
}

// Für JS: Items vorbereiten
if (empty($items)) $items = [['item'=>'','scale'=>'','negated'=>0]]; // mindestens eine Zeile

// Für Autocomplete: Skalenliste aus bisherigen Feldern sammeln
$all_scales = [];
foreach ($items as $it) {
    if ($it['scale'] !== '' && !in_array($it['scale'], $all_scales)) {
        $all_scales[] = $it['scale'];
    }
}

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editing ? "Fragebogen bearbeiten" : "Fragebogen erstellen" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.css" rel="stylesheet">
    <style>
        body { background: #f7f9fb; }
        .card { box-shadow: 0 6px 24px rgba(0,0,0,0.07);}
        .drag-handle { cursor:grab; color:#ccc;}
        .autocomplete-list { position:absolute; background:#fff; border:1px solid #ddd; z-index:10; max-height:170px; overflow:auto;}
        .autocomplete-item { padding: 0.3em 0.9em; cursor:pointer;}
        .autocomplete-item:hover { background:#f1f1f1;}
        .form-check-input[type=checkbox] { margin-top:0.4em;}
    </style>
</head>
<body>
<div class="container py-4" style="max-width:900px;">
    <h2 class="mb-3"><?= $editing ? "Fragebogen bearbeiten" : "Neuen Fragebogen erstellen" ?></h2>
    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" id="questionnaireForm">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-7 mb-3 mb-md-0">
                        <label class="form-label">Name des Fragebogens *</label>
                        <input type="text" class="form-control" name="name" required maxlength="255"
                               value="<?= htmlspecialchars($questionnaire['name'] ?? $_POST['name'] ?? '') ?>" <?= $has_results?'readonly':''; ?>>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Kürzel (optional)</label>
                        <input type="text" class="form-control" name="short" maxlength="50"
                               value="<?= htmlspecialchars($questionnaire['short'] ?? $_POST['short'] ?? '') ?>" <?= $has_results?'readonly':''; ?>>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label">Sprache *</label>
                        <select name="language" class="form-select" required <?= $has_results?'disabled':''; ?>>
                            <option value="">Bitte wählen</option>
                            <?php foreach ($langs as $code => $lang): ?>
                                <option value="<?= $code ?>" <?=
                                    ((($questionnaire['language'] ?? $_POST['language'] ?? '') == $code) ? 'selected' : '')
                                ?>><?= $lang ?> (<?= $code ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Antworttyp / Skala *</label>
                        <select name="choice_type" class="form-select" required <?= $has_results?'disabled':''; ?>>
                            <option value="">Bitte wählen</option>
                            <?php foreach ($choice_types as $val=>$text): ?>
                                <option value="<?= $val ?>" <?=
                                    ((($questionnaire['choice_type'] ?? $_POST['choice_type'] ?? '') == $val) ? 'selected' : '')
                                ?>><?= $text ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Beschreibung *</label>
                    <textarea class="form-control" name="description" required rows="2"><?= htmlspecialchars($questionnaire['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="card mb-4">
            <div class="card-body">
                <label class="form-label mb-2">Items <span class="text-muted">(Fragen/Statements)</span> *</label>
                <?php if ($has_results): ?>
                    <div class="alert alert-info">Die Items können nach dem ersten Ausfüllen nicht mehr geändert werden. Es sind bereits Ergebnisse vorhanden.</div>
                <?php endif; ?>
                <div id="itemsList">
                    <?php foreach ($items as $i=>$it): ?>
                        <div class="row align-items-center mb-2 item-row" draggable="true">
                            <div class="col-auto drag-handle pe-0">
                                <?php if (!$has_results): ?>
                                    <span class="fs-4">&#9776;</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-7 col-md-7">
                                <input type="text" class="form-control item-field" name="item[]" placeholder="Item-Text *"
                                       value="<?= htmlspecialchars($it['item']) ?>" <?= $has_results?'readonly':''; ?> required>
                            </div>
                            <div class="col-3 col-md-3 position-relative">
                                <input type="text" class="form-control scale-field" name="scale[]" placeholder="Subskala (optional)"
                                       value="<?= htmlspecialchars($it['scale']) ?>" <?= $has_results?'readonly':''; ?>>
                                <div class="autocomplete-list d-none"></div>
                            </div>
                            <div class="col-1 text-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="negated[<?= $i ?>]" value="1" <?= !empty($it['negated'])?'checked':''; ?> <?= $has_results?'disabled':''; ?>>
                                    <label class="form-check-label" title="Negativ gepolt">neg.</label>
                                </div>
                            </div>
                            <?php if (!$has_results): ?>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-link text-danger px-1 py-0 btn-remove-item" tabindex="-1" title="Item entfernen">&#10006;</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$has_results): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnAddItem">Weiteres Item hinzufügen</button>
                <?php endif; ?>
                <div class="mt-3 text-muted small" id="itemStats"></div>
            </div>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="copyright" name="copyright" required>
            <label class="form-check-label" for="copyright">
                Ich bestätige, dass ich der Eigentümer dieses Fragebogens bin und alle Rechte am Inhalt besitze.
            </label>
        </div>
        <button type="submit" class="btn btn-success"><?= $editing ? "Speichern" : "Fragebogen erstellen" ?></button>
        <a href="edit_questionnaire.php" class="btn btn-link">Neuen Fragebogen anlegen</a>
    </form>
</div>

<!-- Template für neue Itemzeile -->
<template id="itemRowTemplate">
    <div class="row align-items-center mb-2 item-row" draggable="true">
        <div class="col-auto drag-handle pe-0"><span class="fs-4">&#9776;</span></div>
        <div class="col-7 col-md-7">
            <input type="text" class="form-control item-field" name="item[]" placeholder="Item-Text *" required>
        </div>
        <div class="col-3 col-md-3 position-relative">
            <input type="text" class="form-control scale-field" name="scale[]" placeholder="Subskala (optional)">
            <div class="autocomplete-list d-none"></div>
        </div>
        <div class="col-1 text-center">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="negated[]" value="1">
                <label class="form-check-label" title="Negativ gepolt">neg.</label>
            </div>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-link text-danger px-1 py-0 btn-remove-item" tabindex="-1" title="Item entfernen">&#10006;</button>
        </div>
    </div>
</template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemsList = document.getElementById('itemsList');
    let template = document.getElementById('itemRowTemplate');
    let btnAdd = document.getElementById('btnAddItem');

    function updateItemStats() {
        let rows = itemsList.querySelectorAll('.item-row');
        let skalen = {};
        let nItems = 0;
        rows.forEach(row => {
            let item = row.querySelector('.item-field').value.trim();
            let scale = row.querySelector('.scale-field').value.trim();
            if(item) nItems++;
            if(scale) skalen[scale]=1;
        });
        let subskalen = Object.keys(skalen).filter(x=>x.length);
        let info = nItems + " Item" + (nItems !== 1 ? "s" : "");
        if(subskalen.length) {
            info += " | " + subskalen.length + " Subskala" + (subskalen.length !== 1 ? "en" : "") + ": " + subskalen.join(", ");
        }
        document.getElementById('itemStats').innerText = info;
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', function() {
            let clone = template.content.cloneNode(true);
            itemsList.appendChild(clone);
            updateItemStats();
        });
    }

    // Remove Item Button
    itemsList.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-item')) {
            let row = e.target.closest('.item-row');
            if (row) row.remove();
            updateItemStats();
        }
    });

    // Neue Zeile automatisch nach letzter Eingabe
    itemsList.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-field')) {
            let rows = itemsList.querySelectorAll('.item-row');
            let last = rows[rows.length-1];
            if (last && e.target === last.querySelector('.item-field') && last.querySelector('.item-field').value.trim().length > 0) {
                let clone = template.content.cloneNode(true);
                itemsList.appendChild(clone);
            }
            updateItemStats();
        }
        if (e.target.classList.contains('scale-field')) {
            updateItemStats();
        }
    });

    // Autocomplete für Skalenfeld
    itemsList.addEventListener('focusin', function(e) {
        if (e.target.classList.contains('scale-field')) {
            let field = e.target;
            let list = field.nextElementSibling;
            let options = Array.from(itemsList.querySelectorAll('.scale-field'))
                .map(f=>f.value.trim()).filter(v=>v.length>0 && v!==field.value);
            options = Array.from(new Set(options));
            if(options.length) {
                list.innerHTML = '';
                options.forEach(opt=>{
                    let div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerText = opt;
                    div.addEventListener('mousedown',function(){
                        field.value = opt;
                        list.classList.add('d-none');
                        updateItemStats();
                    });
                    list.appendChild(div);
                });
                list.classList.remove('d-none');
            }
        }
    });
    // Schließt Autocomplete wenn Feld verlässt
    document.addEventListener('click',function(e){
        document.querySelectorAll('.autocomplete-list').forEach(el=>{
            if(!el.contains(e.target)) el.classList.add('d-none');
        });
    });

    // Drag&Drop
    Sortable.create(itemsList, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'bg-light'
    });

    // Initial
    updateItemStats();
});
</script>
</body>
</html>
