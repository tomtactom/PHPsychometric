<?php
require_once 'include.inc.php';

// Fragebögen abrufen
$stmt = $pdo->query("SELECT id, name, short, language, description FROM questionnaires ORDER BY id ASC");
$questionnaires = $stmt->fetchAll();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fragebögen Übersicht</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8fafc;
        }
        .card {
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .card:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,0.10);
            transform: translateY(-2px) scale(1.02);
        }
        .card-title {
            font-size: 1.3rem;
        }
        .badge {
            font-size: 0.85em;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-light bg-light mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1">📝 Fragebögen</span>
    </div>
</nav>

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
