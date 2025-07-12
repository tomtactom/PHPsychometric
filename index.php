<?php
require_once 'include.inc.php';
$pageTitle       = 'Übersicht';
$pageDescription = 'Du möchtest Fragen stellen? Du möchtest Fragen beantworten? PHPsychometric macht\'s möglich!';
require_once 'navbar.inc.php';

// Aktuellen Nutzer ermitteln
$user_id = isset($_COOKIE['profile']) && ctype_digit($_COOKIE['profile'])
    ? intval($_COOKIE['profile'])
    : null;

// Bereits ausgefüllte Fragebögen laden
$filled = [];
if ($user_id) {
    $stmt2 = $pdo->prepare("SELECT DISTINCT questionnaire_id FROM results WHERE user_id = ?");
    $stmt2->execute([$user_id]);
    $filled = $stmt2->fetchAll(PDO::FETCH_COLUMN);
}

// Alle Fragebögen abrufen
$stmt = $pdo->query("SELECT id, name, short, language, description FROM questionnaires ORDER BY id ASC");
$questionnaires = $stmt->fetchAll();
?>
<section class="hero">
  <div class="container">
    <h1>Willkommen bei PHPsychometric</h1>
    <p>Erstelle &amp; beantworte psychometrische Fragebögen – anonym und interaktiv.</p>
    <a href="#browse" class="btn btn-lg btn-light mt-3">Fragebögen entdecken</a>
  </div>
</section>

<section id="browse" class="container">
  <div class="search-bar">
    <input type="text" id="filterInput" class="form-control form-control-lg" placeholder="Fragebogen suchen…">
  </div>

  <div class="row g-4 justify-content-center">
    <?php if (empty($questionnaires)): ?>
      <div class="col-12 text-center text-muted py-5">
        Zurzeit sind keine Fragebögen verfügbar.
      </div>
    <?php else: foreach ($questionnaires as $q): ?>
      <div class="col-12 col-md-6 col-lg-4 card-hover">
        <div class="card shadow-sm h-100 card-tilt">
          <div class="card-body d-flex flex-column">
            <!-- Vorderseite -->
            <div class="card-front">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0"><?= htmlspecialchars($q['name']) ?></h5>
                <span class="badge bg-primary"><?= strtoupper(htmlspecialchars($q['language'])) ?></span>
              </div>
              <?php if ($q['short']): ?>
                <p class="text-muted small"><?= htmlspecialchars($q['short']) ?></p>
              <?php endif; ?>

              <?php if ($user_id && in_array($q['id'], $filled)): ?>
                <a href="results.php?id=<?= urlencode($q['id']) ?>"
                   class="btn btn-outline-success result-btn"
                   title="Ergebnis ansehen">
                  Ergebnis
                </a>
              <?php endif; ?>

              <p class="mt-auto">
                <a href="q.php?id=<?= urlencode($q['id']) ?>"
                   class="stretched-link text-decoration-none fw-bold">
                  Jetzt starten →
                </a>
              </p>
            </div>

            <!-- Rückseite -->
            <div class="card-back">
              <h6>Beschreibung</h6>
              <p class="small"><?= nl2br(htmlspecialchars($q['description'])) ?></p>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</section>

<script>
  // Live-Filter
  document.getElementById('filterInput').addEventListener('input', function(){
    const term = this.value.toLowerCase();
    document.querySelectorAll('.card-hover').forEach(card => {
      const title = card.querySelector('.card-title').textContent.toLowerCase();
      card.style.display = title.includes(term) ? '' : 'none';
    });
  });
</script>

<?php include 'footer.inc.php'; ?>
