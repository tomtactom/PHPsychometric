<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(SITE_TITLE . ' – ' . ($pageTitle ?? 'Startseite')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Grundlegende Meta-Tags -->
  <meta name="description" content="<?= htmlspecialchars($pageDescription ?? SEITENBESCHREIBUNG) ?>">
  <meta name="author" content="Tom Aschmann">
  <meta name="copyright" content="&copy; <?= date('Y') ?> PHPsychometric">
  <meta name="robots" content="<?= $robotsTag ?? 'index, follow' ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <meta http-equiv="Content-Language" content="de">

  <!-- Theme Colors -->
  <meta name="theme-color" content="#2E5BDA" id="meta-theme-color">
  <meta name="msapplication-TileColor" content="#2E5BDA">
  <meta name="msapplication-TileImage" content="assets/ms-icon-144x144.png">

  <!-- Apple WebApp -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PHPsychometric">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">

  <!-- Favicon & PWA Manifest -->
  <link rel="manifest" href="assets/manifest.json">
  <link rel="apple-touch-icon" sizes="57x57"   href="assets/apple-icon-57x57.png">
  <link rel="apple-touch-icon" sizes="60x60"   href="assets/apple-icon-60x60.png">
  <link rel="apple-touch-icon" sizes="72x72"   href="assets/apple-icon-72x72.png">
  <link rel="apple-touch-icon" sizes="76x76"   href="assets/apple-icon-76x76.png">
  <link rel="apple-touch-icon" sizes="114x114" href="assets/apple-icon-114x114.png">
  <link rel="apple-touch-icon" sizes="120x120" href="assets/apple-icon-120x120.png">
  <link rel="apple-touch-icon" sizes="144x144" href="assets/apple-icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="assets/apple-icon-152x152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-icon-180x180.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/android-icon-192x192.png">
  <link rel="icon" type="image/png" sizes="32x32"   href="assets/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="96x96"   href="assets/favicon-96x96.png">
  <link rel="icon" type="image/png" sizes="16x16"   href="assets/favicon-16x16.png">

  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap JS (deferred) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

  <!-- Custom Styles -->
  <link rel="stylesheet" href="assets/style.css">

  <!-- JSON-LD WebApp Schema -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "PHPsychometric",
    "url": "<?= htmlspecialchars(BASE_URL) ?>",
    "applicationCategory": "ProductivityApplication",
    "operatingSystem": "All",
    "description": "Online psychometrische Fragebögen erstellen, beantworten und auswerten."
  }
  </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded-bottom">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="assets/logo_long.svg" alt="PHPsychometric Logo" height="36" class="me-2 rounded">
      <span class="fw-bold">PHPsychometric</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#mainNavbar" aria-controls="mainNavbar"
            aria-expanded="false" aria-label="Menü ein-/ausblenden">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item">
          <a class="nav-link <?= ($activePage==='overview'?'active':'') ?>" href="index.php">Übersicht</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= ($activePage==='create'?'active':'') ?>" href="edit_questionnaire.php">Fragebogen erstellen</a>
        </li>
        <li class="nav-item d-none d-lg-inline">
          <a class="nav-link <?= ($activePage==='faq'?'active':'') ?>" href="faq.php">FAQ</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Seiteninhalt ... -->

</body>
</html>
