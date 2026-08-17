# Roo – Masterplan

Stand: 6. August 2026

## Zielbild

Roo unterstützt Lehrkräfte dabei, Religionsunterricht für ein vollständiges
Schuljahr zu planen, vorzubereiten, durchzuführen, zu dokumentieren und
auszuwerten.

Die Entwicklung erfolgt in kleinen, testbaren vertikalen Schnitten. Ein Modul
gilt nicht als fertig, nur weil Tabellen und CRUD-Seiten vorhanden sind. Jeder
Meilenstein soll einen real benutzbaren Arbeitsablauf ermöglichen.

---

## Phase 0 – Technische Basis

Fortschritt: `[x]` Phase 0 ist umgesetzt: Bootstrap, Docker-Healthchecks,
Laravel/Inertia/Vue/Bootstrap-Basis, deutsche Authentifizierung, Testgrundlage,
Docker-CI, ausführbarer Queue-Nachweis, privater Storage-Roundtrip und
erreichbarer Meilisearch-Index sind verifiziert.

### Ziel

Reproduzierbare Docker-Entwicklungsumgebung und minimale, geschützte
Laravel/Inertia-Anwendung.

### Lieferumfang

- Laravel 13
- PHP 8.4
- Vue 3
- Inertia.js 3
- Bootstrap 5.3/Sass
- PostgreSQL
- Redis
- Horizon
- Meilisearch
- S3-kompatibler Entwicklungsstorage
- Mailpit
- Pest
- Codeformatierung
- CI-Grundlage
- Anmeldung/Abmeldung
- deutsche Basissprache
- Healthchecks
- Beispiel-Dashboard

### Abnahmekriterien

- `docker compose up -d` startet alle Dienste.
- `./roo bootstrap` initialisiert eine frische Arbeitskopie.
- Login funktioniert.
- Queue-Job kann ausgeführt werden.
- Datei kann privat in S3-kompatiblen Storage geschrieben und gelesen werden.
- Suchindex ist erreichbar.
- Tests laufen vollständig in Docker.

---

## Phase 1 – Benutzer, Schulen und Schuljahre

Fortschritt: `[~]` Schulen, Schuljahre und Kalenderdaten als erster vertikaler
Schnitt umgesetzt; Ferien-API und lokale Überschreibungen integriert.

### Ziel

Eine Lehrkraft kann Schulen und Schuljahre verwalten und Kalendertage
strukturieren.

### Fachobjekte

- User
- Organization (vorbereitet)
- School
- SchoolYear
- SchoolYearDay
- HolidayPeriod
- CalendarException
- DataSource

### Funktionen

- Schule anlegen und bearbeiten
- Schuljahr mit Start- und Enddatum anlegen
- Ferien/Feiertage manuell erfassen
- importierte Daten überschreiben
- Herkunft und Änderungsgrund speichern
- Unterrichtstage im Kalender anzeigen

### Noch nicht

- vollständige externe Ferien-API
- Stundenplan
- Unterrichtsgruppen

---

## Phase 2 – Bildungspläne und Kompetenzen

Fortschritt: `[x]` Bildungspläne und Kompetenzen sind relational importierbar,
hierarchisch sichtbar, durchsuchbar, vergleichbar und über Importprotokolle
nachvollziehbar.

### Ziel

Bildungspläne können versioniert importiert oder manuell erfasst werden.

### Fachobjekte

- EducationPlan
- EducationPlanVersion
- SchoolType
- GradeLevel
- CompetenceArea
- Competence
- CompetenceRelation
- ImportRun

### Funktionen

- hierarchische Kompetenzansicht
- Versionsvergleich
- Suche
- Importprotokoll
- Kompetenz aktiv/inaktiv
- stabile externe Kennungen

### Qualitätsziel

Bildungspläne dürfen nicht als ein einziger JSON-Block gespeichert werden.

---

## Phase 3 – Curricula

### Ziel

Curricula ordnen Jahrgängen Themen und Kompetenzen zu und können an mehreren
Schulen verwendet werden.

### Fachobjekte

- Curriculum
- CurriculumVersion
- CurriculumTopic
- CurriculumTopicCompetence
- CurriculumSchoolAssignment
- CurriculumNote
- DenominationalPerspective

### Funktionen

- Curriculum erstellen und versionieren
- Themen nach Jahrgang ordnen
- Kompetenzen zuordnen
- Zeitbedarf hinterlegen
- KoKo-Hinweise evangelisch/katholisch/gemeinsam
- n:m-Zuordnung zu Schulen mit Gültigkeitszeitraum
- Curriculum kopieren
- Curriculum-Vergleich

---

## Phase 4 – Klassen, Schüler:innen und Unterrichtsgruppen

### Ziel

Reale Lerngruppen eines Schuljahres abbilden.

### Fachobjekte

- SchoolClass
- Student
- TeachingGroup
- TeachingGroupMembership
- TimetableSlot
- TeachingGroupCurriculum

### Funktionen

- Klassen anlegen
- Schüler:innen importieren und manuell verwalten
- Gruppe aus mehreren Klassen zusammensetzen
- Eintritt/Austritt zeitlich abbilden
- Stundenplan mit mehreren regelmäßigen Terminen
- primäres und ergänzende Curricula zuordnen

### Datenschutz

- Policies und Mandantenscopes vollständig
- Export- und Löschpfade vorbereiten
- keine Indexierung von Schülerdaten in Meilisearch

---

## Phase 5 – Wiederverwendbare Unterrichtsinhalte

### Ziel

Vorhandene Einheiten und Stunden aus früheren Jahren strukturiert übernehmen.

### Fachobjekte

- UnitTemplate
- LessonTemplate
- PhaseTemplate
- SocialForm
- ResourceReference
- MaterialItem
- Tag

### Funktionen

- Einheiten-Vorlagen verwalten
- Stunden-Vorlagen verwalten
- wiederkehrende Phasen
- Anhänge an Einheiten/Stunden/Phasen
- Suche und Filter
- Kopieren und versioniertes Aktualisieren
- Import aus einfachem Markdown/JSON als späterer Ausbaupunkt

---

## Phase 6 – Jahresplanung

### Ziel

Unterrichtseinheiten visuell über das Schuljahr verteilen.

### Fachobjekte

- GroupYearPlan
- PlannedUnit
- PlannedLesson
- LessonOccurrence
- PlanRevision

### Funktionen

- horizontale Jahres-/Wochenansicht
- Ferien und Ausnahmen sichtbar
- Einheit per Drag-and-drop und Tastatur verschieben
- Dauer ändern
- Einheit teilen oder unterbrechen
- Stunden aus Stundenplan erzeugen
- ausgefallene/verschobene Stunde markieren
- Plan und tatsächliche Durchführung unterscheiden
- Planänderungen nachvollziehen

### Erste Prüfungen

- verfügbare Stunden
- Einheiten ohne Kompetenzen
- Curriculumthemen ohne geplante Einheit
- Kompetenzabdeckung

---

## Phase 7 – Stundeneditor und Durchführung

### Ziel

Einzelne Stunden schnell vorbereiten und im Unterricht verwenden.

### Funktionen

- Phaseneditor
- Phasen per Vorlage einfügen
- Gruppenrituale automatisch ergänzen
- Zeiten summieren und warnen
- Materialliste generieren
- Präsentations-/Lehrkraftansicht
- Stunde als durchgeführt markieren
- Notizen zur tatsächlichen Durchführung
- einfache Nachplanung

---

## Phase 8 – Liedersammlung und Gruppenliederbuch

### Ziel

Lieder verwalten und für jede Gruppe ein wachsendes A5-Liederbuch erzeugen.

### Fachobjekte

- Song
- SongVersion
- SongRight
- SongSheet
- UnitSong
- LessonSong
- PhaseSong
- GroupSongbook
- GroupSongbookEntry
- SongbookExport
- PrintCheckpoint

### Funktionen

- Liedmetadaten und Rechte
- vorhandenes A5-PDF hochladen
- strukturiertes Liedblatt generieren
- Basisbestand für Gruppe übernehmen
- Lied beim ersten Einsatz hinzufügen
- gruppenspezifische Nummerierung
- PDF vollständig
- nur neue/geänderte Seiten seit letztem Druck
- A4 mit zwei A5-Seiten
- Broschürenexport
- Rechteprüfung vor Export

### Kritische Regel

Ohne ausreichende Rechte kein Text-/Notenexport. Metadatenzuordnung bleibt
möglich.

---

## Phase 9 – Beobachtungen

### Ziel

Während oder direkt nach jeder Stunde schnell Beobachtungen erfassen.

### Fachobjekte

- ObservationType
- Observation
- AttendanceRecord
- ObservationScale
- CompetenceEvidence

### Funktionen

- Schülerübersicht je Stunde
- anwesend/abwesend/verspätet
- frei definierbare Symbole
- fehlendes Material/Hausaufgabe
- kurze Notiz
- Mehrfachaktion für mehrere Schüler:innen
- kompetenzbezogener Nachweis
- mobil und auf Tablet optimiert

### Regel

Ein Emoticon ist ein konfigurierbarer Nachweis, keine automatische Note.

---

## Phase 10 – Lernstandserhebungen und Bewertung

### Ziel

Kompetenzorientierte Lernstandserhebungen erstellen und Ergebnisse bewerten.

### Fachobjekte

- Assessment
- AssessmentTask
- Rubric
- RubricCriterion
- StudentAssessment
- StudentAssessmentResult
- LevelJudgement
- NumericGrade

### Funktionen

- LSE aus Kompetenzen planen
- Aufgaben, Lösungen und Kriterien
- G/M/E-Differenzierung
- Ergebnisse je Kompetenz
- Viertelnoten zum Halbjahr
- Ganzzahlnote zum Jahresende
- nachvollziehbare Rundungsregeln
- manuelle Freigabe

---

## Phase 11 – Endbewertung und Textbausteine

### Ziel

Aus Nachweisen einen transparenten, bearbeitbaren Bewertungsentwurf erstellen.

### Fachobjekte

- ReportPeriod
- EvaluationBlock
- TextBlockTemplate
- StudentEvaluation
- EvaluationDecision

### Funktionen

- Textbausteine nach Bereich/Niveau
- Vorschläge aus Beobachtungen und Kompetenznachweisen
- Widersprüche und geringe Datengrundlage anzeigen
- Lehrkraft bearbeitet und bestätigt
- Export
- keine automatische endgültige Entscheidung

---

## Phase 12 – Arbeitsblätter, Präsentationen und KI

### Ziel

Aus strukturierten Inhalten differenzierte Materialien generieren.

### Architektur

Providerunabhängige Interfaces:

- AiTextProvider
- ImageProvider
- DocumentRenderer
- PresentationRenderer
- WorksheetRenderer

### Funktionen

- Arbeitsblatt + Lösung
- G/M/E-Fassungen
- Präsentationsentwurf
- LSE-Vorschläge
- Planungsassistent
- Kompetenzabdeckungsanalyse
- anonymisierte Prompts
- Prompt-/Modellversion dokumentieren
- menschliche Freigabe

---

## Phase 13 – Qualität, Import/Export und Mehrbenutzerbetrieb

### Ziel

Produktionsreife, Datensicherheit und Zusammenarbeit.

### Funktionen

- Backups und Restore-Test
- vollständiger Datenexport
- Löschkonzept
- Audit Log
- Rollen: Lehrkraft, Administration, Mitarbeit
- Freigabe von Vorlagen
- optional gemeinsame Bibliotheken
- Performanceprüfung
- Accessibility-Audit
- Security-Audit
- Upgrade-Dokumentation

---

# Priorisierte erste Codex-Aufträge

1. Docker-Stack starten und Healthchecks stabilisieren.
2. Laravel 13 mit Inertia 3, Vue 3 und Bootstrap initialisieren.
3. Authentifizierung mit deutscher Oberfläche.
4. CI und Testbefehle.
5. ADR 0001 finalisieren.
6. Phase 1 als erster vertikaler Schnitt: Schule anlegen und anzeigen.
7. Danach Schuljahr und Kalendertage.

# Nicht vorziehen

Die folgenden attraktiven Funktionen erst beginnen, wenn die zugrunde liegende
Domäne stabil ist:

- KI-Assistent
- automatische Notenvorschläge
- komplexe PDF-Liederbücher
- Präsentationsgenerator
- Drag-and-drop-Jahresplan
- externe API-Integrationen

# Fortschrittskonvention

In jeder Phase:

- `[ ]` nicht begonnen
- `[~]` in Arbeit
- `[x]` abgeschlossen
- `[!]` blockiert

Codex aktualisiert diesen Masterplan nur bei tatsächlich geprüftem Fortschritt.
