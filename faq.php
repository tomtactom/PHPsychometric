<?php
require_once 'include.inc.php';
$pageTitle       = 'FAQ';
$pageDescription = 'Antworten auf häufig gestellte Fragen zu PHPsychometric';
require_once 'navbar.inc.php';
?>
<div class="container my-5">
  <h1 class="mb-4">Häufig gestellte Fragen (FAQ)</h1>
  <div class="accordion" id="faqAccordion">

    <!-- 1. Allgemeines -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingGeneral">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral" aria-expanded="true" aria-controls="collapseGeneral">
          1. Allgemeines zur Plattform
        </button>
      </h2>
      <div id="collapseGeneral" class="accordion-collapse collapse show" aria-labelledby="headingGeneral" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Was genau ist PHPsychometric?</h5>
          <p>PHPsychometric ist eine Online-Plattform, auf der psychologische und psychometrische Fragebögen professionell erstellt, anonym beantwortet und automatisiert ausgewertet werden können. Ziel ist es, sowohl wissenschaftlich fundierte Messungen als auch praktische psychologische Diagnostik einfach zugänglich zu machen.</p>
          <h5>Für welche Anwendungsgebiete eignet sich PHPsychometric?</h5>
          <p>Die Plattform eignet sich insbesondere für Forschung, Praxis und Ausbildung in Psychologie, Sozialwissenschaft, Marktforschung, Personalentwicklung sowie im Bereich Coaching und Beratung.</p>
        </div>
      </div>
    </div>

    <!-- 2. Nutzung der Fragebögen -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingUsage">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUsage" aria-expanded="false" aria-controls="collapseUsage">
          2. Nutzung der Fragebögen
        </button>
      </h2>
      <div id="collapseUsage" class="accordion-collapse collapse" aria-labelledby="headingUsage" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Was macht einen Fragebogen psychometrisch hochwertig?</h5>
          <p>Ein psychometrisch hochwertiger Fragebogen erfüllt Kriterien der Objektivität, Reliabilität (z. B. interne Konsistenz – Cronbachs Alpha) und Validität (erfasst wirklich das Zielkonstrukt). PHPsychometric unterstützt Dich mit automatischen Qualitätskontrollen und klaren Auswertungen.</p>
          <h5>Warum ist eine Registrierung nicht erforderlich?</h5>
          <p>Ohne Registrierung bleibt Dein Ausfüllen vollständig anonym. Dies fördert ehrliche Antworten und schützt Deine Privatsphäre.</p>
          <h5>Wie oft darf ich einen Fragebogen ausfüllen?</h5>
          <p>Du kannst die Fragebögen mehrfach beantworten, jedoch fließt nur Deine erste Eingabe in die Berechnung der Normwerte ein, um statistische Verzerrungen durch Mehrfachteilnahmen zu vermeiden.</p>
        </div>
      </div>
    </div>

    <!-- 3. Erstellung und Bearbeitung -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingCreation">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreation" aria-expanded="false" aria-controls="collapseCreation">
          3. Erstellung und Bearbeitung von Fragebögen
        </button>
      </h2>
      <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Was muss ich beachten, um einen zuverlässigen Fragebogen zu erstellen?</h5>
          <p>Formuliere Fragen klar, neutral und vermeide suggestive Sprache. Nutze invertierte („negativ gepolte“) Items, um Antworttendenzen zu reduzieren. Definiere Subskalen eindeutig und überprüfe mit Cronbachs Alpha die Konsistenz.</p>
          <h5>Was bedeutet „negativ gepoltes Item“?</h5>
          <p>Ein negativ gepoltes Item erfasst dieselbe Eigenschaft wie ein positiv formuliertes, aber in gegenteiliger Aussage (z. B. statt „Ich mag Gesellschaft“: „Ich ziehe Einsamkeit vor“). Dies erhöht die Messgenauigkeit, indem Ja-Sage-Tendenzen ausgeglichen werden.</p>
        </div>
      </div>
    </div>

    <!-- 4. Ergebnisse und Interpretation -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingResults">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseResults" aria-expanded="false" aria-controls="collapseResults">
          4. Ergebnisse und Interpretation
        </button>
      </h2>
      <div id="collapseResults" class="accordion-collapse collapse" aria-labelledby="headingResults" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Was sind Normwerte und warum sind sie wichtig?</h5>
          <p>Normwerte ermöglichen den Vergleich individueller Ergebnisse mit einer Referenzgruppe (z. B. Alters- oder Geschlechtsgruppe). So siehst Du, ob Dein Ergebnis über, unter oder im Durchschnitt liegt.</p>
          <h5>Wie stellt PHPsychometric Ergebnisse dar?</h5>
          <p>Die Plattform liefert numerische Mittel- oder Summenwerte und visuelle Darstellungen (Balkendiagramme, Fortschrittsbalken). Ab ca. 50 Datensätzen werden Normwerte und Cronbachs Alpha berechnet.</p>
          <h5>Ab welcher Stichprobengröße sind Normwerte und Cronbachs Alpha valide?</h5>
          <p>Erste Normwerte sind ab 50–100 Datensätzen sinnvoll. Für Cronbachs Alpha sind mindestens 30–50 Antworten nötig, besser ≥100 für robuste Interpretationen.</p>
        </div>
      </div>
    </div>

    <!-- 5. Datenschutz und Anonymität -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingPrivacy">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrivacy" aria-expanded="false" aria-controls="collapsePrivacy">
          5. Datenschutz und Anonymität
        </button>
      </h2>
      <div id="collapsePrivacy" class="accordion-collapse collapse" aria-labelledby="headingPrivacy" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Wie ist meine Anonymität gewährleistet?</h5>
          <p>Persönliche Identifikationsmerkmale werden nicht gespeichert. Demographische Daten sind pseudonymisiert und dienen ausschließlich zur Normwert-Berechnung.</p>
          <h5>Wofür werden demographische Daten verwendet?</h5>
          <p>Alter, Geschlecht, Bildung etc. ermöglichen gezielte Vergleichsgruppen – sodass Du Dein Ergebnis mit einer passenden Referenzpopulation vergleichen kannst.</p>
        </div>
      </div>
    </div>

    <!-- 6. Technische Fragen -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingTech">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTech" aria-expanded="false" aria-controls="collapseTech">
          6. Technische Fragen und Probleme
        </button>
      </h2>
      <div id="collapseTech" class="accordion-collapse collapse" aria-labelledby="headingTech" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Welche Browser und Geräte werden unterstützt?</h5>
          <p>PHPsychometric ist responsive und kompatibel mit modernen Browsern (Chrome, Firefox, Safari, Edge) auf Desktop, Tablet und Smartphone.</p>
          <h5>Was tun bei technischen Problemen?</h5>
          <p>Leere den Browser-Cache oder wechsle das Gerät. Falls das Problem bleibt, kontaktiere unseren Support über das Kontaktformular oder per E-Mail – bitte mit Angabe von URL, Browser und Fehlermeldung.</p>
        </div>
      </div>
    </div>

    <!-- 7. Für Autoren & Admins -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingAuthors">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAuthors" aria-expanded="false" aria-controls="collapseAuthors">
          7. Für Autoren und Administratoren
        </button>
      </h2>
      <div id="collapseAuthors" class="accordion-collapse collapse" aria-labelledby="headingAuthors" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Warum &amp; wie schütze ich meinen Fragebogen mit einem Passwort?</h5>
          <p>Ein Autor-Passwort verhindert unautorisierte Änderungen. Du legst es beim Anlegen fest, und es wird sicher verschlüsselt gespeichert. Administratoren können zudem mit dem globalen Admin-Passwort jeden Fragebogen verwalten.</p>
          <h5>Kann ich Ergebnisse exportieren?</h5>
          <p>Ein Export (CSV/Excel) ist in Planung. Bei dringendem Bedarf unterstützten wir Dich gerne manuell – kontaktiere uns einfach.</p>
        </div>
      </div>
    </div>

    <!-- 8. Wissenschaftlicher Hintergrund -->
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingScience">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseScience" aria-expanded="false" aria-controls="collapseScience">
          8. Wissenschaftlicher Hintergrund
        </button>
      </h2>
      <div id="collapseScience" class="accordion-collapse collapse" aria-labelledby="headingScience" data-bs-parent="#faqAccordion">
        <div class="accordion-body">
          <h5>Wie funktioniert Cronbachs Alpha und warum ist es wichtig?</h5>
          <p>Cronbachs Alpha misst die interne Konsistenz einer Skala, also wie stark die Items miteinander korrelieren. Ein Wert ≥ 0,70 gilt als guter Indikator für zuverlässige Messung.</p>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include('footer.inc.php'); ?>
