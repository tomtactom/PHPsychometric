-- Tabelle: questionnaires
-- Speichert die Metadaten zu jedem Fragebogen. Jeder Eintrag enthält einen eindeutigen Namen,
-- eine optionale Kurzbezeichnung, einen Sprachcode (z.B. 'EN' oder 'DE'), einen Integer-Code für choice_type,
-- sowie eine frei wählbare Beschreibung. Die Felder 'created_at' und 'updated_at' dokumentieren
-- das Erstellungs- und Änderungsdatum des Eintrags.
CREATE TABLE questionnaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,           -- Eindeutiger Name des Fragebogens
    short VARCHAR(50),                           -- Kurzbezeichnung
    language CHAR(2),                            -- Sprachcode, z.B. 'DE', 'EN'
    choice_type INT,                             -- Typ der Antwortmöglichkeit (numerisch codiert)
    description TEXT,                            -- Freitextbeschreibung
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,                     -- Erstellungszeitpunkt
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- Zeitpunkt der letzten Änderung
);

-- Tabelle: items
-- Enthält die einzelnen Items (Fragen) zu jedem Fragebogen.
-- Das Feld 'negated' kennzeichnet umgepolte (negativ formulierte) Items (nur für Auswertungszwecke relevant).
-- Im Feld 'scale' wird angegeben, welche Subskala oder welches Merkmal durch das Item gemessen wird (als Freitext).
-- Jedes Item verweist über 'questionnaire_id' auf einen Fragebogen.
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    questionnaire_id INT NOT NULL,               -- Fremdschlüssel auf questionnaires(id)
    item TEXT NOT NULL,                          -- Formulierung des Items
    negated BOOLEAN DEFAULT FALSE,               -- 1 = negativ gepolt, 0 = positiv (nur für Auswertung)
    scale VARCHAR(255),                          -- Name der Subskala/des Merkmals (frei vergeben)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id)
        ON DELETE RESTRICT                      -- Löschen eines Fragebogens nur möglich, wenn keine Items mehr vorhanden sind
);

-- Tabelle: users
-- Speichert pseudonymisierte demografische Daten der Nutzer*innen.
-- Die Angaben erfolgen ausschließlich codiert (numerisch) zur Wahrung der Anonymität.
-- Es werden keine persönlichen Daten gespeichert. Die Zuordnung erfolgt nur über eine anonyme Cookie-ID im System.
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gender TINYINT,               -- Codierung: z.B. 0=male, 1=female, 2=diverse
    birth_year INT,               -- Geburtsjahr, vierstellig
    birth_month TINYINT,          -- Geburtsmonat (1-12)
    degree TINYINT,               -- Bildungsabschluss (numerisch codiert)
    marital_status TINYINT,       -- Familienstand (numerisch codiert)
    income TINYINT,               -- Einkommen (numerisch codiert)
    german_knowledge TINYINT,     -- Deutschkenntnisse (z.B. Skala 0-5)
    english_knowledge TINYINT,    -- Englischkenntnisse (z.B. Skala 0-5)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabelle: results
-- Speichert die Antworten der Nutzer*innen auf einzelne Items.
-- Das Feld 'result' kann numerische Werte (z.B. Skalenpunkte) oder auch Freitext (z.B. bei offenen Fragen) enthalten.
-- Jede Antwort ist eindeutig einem Nutzer, einem Item und einem Fragebogen zugeordnet.
-- Die Fremdschlüssel sind mit 'ON DELETE RESTRICT' versehen, d.h. das Löschen von Nutzern, Items oder Fragebögen
-- ist nur möglich, wenn keine zugehörigen Ergebnisse mehr vorhanden sind. Dadurch bleibt die Datenintegrität erhalten.
CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    questionnaire_id INT NOT NULL,           -- Verweis auf questionnaires(id)
    item_id INT NOT NULL,                    -- Verweis auf items(id)
    user_id INT NOT NULL,                    -- Verweis auf users(id)
    result TEXT,                             -- Ergebnis (numerisch oder Freitext)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id)
        ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES items(id)
        ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT
);

-- Hinweise:
-- Die Codierung der Felder (z.B. gender, degree, marital_status, income) sollte in einer separaten Dokumentation
-- oder als Mapping-Tabelle abgelegt werden.
-- Die Sprache ist per ISO-639-1 Code zu wählen (z.B. 'DE', 'EN').
-- Eine tiefergehende Änderungshistorie ist nicht vorgesehen, da 'created_at' und 'updated_at' die Basisinformationen liefern.
-- Das Löschen von Einträgen in den Haupttabellen ist nur möglich, wenn keine abhängigen Daten (results) existieren.
-- Damit bleibt die Referenzierbarkeit aller für die Normwertberechnung benötigten Daten stets gewährleistet.
