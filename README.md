# PHPsychometric

Dies ist eine Webanwendung zum Erstellen, Ausfüllen und Auswerten von wissenschaftlichen Fragebögen, bei der der Datenschutz der Nutzer*innen im Mittelpunkt steht. Die Anwendung ist vollständig open source, responsiv gestaltet und sowohl für individuelle Forschungsprojekte als auch für größere Online-Umfragen geeignet.

---

## Features

- **Online-Fragebögen**: Verschiedene Skalentypen, Subskalen und flexible Items.
- **Anonymisierte Nutzerprofile**: Keine Erhebung personenbezogener Daten; alle Angaben werden pseudonymisiert gespeichert.
- **Ergebnisberechnung**: Deskriptive Auswertung mit Mittelwerten/Summen und visueller Darstellung; Normwert-Interpretation ist vorbereitet.
- **Autorenbereich**: Fragebögen können dynamisch erstellt, bearbeitet und strukturiert werden (inkl. Drag & Drop, Skalen-Autocomplete).
- **Automatische Datenbank-Initialisierung**: Beim ersten Start legt die Anwendung die notwendigen Tabellen automatisch an.
- **IT-Security**: Sichere Sessions, PDO, keine sensiblen Daten im Repo, Standard-Header gegen gängige Angriffe.
- **Responsive Design**: Bootstrap 5 sorgt für eine ansprechende Nutzeroberfläche auf allen Geräten.

---

## Schnellstart

### Voraussetzungen

- PHP >= 7.4
- MySQL/MariaDB
- Webserver (Apache, nginx o.ä.)

### Installation

1. **Projekt clonen:**
    ```bash
    git clone https://github.com/DEIN-USER/online-frageboegen.git
    cd online-frageboegen
    ```

2. **Private Konfiguration anlegen:**
   - Kopiere die Datei `config.private.php.example` nach `config.private.php`  
   - Trage dort deine MySQL-Datenbankdaten ein:
     ```php
     <?php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'deinedatenbank');
     define('DB_USER', 'deinbenutzer');
     define('DB_PASS', 'deinpasswort');
     ?>
     ```

3. **Datenbank vorbereiten:**  
   - Die Datenbanktabellen werden beim ersten Start automatisch aus der Datei `db.sql` erzeugt (keine manuelle Installation nötig).

4. **Webserver konfigurieren:**  
   - Das Projektverzeichnis als DocumentRoot nutzen  
   - oder als Subfolder einrichten

5. **Aufruf im Browser:**  
   - Starte die Anwendung, z.B. unter http://localhost/ oder auf deinem Server

---

## Konfiguration

- **`config.private.php`** enthält sensible Daten (niemals ins Repo!).  
- In der Datei **`include.inc.php`** können globale Settings, Titel oder optionale Einstellungen geändert werden.
- Für den Produktivbetrieb:  
  - **HTTPS aktivieren**
  - `APP_ENV=prod` als Umgebungsvariable setzen (keine Fehleranzeige für Nutzer)
  - `.gitignore` enthält die Zeile `config.private.php`

---

## Datenbank

- Das Datenbankschema wird aus der Datei **`db.sql`** gelesen.
- Enthaltene Tabellen:
    - `questionnaires`: Fragebögen (Metadaten)
    - `items`: Items/Fragen (je Fragebogen)
    - `users`: Anonymisierte Nutzerprofile
    - `results`: Ergebnisse / Antworten
- Die Initialisierung erfolgt automatisch, sobald die Anwendung gestartet wird.

---

## Nutzung

### Für Nutzer*innen

- **Fragebogen auswählen** (`index.php`)
- **Profil (anonym) anlegen** (nur beim ersten Mal)
- **Fragebogen ausfüllen** (`q.php`)
- **Ergebnis erhalten** (`results.php`)

### Für Autoren

- **Neuen Fragebogen anlegen oder bearbeiten** (`edit_questionnaire.php`)
    - Name, Kurzbeschreibung, Sprache und Skalentyp wählen
    - Items dynamisch hinzufügen, Reihenfolge ändern, Skalen benennen
    - Subskalen werden automatisch erkannt
    - Nach dem ersten Ausfüllen können Items nicht mehr verändert werden

---

## Datenschutz & IT-Sicherheit

- Es werden keine personenbezogenen Daten gespeichert.
- Nutzerprofile sind vollständig anonym bzw. pseudonymisiert (ID nur als Cookie).
- Alle sicherheitsrelevanten Header und Settings werden standardmäßig gesetzt.
- Private Konfiguration niemals ins Repo einchecken!
- Bei Deployment in Produktivumgebung: HTTPS erzwingen, Fehlerausgabe deaktivieren.

---

## Erweiterungen / TODO

- Interpretation der Ergebnisse anhand dynamischer Normwerttabellen (sobald genügend Daten vorliegen)
- Mehrsprachigkeit und Übersetzungen der Nutzeroberfläche
- User-Authentifizierung für Autorenbereich (optional)
- Erweiterte Statistiken / Admin-Auswertungen
- E-Mail-Benachrichtigung bei neuen Fragebögen (optional)

---

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz.  
Weitere Details siehe [LICENSE](LICENSE).

---

> **Fragen, Feedback und Beiträge sind willkommen!**
