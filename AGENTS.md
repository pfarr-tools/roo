# AGENTS.md – Arbeitsanweisung für Codex

## 1. Projekt

**Roo** ist eine vollständig deutschsprachige Webanwendung zur Planung,
Durchführung, Dokumentation und Auswertung des Religionsunterrichts über
komplette Schuljahre.

„Roo“ leitet sich von „RU“ (Religionsunterricht) ab.

Die Anwendung wird zunächst für eine einzelne Lehrkraft entwickelt, muss aber
von Anfang an so modelliert werden, dass später mehrere Lehrkräfte,
Organisationen und Schulen unterstützt werden können.

## 2. Sprache

- Oberfläche, Hilfetexte, Validierungsfehler und fachliche Begriffe: Deutsch.
- Quellcode, Klassennamen, Methodennamen, Datenbankspalten und technische
  Kommentare: Englisch.
- Domänenbegriffe dürfen als englische, eindeutig dokumentierte Begriffe im
  Code erscheinen.
- Git-Commit-Nachrichten: Deutsch oder Conventional Commits auf Englisch;
  innerhalb eines Pull Requests konsistent bleiben.
- Keine Texte hart in Vue-Komponenten verteilen. Übersetzbare UI-Texte über
  Laravels Lokalisierung bzw. eine zentrale Frontend-Lokalisierung verwalten.

## 3. Verbindlicher Technologie-Stack

- Laravel 13
- PHP 8.4
- Vue 3
- Inertia.js 3
- Bootstrap 5.3 mit Sass
- Bootstrap Icons
- Vite
- PostgreSQL 17
- Redis
- Laravel Horizon
- Laravel Scout mit Meilisearch
- S3-kompatibler Object Storage
- Mailpit in der Entwicklung
- Pest für Backend-Tests
- Vitest für isolierte Frontend-Tests
- Docker Compose als einzige unterstützte Entwicklungsumgebung

Keine lokale PHP-, Composer-, Node-, PostgreSQL- oder Redis-Installation
voraussetzen.

## 4. Zentrale Architekturentscheidungen

### 4.1 Modularer Monolith

Roo ist zunächst ein modularer Laravel-Monolith, keine Microservice-Landschaft.

Fachmodule werden sauber getrennt, kommunizieren aber innerhalb derselben
Anwendung und Datenbank. Keine vorschnelle Aufteilung in Services.

Vorgesehene Module:

1. Identity
2. Schools
3. SchoolYears
4. EducationPlans
5. Curricula
6. TeachingGroups
7. Students
8. Planning
9. Lessons
10. Resources
11. Songs
12. Assessment
13. Documents
14. Generation
15. AI Assistance

### 4.2 Strukturierte Daten sind die Quelle der Wahrheit

Word-, PDF- und Präsentationsdateien sind Exporte oder Anhänge, nicht die
primäre Repräsentation einer Unterrichtseinheit oder Stunde.

Insbesondere strukturiert speichern:

- Kompetenzen
- Themen
- Unterrichtseinheiten
- Unterrichtsstunden
- Phasen
- Sozialformen
- Materialien
- Beobachtungen
- Bewertungen
- Lieder und Liedfassungen
- Dokumentbeziehungen

### 4.3 Vorlage und konkrete Verwendung trennen

Immer zwischen wiederverwendbarer Vorlage und konkreter Instanz unterscheiden.

Beispiele:

- Unterrichtseinheiten-Vorlage → geplante Einheit einer Unterrichtsgruppe
- Stundenentwurf → konkrete Unterrichtsstunde
- Phasenvorlage → konkrete Phase
- Liedfassung → Aufnahme in ein Gruppenliederbuch
- Curriculum → Verwendung an Schule bzw. Unterrichtsgruppe

Historische Daten dürfen sich nicht unbemerkt verändern, wenn eine Vorlage
später bearbeitet wird. Standard ist Copy-on-use bzw. eine versionierte
Referenz mit expliziter Aktualisierung.

### 4.4 Mandantenfähigkeit vorbereiten

Fachliche Datensätze, die einer Lehrkraft oder Organisation gehören, erhalten
einen eindeutigen Besitzer bzw. Mandantenbezug. Globale Referenzdaten werden
explizit als solche modelliert.

Keine Abfrage personenbezogener Daten ohne Scope/Policy.

### 4.5 Datenschutz

Schülerdaten, Beobachtungen und Bewertungen sind besonders schützenswert.

- Keine Schülerdaten in Logs.
- Keine Schülerdaten in Meilisearch, solange dies nicht ausdrücklich
  beschlossen und abgesichert ist.
- Keine Übermittlung personenbezogener Schülerdaten an KI-Anbieter.
- KI-Prompts standardmäßig anonymisieren.
- Zugriffe über Laravel Policies absichern.
- Lösch- und Exportkonzept von Anfang an berücksichtigen.
- Anhänge niemals allein durch erratbare öffentliche URLs schützen.

## 5. Kerndomäne

### 5.1 Schulen und Curricula

- Eine Schule kann mehrere Curricula verwenden.
- Ein Curriculum kann an mehreren Schulen verwendet werden.
- Die Beziehung Schule ↔ Curriculum ist n:m und kann Gültigkeitszeiträume,
  Schularten, Jahrgänge und Hinweise enthalten.
- Eine Unterrichtsgruppe kann ein primäres und mehrere ergänzende Curricula
  verwenden.
- Curricula sind versionierbar.

### 5.2 Unterrichtsgruppen

Eine Unterrichtsgruppe:

- gehört zu einem Schuljahr,
- findet an einer Schule statt,
- kann Schüler:innen aus mehreren Klassen enthalten,
- hat einen oder mehrere regelmäßige Stundenplantermine,
- verwendet Curricula,
- erhält eine Jahresplanung,
- besitzt ein wachsendes Gruppenliederbuch,
- sammelt Beobachtungen und Bewertungen.

### 5.3 Planung

- Ferien, Feiertage und Schuljahresgrenzen kommen aus importierbaren APIs,
  müssen aber überschreibbar sein.
- Alle automatisch importierten Kalenderdaten behalten ihre Herkunft.
- Unterrichtseinheiten werden visuell auf einer Schuljahres-Zeitachse
  verteilt.
- Planungseinträge können verschoben, verlängert, verkürzt und unterbrochen
  werden.
- Tatsächliche Durchführung und ursprüngliche Planung getrennt speichern.

### 5.4 Unterrichtseinheiten, Stunden und Phasen

Unterrichtseinheit:

- Thema
- Kompetenzen
- erwartete Dauer
- Stunden
- Materialien
- Anhänge
- Lieder
- Differenzierung
- Hinweise

Unterrichtsstunde:

- Datum und Zeit
- Bezug zur Einheit
- Status: geplant, vorbereitet, durchgeführt, ausgefallen, verschoben
- Phasen
- Anhänge
- Unterrichtsnotizen
- Schülerbeobachtungen

Phase:

- Reihenfolge
- Dauer
- Sozialform
- Beschreibung
- Material
- Anhänge
- optionale Phasenvorlage

Wiederkehrende Phasen, etwa ritualisierte Einstiege, werden als
Phasenvorlagen modelliert und können automatisch in neue Stunden eingefügt
werden.

### 5.5 Lieder

- Zentrale Liedersammlung mit Metadaten, Themen, Altersstufen und Rechten.
- Ein Lied kann mehrere Fassungen besitzen.
- Eine Fassung kann ein vorhandenes oder generiertes A5-Liedblatt haben.
- Lieder können Einheiten, Stunden und Phasen zugeordnet werden.
- Jede Unterrichtsgruppe hat einen Basisbestand zu Schuljahresbeginn.
- Neu verwendete Lieder können dem Gruppenliederbuch hinzugefügt werden.
- Das Gruppenliederbuch wächst im Laufe des Schuljahres.
- Export jederzeit als A5-PDF, A4-Doppelseite oder Broschüre.
- Export wahlweise vollständig, ausgewählt oder nur seit letztem Druck neu.
- Rechte müssen vor PDF-Ausgabe geprüft werden.
- Gruppenspezifische Liednummer und globale Lied-ID getrennt speichern.

### 5.6 Beobachtungen und Bewertung

- Pro Stunde schnelle Schülerübersicht.
- Mögliche Ereignisse: anwesend, abwesend, verspätet, Material fehlt,
  Hausaufgabe fehlt und frei definierbare Beobachtungssymbole.
- Beobachtungen sind Nachweise, nicht automatisch Noten.
- Bewertungen können kompetenzbezogen erfolgen.
- Ab Klasse 5: G/M/E-Niveau.
- Optional numerische deutsche Note 1–6.
- Halbjahr: Viertelnoten, in der Darstellung als Plus/Minus.
- Jahresende: Ganzzahlnote.
- Endbewertung kann aus strukturierten Textblöcken zusammengesetzt werden.
- Automatische Vorschläge müssen für die Lehrkraft nachvollziehbar und
  überschreibbar sein.

## 6. UI-Regeln

- Bootstrap 5.3 und eigene Vue-Komponenten.
- Kein zusätzliches vollständiges UI-Framework ohne ADR.
- Bootstrap-JavaScript nur gezielt verwenden; DOM-Zustand bevorzugt durch Vue
  steuern.
- Desktop-first für Planungsansichten, aber Kernfunktionen mobil benutzbar.
- Beobachtungserfassung muss auf Tablet und Notebook sehr schnell funktionieren.
- Tastaturbedienung und Barrierefreiheit berücksichtigen.
- Keine Drag-and-drop-Funktion ohne alternative Tastatursteuerung.
- Deutsche Datumsdarstellung, intern ISO-8601.
- Zeitzone standardmäßig `Europe/Berlin`, konfigurierbar.

## 7. Backend-Regeln

- Controllers dünn halten.
- Validierung über Form Requests.
- Autorisierung über Policies.
- Fachlogik in Actions, Services oder Domain-Klassen.
- Transaktionen bei fachlich zusammengehörigen Änderungen.
- Enums für stabile Statuswerte.
- Geldwerte spielen derzeit keine Rolle; keine unnötige Commerce-Abstraktion.
- UUID/ULID für extern sichtbare Entitäten erwägen; Entscheidung pro Modul
  konsistent dokumentieren.
- Fremdschlüssel und sinnvolle Constraints in der Datenbank verwenden.
- Keine fachlich relevanten Regeln ausschließlich im Frontend implementieren.

## 8. Frontend-Regeln

- Vue Composition API und `<script setup>`.
- Inertia für Navigation und Formulare.
- Keine zusätzliche globale State-Library, solange Vue/Inertia ausreichen.
- Wiederverwendbare Basiskomponenten unter
  `resources/js/Components/Ui`.
- Fachkomponenten nach Modul organisieren.
- Keine direkte Manipulation von Bootstrap-Modals quer durch Komponenten.
- Formulare müssen Serverfehler sauber anzeigen.
- Kritische Aktionen mit verständlicher Bestätigung.

## 9. Jobs und Dateien

Folgende Aufgaben immer als Queue Job prüfen:

- PDF-Generierung
- DOCX-Generierung
- Präsentationsgenerierung
- große Importe
- Suchindexierung
- KI-Anfragen
- vollständige Planungsprüfungen
- Massenexporte

Dateien ausschließlich über Laravel Filesystem ansprechen.

Vorgesehene Disks/Bereiche:

- documents
- generated
- imports
- exports
- temporary

Temporäre Dateien erhalten Ablaufzeiten und werden regelmäßig bereinigt.

## 10. Tests

Für jede fachliche Änderung:

- mindestens ein aussagekräftiger Feature- oder Unit-Test,
- Berechtigungstests für personenbezogene Daten,
- Tests für Datenbank-Constraints,
- Tests für relevante Randfälle.

Besonders gründlich testen:

- Curriculum-Zuordnungen
- Übernahme aus Vorjahren
- Ferien/Feiertage und ausgefallene Stunden
- Verschieben von Einheiten
- Kompetenzabdeckung
- G/M/E-Bewertungen
- Rundung und Darstellung von Noten
- Rechteprüfung beim Liederbuch
- Export „nur neue Lieder“
- Mandantentrennung

## 11. Vorgehen für Codex

Vor Beginn einer größeren Änderung:

1. `build/masterplan.md` lesen.
2. Relevante ADRs und Modulbeschreibungen lesen.
3. Bestehende Tests und Datenbankstruktur prüfen.
4. Einen kleinen, abgeschlossenen Arbeitsschritt auswählen.
5. Änderungen implementieren.
6. Tests, Formatierung und statische Analyse ausführen.
7. Dokumentation und Masterplan aktualisieren.

Nicht mehrere große Fachmodule in einem Schritt beginnen.

## 12. Definition of Done

Eine Aufgabe ist erst abgeschlossen, wenn:

- Implementierung vorhanden,
- Migrationen reversibel,
- Tests grün,
- Autorisierung geprüft,
- deutsche UI-Texte vorhanden,
- keine sensiblen Daten geloggt,
- Dokumentation aktualisiert,
- relevante Entscheidungen als ADR festgehalten,
- `composer test` bzw. Projekt-Testbefehl erfolgreich.

## 13. Verbotene Abkürzungen

- Keine produktive Logik in Seedern.
- Keine Speicherung ganzer Fachmodelle als unvalidiertes JSON, nur weil es
  schneller ist.
- Keine polymorphen Beziehungen ohne konkreten fachlichen Nutzen.
- Keine generische `settings`-Tabelle für klar modellierbare Kerndaten.
- Keine KI-generierte Bewertung ohne sichtbare menschliche Freigabe.
- Keine direkte Kopplung der Domäne an einen einzelnen KI-Anbieter.
- Keine öffentliche Ablage von Schülerlisten oder Bewertungsdateien.

## 14. Git-Commits

- Commit-Nachrichten verwenden Conventional Commits.
- Format: `<type>(<scope>): <imperative description>`.
- Erlaubte Typen sind insbesondere `feat`, `fix`, `docs`, `refactor`, `test`,
  `build`, `ci` und `chore`.
- Commit-Nachrichten bleiben innerhalb eines Pull Requests konsistent und
  beschreiben eine fachlich zusammenhängende Änderung.
