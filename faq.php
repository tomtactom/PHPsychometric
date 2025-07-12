<?php
require_once 'include.inc.php';
$pageTitle       = 'FAQ';
$pageDescription = 'Antworten auf häufig gestellte Fragen zu PHPsychometric';
require_once 'navbar.inc.php';

// FAQ-Daten als PHP-Array
$faqItems = [
    [
        'section'  => 'Allgemeines zur Plattform',
        'question' => 'Was genau ist PHPsychometric?',
        'answer'   => '<p>PHPsychometric ist eine Online-Plattform, auf der psychologische und psychometrische Fragebögen professionell erstellt, anonym beantwortet und automatisiert ausgewertet werden können.</p><p>Ziel ist es, sowohl wissenschaftlich fundierte Messungen als auch praktische psychologische Diagnostik einfach zugänglich zu machen.</p>'
    ],
    [
        'section'  => 'Allgemeines zur Plattform',
        'question' => 'Für welche Anwendungsgebiete eignet sich PHPsychometric?',
        'answer'   => '<p>Die Plattform eignet sich insbesondere für Forschung, Praxis und Ausbildung in Psychologie, Sozialwissenschaft, Marktforschung, Personalentwicklung sowie im Bereich Coaching und Beratung.</p>'
    ],
    [
        'section'  => 'Nutzung der Fragebögen',
        'question' => 'Was macht einen Fragebogen psychometrisch hochwertig?',
        'answer'   => '<p>Ein psychometrisch hochwertiger Fragebogen erfüllt Kriterien der Objektivität, Reliabilität (z. B. interne Konsistenz – Cronbachs Alpha) und Validität (erfasst wirklich das Zielkonstrukt). PHPsychometric unterstützt dich mit automatischen Qualitätskontrollen und klaren Auswertungen.</p>'
    ],
    [
        'section'  => 'Nutzung der Fragebögen',
        'question' => 'Warum ist eine Registrierung nicht erforderlich?',
        'answer'   => '<p>Ohne Registrierung bleibt dein Ausfüllen vollständig anonym. Dies fördert ehrliche Antworten und schützt deine Privatsphäre.</p>'
    ],
    [
        'section'  => 'Nutzung der Fragebögen',
        'question' => 'Wie oft darf ich einen Fragebogen ausfüllen?',
        'answer'   => '<p>Du kannst die Fragebögen mehrfach beantworten, jedoch fließt nur deine erste Eingabe in die Berechnung der Normwerte ein, um statistische Verzerrungen durch Mehrfachteilnahmen zu vermeiden.</p>'
    ],
    [
        'section'  => 'Erstellung und Bearbeitung von Fragebögen',
        'question' => 'Was muss ich beachten, um einen zuverlässigen Fragebogen zu erstellen?',
        'answer'   => '<p>Formuliere Fragen klar, neutral und vermeide suggestive Sprache. Nutze invertierte („negativ gepolte“) Items, um Antwort­tendenzen zu reduzieren. Definiere Subskalen eindeutig und überprüfe mit Cronbachs Alpha die Konsistenz.</p>'
    ],
    [
        'section'  => 'Erstellung und Bearbeitung von Fragebögen',
        'question' => 'Was bedeutet „negativ gepoltes Item“?',
        'answer'   => '<p>Ein negativ gepoltes Item erfasst dieselbe Eigenschaft wie ein positiv formuliertes, aber in gegenteiliger Aussage (z. B. statt „Ich mag Gesellschaft“: „Ich ziehe Einsamkeit vor“). Dies erhöht die Messgenauigkeit, indem Ja-Sage-Tendenzen ausgeglichen werden.</p>'
    ],
    [
        'section'  => 'Ergebnisse und Interpretation',
        'question' => 'Was sind Normwerte und warum sind sie wichtig?',
        'answer'   => '<p>Norm­werte ermöglichen den Vergleich individueller Ergebnisse mit einer Referenzgruppe (z. B. Alters- oder Geschlechtsgruppe). So siehst du, ob dein Ergebnis über, unter oder im Durchschnitt liegt.</p>'
    ],
    [
        'section'  => 'Ergebnisse und Interpretation',
        'question' => 'Wie stellt PHPsychometric Ergebnisse dar?',
        'answer'   => '<p>Die Plattform liefert numerische Mittel- oder Summen­werte sowie visuelle Darstellungen (Balken­diagramme, Fortschritts­balken). Ab ca. 50 Datensätzen werden Normwerte und Cronbachs Alpha berechnet.</p>'
    ],
    [
        'section'  => 'Ergebnisse und Interpretation',
        'question' => 'Ab welcher Stichprobengröße sind Normwerte und Cronbachs Alpha valide?',
        'answer'   => '<p>Erste Normwerte sind ab 50–100 Datensätzen sinnvoll. Für Cronbachs Alpha sind mindestens 30–50 Antworten nötig, besser ≥ 100 für robuste Interpretationen.</p>'
    ],
    [
        'section'  => 'Datenschutz und Anonymität',
        'question' => 'Wie ist meine Anonymität gewährleistet?',
        'answer'   => '<p>Persönliche Identifikations­merkmale werden nicht gespeichert. Demografische Daten sind pseudonymisiert und dienen ausschließlich der Normwert-Berechnung.</p>'
    ],
    [
        'section'  => 'Datenschutz und Anonymität',
        'question' => 'Wofür werden demografische Daten verwendet?',
        'answer'   => '<p>Alter, Geschlecht, Bildung etc. ermöglichen gezielte Vergleichs­gruppen – sodass du dein Ergebnis mit einer passenden Referenz­population vergleichen kannst.</p>'
    ],
    [
        'section'  => 'Technische Fragen und Probleme',
        'question' => 'Welche Browser und Geräte werden unterstützt?',
        'answer'   => '<p>PHPsychometric ist responsive und kompatibel mit modernen Browsern (Chrome, Firefox, Safari, Edge) auf Desktop, Tablet und Smartphone.</p>'
    ],
    [
        'section'  => 'Technische Fragen und Probleme',
        'question' => 'Was tun bei technischen Problemen?',
        'answer'   => '<p>Leere den Browser-Cache oder wechsle das Gerät. Bleibt das Problem bestehen, kontaktiere unseren Support über das Kontaktformular oder per E-Mail – bitte mit Angabe von URL, Browser und Fehlermeldung.</p>'
    ],
    [
        'section'  => 'Für Autoren und Administratoren',
        'question' => 'Warum &amp; wie schütze ich meinen Fragebogen mit einem Passwort?',
        'answer'   => '<p>Ein Autor-Passwort verhindert unautorisierte Änderungen. Du legst es beim Anlegen fest, es wird sicher verschlüsselt gespeichert. Administratoren können zusätzlich mit dem globalen Admin-Passwort jeden Fragebogen verwalten.</p>'
    ],
    [
        'section'  => 'Für Autoren und Administratoren',
        'question' => 'Kann ich Ergebnisse exportieren?',
        'answer'   => '<p>Ein Export (CSV/Excel) ist in Planung. Bei dringendem Bedarf unterstützen wir dich gerne manuell – kontaktiere uns.</p>'
    ],
    [
        'section'  => 'Wissenschaftlicher Hintergrund',
        'question' => 'Wie funktioniert Cronbachs Alpha und warum ist es wichtig?',
        'answer'   => '<p>Cronbachs Alpha misst die interne Konsistenz einer Skala, also wie stark die Items einer Skala miteinander korrelieren. Ein Wert ≥ 0,70 gilt als guter Indikator für zuverlässige Messung.</p>'
    ]
];
?>
<div class="container my-5">
  <h1 class="faq-title text-center mb-5">Häufig gestellte Fragen</h1>

  <!-- Suche -->
  <div class="mb-4 text-center">
    <input id="faqSearch" type="text" class="form-control form-control-lg w-75 mx-auto"
           placeholder="Suche Fragen…" autofocus>
  </div>

  <!-- FAQ-Karten-Grid -->
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    <?php foreach ($faqItems as $idx => $item): ?>
      <div class="col">
        <div class="card faq-card h-100 shadow-sm" tabindex="0"
             data-index="<?= $idx ?>"
             data-question="<?= htmlspecialchars($item['question'], ENT_QUOTES) ?>"
             data-answer='<?= htmlspecialchars($item['answer'], ENT_QUOTES) ?>'
             data-section="<?= htmlspecialchars($item['section'], ENT_QUOTES) ?>">
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($item['question']) ?></h5>
            <p class="card-text text-muted small"><?= htmlspecialchars($item['section']) ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal für Antworten -->
<div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="faqModalLabel"></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>

<?php include 'footer.inc.php'; ?>

<script>
  // Live-Filter: zeigt Karten, deren Frage oder Sektion den Suchbegriff enthalten
  document.getElementById('faqSearch').addEventListener('input', function(){
    const term = this.value.toLowerCase();
    document.querySelectorAll('.faq-card').forEach(card => {
      const q = card.dataset.question.toLowerCase();
      const s = card.dataset.section.toLowerCase();
      card.parentElement.style.display = (q.includes(term) || s.includes(term)) ? '' : 'none';
    });
  });

  // Klick & Enter auf Karte öffnet Modal
  document.querySelectorAll('.faq-card').forEach(card => {
    const showModal = () => {
      const title   = card.dataset.question;
      const content = card.dataset.answer;
      document.getElementById('faqModalLabel').textContent = title;
      document.querySelector('#faqModal .modal-body').innerHTML = content;
      new bootstrap.Modal(document.getElementById('faqModal')).show();
    };
    card.addEventListener('click', showModal);
    card.addEventListener('keypress', e => { if (e.key==='Enter') showModal(); });
  });
</script>
