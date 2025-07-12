<?php
require_once 'include.inc.php';
$pageTitle       = 'Übersicht';
$pageDescription = 'Du möchtest Fragen stellen? Du möchtest Fragen beantworten? PHPsychometric macht\'s möglich!';
require_once 'navbar.inc.php';

// Fragebögen abrufen
$stmt = $pdo->query("SELECT id, name, short, language, description FROM questionnaires ORDER BY id ASC");
$questionnaires = $stmt->fetchAll();
?>
<div class="container">
    <h2 class="mb-4 text-center">Alle verfügbaren Fragebögen</h2>
    <div class="row g-4 justify-content-center">
        <?php if (!$questionnaires): ?>
            <div class="col-12 text-center text-muted">Zurzeit sind keine Fragebögen verfügbar.</div>
        <?php else: ?>
            <?php foreach ($questionnaires as $q): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0"><?= htmlspecialchars($q['name']) ?></h5>
                                <span class="badge bg-secondary"><?= strtoupper(htmlspecialchars($q['language'])) ?></span>
                            </div>
                            <?php if ($q['short']): ?>
                                <p class="card-subtitle text-muted"><?= htmlspecialchars($q['short']) ?></p>
                            <?php endif; ?>
                            <p class="card-text mt-2 mb-4"><?= nl2br(htmlspecialchars($q['description'])) ?></p>
                            <a href="q.php?id=<?= urlencode($q['id']) ?>" class="btn btn-primary mt-auto">Fragebogen öffnen</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
