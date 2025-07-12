<?php
require_once 'include.inc.php';
$pageTitle       = 'Übersicht';
$pageDescription = 'Du möchtest Fragen stellen? Du möchtest Fragen beantworten? PHPsychometric macht\'s möglich!';
require_once 'navbar.inc.php';

// Aktuellen Nutzer ermitteln (Cookie "profile")
$user_id = (isset($_COOKIE['profile']) && ctype_digit($_COOKIE['profile']))
    ? intval($_COOKIE['profile'])
    : null;

// Bereits ausgefüllte Fragebögen für "Ergebnis"-Button
$filled = [];
if ($user_id) {
    $stmt2 = $pdo->prepare("SELECT DISTINCT questionnaire_id FROM results WHERE user_id = ?");
    $stmt2->execute([$user_id]);
    $filled = $stmt2->fetchAll(PDO::FETCH_COLUMN);
}

// Alle Fragebögen abrufen
$stmt = $pdo->query("SELECT id, name, short, language, description
                     FROM questionnaires
                     ORDER BY id ASC");
$questionnaires = $stmt->fetchAll();
?>
<!-- Hero-Bereich -->
<section class="hero">
  <div class="container">
    <h1>Willkommen bei PHPsychometric</h1>
    <p>Erstelle &amp; beantworte psychometrische Fragebögen – anonym und interaktiv.</p>
    <a href="#browse" class="btn btn-lg btn-light mt-3">Fragebögen entdecken</a>
    <a href="./edit_questionnaire.php" class="btn btn-lg btn-light mt-3">Entdeckungen teilen</a>
  </div>
</section>

<!-- Suchleiste + Kartenübersicht -->
<section id="browse" class="container my-5">
  <div class="search-bar mb-4">
    <input type="text" id="filterInput" class="form-control form-control-lg" placeholder="Fragebogen suchen…">
  </div>

  <div class="index-cards">
    <?php if (empty($questionnaires)): ?>
      <div class="text-center text-muted py-5">
        Zurzeit sind keine Fragebögen verfügbar.
      </div>
    <?php else: foreach ($questionnaires as $q): ?>
      <div class="index-card">
        <div class="index-card-header">
          <h5 class="index-card-title"><?= htmlspecialchars($q['name']) ?></h5>
          <span class="badge bg-primary"><?= strtoupper(htmlspecialchars($q['language'])) ?></span>
        </div>
        <div class="index-card-body">
          <?php if ($q['short']): ?>
            <p class="index-card-short"><?= htmlspecialchars($q['short']) ?></p>
          <?php endif; ?>
          <p class="index-card-desc"><?= htmlspecialchars($q['description']) ?></p>
        </div>
        <div class="index-card-footer">
          <?php if ($user_id && in_array($q['id'], $filled)): ?>
            <a href="results.php?id=<?= $q['id'] ?>" class="btn btn-outline-success index-btn">
              Ergebnis
            </a>
          <?php endif; ?>
          <a href="q.php?id=<?= $q['id'] ?>" class="btn btn-primary index-btn">
            Jetzt starten →
          </a>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</section>

<?php include 'footer.inc.php'; ?>

<script>
// Live-Filter für die Karten
document.getElementById('filterInput').addEventListener('input', function(){
  const term = this.value.toLowerCase();
  document.querySelectorAll('.index-card').forEach(card => {
    const title = card.querySelector('.index-card-title').textContent.toLowerCase();
    card.style.display = title.includes(term) ? '' : 'none';
  });
});
</script>
