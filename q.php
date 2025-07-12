<?php
// q.php – Fragebogen-Seite

require_once 'include.inc.php';
$pageTitle       = 'Fragebogen';
$pageDescription = 'Du möchtest Fragen stellen? Du möchtest Fragen beantworten? PHPsychometric macht\'s möglich!';
require_once 'navbar.inc.php';

// Hilfsfunktion: user_id aus Cookie
function getUserIdFromCookie() {
    if (!empty($_COOKIE['profile']) && ctype_digit($_COOKIE['profile'])) {
        return intval($_COOKIE['profile']);
    }
    return false;
}

// 1) Fragebogen-ID prüfen und laden
$qid = isset($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : null;
if (!$qid) {
    http_response_code(400);
    echo '<div class="alert alert-danger m-5">Ungültige Anfrage.</div>';
    require 'footer.inc.php';
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM questionnaires WHERE id = ?");
$stmt->execute([$qid]);
$fragebogen = $stmt->fetch();
if (!$fragebogen) {
    http_response_code(404);
    echo '<div class="alert alert-danger m-5">Fragebogen nicht gefunden.</div>';
    require 'footer.inc.php';
    exit;
}

// 2) Benutzer prüfen bzw. Profil-Formular ausgeben
$user_id = getUserIdFromCookie();
$user = null;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

if (!$user) {
    // Profil anlegen
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_submit'])) {
        $fields = ['gender','birth_year','birth_month','degree','marital_status','income','german_knowledge','english_knowledge'];
        $data = [];
        $errors = [];
        foreach ($fields as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
        }
        if (!in_array($data['gender'], ['0','1','2'], true)) $errors[] = "Bitte Geschlecht auswählen.";
        if (!ctype_digit($data['birth_year']) || $data['birth_year'] < 1900 || $data['birth_year'] > date('Y')-5)
            $errors[] = "Bitte gültiges Geburtsjahr angeben.";
        if (!ctype_digit($data['birth_month']) || $data['birth_month']<1 || $data['birth_month']>12)
            $errors[] = "Bitte gültigen Geburtsmonat wählen.";
        foreach (['degree','marital_status','income','german_knowledge','english_knowledge'] as $f) {
            if (!ctype_digit($data[$f] ?? '')) $errors[] = "Bitte alle Felder ausfüllen.";
        }
        if (!$errors) {
            $stmt = $pdo->prepare("
                INSERT INTO users
                  (gender,birth_year,birth_month,degree,marital_status,income,german_knowledge,english_knowledge,created_at,updated_at)
                VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())
            ");
            $stmt->execute([
                $data['gender'],$data['birth_year'],$data['birth_month'],
                $data['degree'],$data['marital_status'],$data['income'],
                $data['german_knowledge'],$data['english_knowledge']
            ]);
            $newId = $pdo->lastInsertId();
            setcookie('profile', $newId, [
                'expires'=>time()+10*365*24*3600,
                'path'=>'/','samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])
            ]);
            header("Location: q.php?id={$qid}");
            exit;
        }
    }
    // Profil-Formular ausgeben
    ?>
    <div class="container my-5">
      <div class="card mx-auto shadow" style="max-width:480px;">
        <div class="card-body">
          <h4 class="card-title mb-3">Kurzprofil ausfüllen</h4>
          <p class="text-muted small mb-3">
            Deine Angaben sind <strong>vollständig anonym</strong> und helfen, bessere Auswertungen zu ermöglichen.
          </p>
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
          <?php endif; ?>
          <form method="post" novalidate>
            <!-- Geschlecht -->
            <div class="mb-3">
              <label class="form-label">Geschlecht</label>
              <select name="gender" class="form-select" required>
                <option value="">Bitte wählen</option>
                <option value="0" <?= ($data['gender']??'')==='0'?'selected':'' ?>>Männlich</option>
                <option value="1" <?= ($data['gender']??'')==='1'?'selected':'' ?>>Weiblich</option>
                <option value="2" <?= ($data['gender']??'')==='2'?'selected':'' ?>>Divers</option>
              </select>
            </div>
            <!-- Geburtsjahr -->
            <div class="mb-3">
              <label class="form-label">Geburtsjahr</label>
              <input type="text" name="birth_year" class="form-control" maxlength="4" required
                     value="<?= htmlspecialchars($data['birth_year'] ?? '') ?>">
            </div>
            <!-- Geburtsmonat -->
            <div class="mb-3">
              <label class="form-label">Geburtsmonat</label>
              <select name="birth_month" class="form-select" required>
                <option value="">Bitte wählen</option>
                <?php for($m=1;$m<=12;$m++): ?>
                  <option value="<?= $m ?>" <?= ($data['birth_month']??'')==$m?'selected':''; ?>><?= $m ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <!-- Abschluss -->
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
                <option value="6">Studium (Bachelor/Master/Diplom)</option>
                <option value="7">Promotion (Doktorgrad)</option>
                <option value="8">Sonstiger Abschluss</option>
              </select>
            </div>
            <!-- Familienstand -->
            <div class="mb-3">
              <label class="form-label">Familienstand</label>
              <select name="marital_status" class="form-select" required>
                <option value="">Bitte wählen</option>
                <option value="0">ledig</option>
                <option value="1">verheiratet/eingetr. LP, zusammenlebend</option>
                <option value="2">verheiratet/eingetr. LP, getrennt</option>
                <option value="3">geschieden/LP aufgehoben</option>
                <option value="4">verwitwet/LP verstorben</option>
              </select>
            </div>
            <!-- Einkommen -->
            <div class="mb-3">
              <label class="form-label">Einkommen (monatlich, netto)</label>
              <select name="income" class="form-select" required>
                <option value="">Bitte wählen</option>
                <?php
                $incOpts = [
                  "1"=>"unter 500 €","2"=>"500–750 €","3"=>"750–1 000 €",
                  "4"=>"1 000–1 250 €","5"=>"1 250–1 500 €","6"=>"1 500–1 750 €",
                  "7"=>"1 750–2 000 €","8"=>"2 000–2 250 €","9"=>"2 250–2 500 €",
                  "10"=>"2 500–2 750 €","11"=>"2 750–3 000 €","12"=>"3 000–3 250 €",
                  "13"=>"3 250–3 500 €","14"=>"3 500–4 000 €","15"=>"4 000–4 500 €",
                  "16"=>"4 500–5 000 €","17"=>"5 000–6 000 €","18"=>"6 000–7 000 €",
                  "19"=>"7 000–8 000 €","20"=>"8 000–10 000 €","21"=>"10 000–15 000 €",
                  "22"=>"15 000–25 000 €","23"=>"25 000 €+"];
                foreach($incOpts as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= ($data['income']??'')==$k?'selected':''; ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Deutschkenntnisse -->
            <div class="mb-3">
              <label class="form-label">Deutschkenntnisse</label>
              <select name="german_knowledge" class="form-select" required>
                <option value="">Bitte wählen</option>
                <option value="0">A1 – Anfänger</option>
                <option value="1">A2 – Grundkenntnisse</option>
                <option value="2">B1 – Fortgeschrittene</option>
                <option value="3">B2 – Selbstständige</option>
                <option value="4">C1 – Fachkundige</option>
                <option value="5">C2 – Fast muttersprachlich</option>
                <option value="6">Muttersprachler</option>
              </select>
            </div>
            <!-- Englischkenntnisse -->
            <div class="mb-3">
              <label class="form-label">Englischkenntnisse</label>
              <select name="english_knowledge" class="form-select" required>
                <option value="">Bitte wählen</option>
                <option value="0">A1 – Anfänger</option>
                <option value="1">A2 – Grundkenntnisse</option>
                <option value="2">B1 – Fortgeschrittene</option>
                <option value="3">B2 – Selbstständige</option>
                <option value="4">C1 – Fachkundige</option>
                <option value="5">C2 – Fast muttersprachlich</option>
                <option value="6">Muttersprachler</option>
              </select>
            </div>
            <div class="form-text text-muted mb-3">
              Deine Angaben werden anonymisiert gespeichert und nur für statistische Zwecke genutzt.
            </div>
            <button type="submit" name="profile_submit" class="btn btn-primary w-100">
              Weiter zum Fragebogen
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php
    require 'footer.inc.php';
    exit;
}

// 3) Items laden
$stmt = $pdo->prepare("SELECT * FROM items WHERE questionnaire_id = ? ORDER BY id ASC");
$stmt->execute([$qid]);
$items = $stmt->fetchAll();

// 4) Antworten speichern
$feedback = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['questionnaire_submit'])) {
    $antworten = [];
    foreach($items as $item) {
        $fn = 'item_'.$item['id'];
        if (!isset($_POST[$fn]) || $_POST[$fn]==='') {
            $feedback = ['type'=>'danger','msg'=>'Bitte alle Fragen beantworten.'];
            break;
        }
        $antworten[$item['id']] = $_POST[$fn];
    }
    if (!$feedback) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("
                INSERT INTO results
                  (questionnaire_id,item_id,user_id,result,created_at,updated_at)
                VALUES(?,?,?,?,NOW(),NOW())
            ");
            foreach($antworten as $iid=>$val) {
                $ins->execute([$qid,$iid,$user_id,$val]);
            }
            $pdo->commit();
            header("Location: results.php?id={$qid}");
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $feedback = ['type'=>'danger','msg'=>'Fehler beim Speichern, bitte erneut versuchen.'];
        }
    }
}

// 5) Renderer für Eingaben
function renderItemInput($choice, $name) {
    $ct = intval($choice);
    switch($ct) {
        case 0:
            return '<input type="range" name="'.$name.'" class="form-range" min="0" max="100" required>';
        case 1:
            return '
              <div class="form-check">
                <input type="radio" class="form-check-input" name="'.$name.'" value="1" required>
                <label class="form-check-label">Wahr</label>
              </div>
              <div class="form-check">
                <input type="radio" class="form-check-input" name="'.$name.'" value="0" required>
                <label class="form-check-label">Falsch</label>
              </div>';
        case 2:
            return '
              <div class="form-check">
                <input type="radio" class="form-check-input" name="'.$name.'" value="1" required>
                <label class="form-check-label">Stimme voll zu</label>
              </div>
              <div class="form-check">
                <input type="radio" class="form-check-input" name="'.$name.'" value="0" required>
                <label class="form-check-label">Stimme nicht zu</label>
              </div>';
        default:
            $steps = $ct+1;
            $out = '<div class="btn-group w-100 flex-nowrap overflow-auto" role="group">';
            for($i=0;$i<$steps;$i++){
                $lab = '';
                if($i===0) $lab='Stimme gar nicht zu';
                if($i===$steps-1) $lab='Stimme voll zu';
                $out .= '<input type="radio" class="btn-check" name="'.$name.'" id="'.$name.'_'.$i.'" value="'.$i.'" required>';
                $out .= '<label class="btn btn-outline-primary" for="'.$name.'_'.$i.'"'.($lab?' title="'.$lab.'"':'').'>'.($i+1).'</label>';
            }
            $out .= '</div>';
            return $out;
    }
}
?>

<div class="container my-4" style="max-width:900px;">
  <h3><?= htmlspecialchars($fragebogen['name']) ?></h3>
  <p class="text-muted"><?= nl2br(htmlspecialchars($fragebogen['description'])) ?></p>
  <?php if($feedback): ?>
    <div class="alert alert-<?= $feedback['type'] ?>"><?= $feedback['msg'] ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <?php foreach($items as $item): ?>
      <div class="mb-4">
        <label class="form-label"><?= htmlspecialchars($item['item']) ?></label>
        <?= renderItemInput($fragebogen['choice_type'], 'item_'.$item['id']) ?>
      </div>
    <?php endforeach; ?>
    <button type="submit" name="questionnaire_submit" class="btn btn-success">Abschicken</button>
  </form>
</div>

<?php require 'footer.inc.php'; ?>
