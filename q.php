<?php
require_once 'include.inc.php';
$pageTitle       = 'Übersicht';
$pageDescription = 'Du möchtest Fragen stellen? Du möchtest Fragen beantworten? PHPsychometric macht\'s möglich!';
require_once 'navbar.inc.php';

// Hilfsfunktion: Gibt user_id aus Cookie oder false zurück
function getUserIdFromCookie() {
    if (!empty($_COOKIE['profile']) && ctype_digit($_COOKIE['profile'])) {
        return intval($_COOKIE['profile']);
    }
    return false;
}

// Fragebogen-ID prüfen
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;

// Fragebogen auslesen
$fragebogen = null;
if ($qid) {
    $stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
    $stmt->execute([$qid]);
    $fragebogen = $stmt->fetch();
}

// Fehler wenn ungültig
if (!$fragebogen) {
    http_response_code(404);
    die('<div class="alert alert-danger m-5">Fragebogen nicht gefunden.</div>');
}

// Nutzer prüfen/ermitteln
$user_id = getUserIdFromCookie();
$user = null;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Wenn Nutzer nicht existiert oder Cookie fehlt: Formular anzeigen
if (!$user) {
    // Profilformular absenden
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_submit'])) {
        // Alle Felder einlesen und validieren
        $fields = ['gender','birth_year','birth_month','degree','marital_status','income','german_knowledge','english_knowledge'];
        $data = [];
        $errors = [];
        foreach ($fields as $f) {
            $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : '';
        }
        // Einfache Validation
        if (!in_array($data['gender'], ['0','1','2'], true)) $errors[] = "Bitte Geschlecht auswählen.";
        if (!ctype_digit($data['birth_year']) || $data['birth_year'] < 1900 || $data['birth_year'] > (date('Y')-5)) $errors[] = "Bitte gültiges Geburtsjahr angeben.";
        if (!ctype_digit($data['birth_month']) || $data['birth_month'] < 1 || $data['birth_month'] > 12) $errors[] = "Bitte gültigen Geburtsmonat wählen.";
        foreach (['degree','marital_status','income','german_knowledge','english_knowledge'] as $f) {
            if (!ctype_digit($data[$f])) $errors[] = "Bitte alle Felder ausfüllen.";
        }
        if (!$errors) {
            // In DB speichern
            $stmt = $pdo->prepare("INSERT INTO users
                (gender, birth_year, birth_month, degree, marital_status, income, german_knowledge, english_knowledge, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $data['gender'], $data['birth_year'], $data['birth_month'],
                $data['degree'], $data['marital_status'], $data['income'],
                $data['german_knowledge'], $data['english_knowledge']
            ]);
            $new_id = $pdo->lastInsertId();
            // Cookie setzen (10 Jahre gültig)
            setcookie("profile", $new_id, [
                'expires' => time() + 60*60*24*365*10,
                'path' => '/',
                'samesite' => 'Lax',
                'secure' => isset($_SERVER['HTTPS'])
            ]);
            // Seite neuladen (jetzt ist Nutzer da)
            header("Location: q.php?id=".$qid);
            exit;
        }
    }
    // Formular anzeigen und beenden
    ?>

    <div class="container my-5">
        <div class="card mx-auto shadow" style="max-width: 480px;">
            <div class="card-body">
                <h4 class="card-title mb-3">Kurzprofil ausfüllen</h4>
                <p class="mb-3 text-muted small">
                    Deine Angaben sind <strong>vollständig anonym</strong> und helfen, bessere Ergebnisse zu berechnen. Ohne die Angaben kann leider keine Auswertung erfolgen.
                </p>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Geschlecht</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <option value="0" <?= (isset($data['gender']) && $data['gender']=='0')?'selected':''; ?>>Männlich</option>
                            <option value="1" <?= (isset($data['gender']) && $data['gender']=='1')?'selected':''; ?>>Weiblich</option>
                            <option value="2" <?= (isset($data['gender']) && $data['gender']=='2')?'selected':''; ?>>Divers</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Geburtsjahr</label>
                        <input type="text" name="birth_year" class="form-control" maxlength="4" required value="<?= htmlspecialchars($data['birth_year'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Geburtsmonat</label>
                        <select name="birth_month" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <?php for ($m=1;$m<=12;$m++): ?>
                                <option value="<?= $m ?>" <?= (isset($data['birth_month']) && $data['birth_month']==$m)?'selected':''; ?>><?= $m ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bildungsabschluss</label>
                        <select name="degree" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <option value="0">Keine Angabe</option>
                            <option value="1">Kein Schulabschluss</option>
                            <option value="2">Hauptschulabschluss</option>
                            <option value="3">Mittlere Reife / Realschulabschluss</option>
                            <option value="4">Allgemeine Hochschulreife (Abitur)</option>
                            <option value="5">Berufsausbildung (mit Abschluss)</option>
                            <option value="6">Studium (Bachelor / Master / Diplom)</option>
                            <option value="7">Promotion (Doktorgrad)</option>
                            <option value="8">Sonstiger Abschluss</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Familienstand</label>
                        <select name="marital_status" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <option value="0">ledig, war noch nie verheiratet</option>
                            <option value="1">verheiratet oder Eingetragene Lebenspartnerschaft, zusammenlebend</option>
                            <option value="2">verheiratet oder Eingetragene Lebenspartnerschaft, aber in Trennung lebend</option>
                            <option value="3">geschieden, Eingetragene Lebenspartnerschaft aufgehoben</option>
                            <option value="4">verwitwet, Eingetragene*r Lebenspartner*in verstorben</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Einkommen (monatlich, netto)</label>
                        <select name="income" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <?php
                            $income_options = [
                                "1" => "unter 500 €",
                                "2" => "500 bis unter 750 €",
                                "3" => "750 bis unter 1.000 €",
                                "4" => "1.000 bis unter 1.250 €",
                                "5" => "1.250 bis unter 1.500 €",
                                "6" => "1.500 bis unter 1.750 €",
                                "7" => "1.750 bis unter 2.000 €",
                                "8" => "2.000 bis unter 2.250 €",
                                "9" => "2.250 bis unter 2.500 €",
                                "10" => "2.500 bis unter 2.750 €",
                                "11" => "2.750 bis unter 3.000 €",
                                "12" => "3.000 bis unter 3.250 €",
                                "13" => "3.250 bis unter 3.500 €",
                                "14" => "3.500 bis unter 4.000 €",
                                "15" => "4.000 bis unter 4.500 €",
                                "16" => "4.500 bis unter 5.000 €",
                                "17" => "5.000 bis unter 6.000 €",
                                "18" => "6.000 bis unter 7.000 €",
                                "19" => "7.000 bis unter 8.000 €",
                                "20" => "8.000 bis unter 10.000 €",
                                "21" => "10.000 bis unter 15.000 €",
                                "22" => "15.000 bis unter 25.000 €",
                                "23" => "25.000 € oder mehr"
                            ];
                            foreach ($income_options as $k=>$v):
                                ?>
                                <option value="<?= $k ?>" <?= (isset($data['income']) && $data['income']==$k)?'selected':''; ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deutschkenntnisse</label>
                        <select name="german_knowledge" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <option value="0">A1 — Anfänger</option>
                            <option value="1">A2 — Grundlegende Kenntnisse</option>
                            <option value="2">B1 — Fortgeschrittene Sprachverwendung</option>
                            <option value="3">B2 — Selbstständige Sprachverwendung</option>
                            <option value="4">C1 — Fachkundige Sprachkenntnisse</option>
                            <option value="5">C2 — Annähernd muttersprachliche Kenntnisse</option>
                            <option value="6">Muttersprachler*in</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Englischkenntnisse</label>
                        <select name="english_knowledge" class="form-select" required>
                            <option value="">Bitte wählen</option>
                            <option value="0">A1 — Anfänger</option>
                            <option value="1">A2 — Grundlegende Kenntnisse</option>
                            <option value="2">B1 — Fortgeschrittene Sprachverwendung</option>
                            <option value="3">B2 — Selbstständige Sprachverwendung</option>
                            <option value="4">C1 — Fachkundige Sprachkenntnisse</option>
                            <option value="5">C2 — Annähernd muttersprachliche Kenntnisse</option>
                            <option value="6">Muttersprachler*in</option>
                        </select>
                    </div>
                    <div class="form-text mb-3 text-muted">
                        Deine Angaben werden nur anonymisiert gespeichert und ausschließlich für statistische Zwecke verwendet.
                    </div>
                    <button type="submit" name="profile_submit" class="btn btn-primary w-100">Weiter zum Fragebogen</button>
                </form>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Items abrufen
$stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
$stmt->execute([$fragebogen['id']]);
$items = $stmt->fetchAll();

// Bei Submit: Antworten speichern
$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['questionnaire_submit'])) {
    $antworten = [];
    foreach ($items as $item) {
        $fieldname = 'item_' . $item['id'];
        if (!isset($_POST[$fieldname]) || $_POST[$fieldname]==='') {
            $feedback = ['type'=>'danger', 'msg'=>'Bitte alle Fragen beantworten.'];
            break;
        }
        $antworten[$item['id']] = $_POST[$fieldname];
    }
    // Nur speichern, wenn alles vollständig
    if (!$feedback) {
        try {
            $pdo->beginTransaction();
            foreach ($antworten as $item_id => $value) {
                $stmt = $pdo->prepare("INSERT INTO results (questionnaire_id, item_id, user_id, result, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$fragebogen['id'], $item_id, $user['id'], $value]);
            }
            $pdo->commit();
            header('Location: results.php?id='.$fragebogen['id']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $feedback = ['type'=>'danger', 'msg'=>'Fehler beim Speichern. Bitte versuche es erneut.'];
        }
    }
}

// Hilfsfunktion: Skalen-HTML erzeugen (Likert, Slider, Dual, etc.)
// Verwendet jetzt ausschließlich den choicetype des Fragebogens!
// Für Likert: Nur die beiden Extreme beschriften, responsive!
function renderItemInput($item, $name, $choicetype) {
    $type = intval($choicetype);
    switch ($type) {
        case 0: // Intervallskala (Slider: 0-100)
            return '<input type="range" min="0" max="100" step="1" class="form-range" name="'.$name.'" required>
            <div class="d-flex justify-content-between"><small>0</small><small>100</small></div>';
        case 1: // Dual (Wahr/Falsch)
            return '
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="'.$name.'" id="'.$name.'_1" value="1" required>
                  <label class="form-check-label" for="'.$name.'_1">Wahr</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="'.$name.'" id="'.$name.'_0" value="0" required>
                  <label class="form-check-label" for="'.$name.'_0">Falsch</label>
                </div>
            ';
        case 2: // Dual (Stimme voll zu / Stimme nicht zu)
            return '
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="'.$name.'" id="'.$name.'_1" value="1" required>
                  <label class="form-check-label" for="'.$name.'_1">Stimme voll zu</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="'.$name.'" id="'.$name.'_0" value="0" required>
                  <label class="form-check-label" for="'.$name.'_0">Stimme nicht zu</label>
                </div>
            ';
        case 3: case 4: case 5: case 6: case 7: // Likert-Skalen (3-7-stufig)
            $count = $type + 1;
            $labels = [
                0 => 'Stimme gar nicht zu',
                ($count-1) => 'Stimme voll zu'
            ];
            // Responsive Button-Gruppe (horizontal scrollbar auf kleinen Geräten)
            $out = '<div class="btn-group w-100 d-flex flex-nowrap flex-sm-wrap overflow-auto" role="group" style="min-width:210px;">';
            for ($i=0; $i<$count; $i++) {
                $label = $labels[$i] ?? '';
                $out .= '<input type="radio" class="btn-check" name="'.$name.'" id="'.$name.'_'.$i.'" value="'.$i.'" required>';
                $out .= '<label class="btn btn-outline-primary flex-fill px-1 px-sm-2" for="'.$name.'_'.$i.'" style="font-size:0.95em; min-width:38px;white-space:normal;">'.htmlspecialchars($label).'</label>';
            }
            $out .= '</div>';
            return $out;
        default: // Textfeld (Fallback)
            return '<input type="text" class="form-control" name="'.$name.'" required>';
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($fragebogen['name']) ?> | Fragebogen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .frage-item { margin-bottom:2.5rem;}
        .frage-label { font-weight:500;}
        @media (max-width: 600px) {
            .btn-group label.btn {
                font-size: 0.87em !important;
                padding-left: 0.15rem !important;
                padding-right: 0.15rem !important;
            }
        }
    </style>
</head>
<body>
<div class="container my-4" style="max-width:900px;">
    <h3 class="mb-3"><?= htmlspecialchars($fragebogen['name']) ?></h3>
    <p class="mb-4 text-muted"><?= nl2br(htmlspecialchars($fragebogen['description'])) ?></p>
    <?php if ($feedback): ?>
        <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <?php foreach ($items as $item): ?>
            <div class="frage-item">
                <div class="frage-label mb-2"><?= nl2br(htmlspecialchars($item['item'])) ?></div>
                <?= renderItemInput($item, 'item_'.$item['id'], $fragebogen['choice_type']) ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" name="questionnaire_submit" class="btn btn-success px-4">Abschicken</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include('footer.inc.php'); ?>
