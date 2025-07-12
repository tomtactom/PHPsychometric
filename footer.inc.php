<?php
// footer.inc.php
// Benötigt in config.private.php definiert:
//   $company_name, $owner_name, $contact_email, $contact_phone, $contact_address, $vat_id, $tax_id
?>
<footer class="bg-light text-center text-muted py-4 mt-5">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
    <div>
      &copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?>
    </div>
    <div>
      <button type="button" class="btn btn-link text-decoration-none px-2" data-bs-toggle="modal" data-bs-target="#impressumModal">
        Impressum
      </button>
      |
      <button type="button" class="btn btn-link text-decoration-none px-2" data-bs-toggle="modal" data-bs-target="#privacyModal">
        Datenschutzerklärung
      </button>
    </div>
  </div>
</footer>

<!-- Impressum Modal -->
<div class="modal fade" id="impressumModal" tabindex="-1" aria-labelledby="impressumLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="impressumLabel">Impressum</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <p><strong>Angaben gemäß § 5 TMG</strong></p>
        <p>
          <?= htmlspecialchars($company_name) ?><br>
          Inhaber: <?= htmlspecialchars($owner_name) ?><br>
          <?= nl2br(htmlspecialchars($contact_address)) ?>
        </p>
        <p><strong>Kontakt</strong></p>
        <p>
          E-Mail: <span id="imp-email"></span><br>
          Telefon: <span id="imp-phone"></span>
        </p>
        <?php if (!empty($tax_id) || !empty($vat_id)): ?>
        <p><strong>Steuernummer</strong><br>
          <?= htmlspecialchars($tax_id ?? $vat_id) ?>
        </p>
        <?php endif; ?>
        <p><strong>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</strong><br>
           <?= htmlspecialchars($owner_name) ?><br>
           <?= nl2br(htmlspecialchars($contact_address)) ?>
        </p>
      </div>
    </div>
  </div>
</div>

<!-- Datenschutzerklärung Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="privacyLabel">Datenschutzerklärung</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body text-start">
        <h6>1. Verantwortlicher</h6>
        <p>
          <?= htmlspecialchars($company_name) ?><br>
          Inhaber: <?= htmlspecialchars($owner_name) ?><br>
          <?= nl2br(htmlspecialchars($contact_address)) ?><br>
          E-Mail: <span id="priv-email"></span><br>
          Telefon: <span id="priv-phone"></span>
        </p>
        <h6>2. Erhebung und Verarbeitung personenbezogener Daten</h6>
        <p>Beim Ausfüllen eines Fragebogens erheben wir pseudonymisierte demografische Daten und Antworten. Diese Daten dienen ausschließlich der statistischen Auswertung und Normwert-Berechnung.</p>
        <h6>3. Rechtsgrundlage</h6>
        <p>Die Verarbeitung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. e DSGVO (öffentliche Aufgabe) und ggf. Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).</p>
        <h6>4. Weitergabe an Dritte</h6>
        <p>Es erfolgt keine Weitergabe an Dritte, außer bei gesetzlicher Verpflichtung.</p>
        <h6>5. Cookies &amp; Tracking</h6>
        <p>Für die Sitzung wird ein Cookie gesetzt, um Mehrfacheinreichungen zu verhindern. Dieses Cookie wird nach Ablauf von 10 Jahren erneuert.</p>
        <h6>6. Betroffenenrechte</h6>
        <p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung und Datenübertragbarkeit. Wenden Sie sich hierzu an:</p>
        <p>
          E-Mail: <span id="priv-email-2"></span><br>
          Telefon: <span id="priv-phone-2"></span>
        </p>
        <h6>7. Änderungen dieser Erklärung</h6>
        <p>Wir behalten uns vor, diese Datenschutzerklärung anzupassen. Die jeweils aktuelle Fassung finden Sie hier.</p>
      </div>
    </div>
  </div>
</div>

<script>
// JS-geschützte Kontaktdaten zum Schutz vor Crawlern
document.addEventListener('DOMContentLoaded', function() {
  const email   = <?= json_encode($contact_email) ?>;
  const phone   = <?= json_encode($contact_phone) ?>;

  // Impressum
  document.getElementById('imp-email').innerHTML = '<a href="mailto:' + email + '">' + email + '</a>';
  document.getElementById('imp-phone').textContent = phone;

  // Datenschutzerklärung
  document.getElementById('priv-email').innerHTML   = '<a href="mailto:' + email + '">' + email + '</a>';
  document.getElementById('priv-phone').textContent = phone;
  document.getElementById('priv-email-2').innerHTML   = '<a href="mailto:' + email + '">' + email + '</a>';
  document.getElementById('priv-phone-2').textContent = phone;
});
</script>
</body>
</html>
