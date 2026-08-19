# Roo – Masterplan

Stand: 6. August 2026

## Zielbild

Roo unterstützt Lehrkräfte dabei, Religionsunterricht für ein vollständiges
Schuljahr zu planen, vorzubereiten, durchzuführen, zu dokumentieren und
auszuwerten.

Die Entwicklung erfolgt in kleinen, testbaren vertikalen Schnitten. Ein Modul
gilt nicht als fertig, nur weil Tabellen und CRUD-Seiten vorhanden sind. Jeder
Meilenstein soll einen real benutzbaren Arbeitsablauf ermöglichen.

## Verbindliche UI-Grundsätze

- Die Topbar enthält keine Seitentitel. Aktionen der aktuellen Seite dürfen
  dort weiterhin als kompakte Werkzeugleiste erscheinen.
- Seitenköpfe zeigen ausschließlich die jeweilige Überschrift. Rücksprunglinks
  und beschreibende Unterzeilen gehören nicht in den Seitenkopf; die Navigation
  erfolgt über die globale Seitennavigation und kontextbezogene Aktionen.
- Angemeldete Ansichten enthalten in der Topbar unmittelbar vor dem Profilmenü
  eine globale Suche. Sie durchsucht nur sichtbare, mandantengeschützte Daten
  und führt zu einer eigenen Ergebnisansicht unter `/suche`.
- Karten in Planungs- und Zuordnungsansichten, insbesondere Curriculum-
  Drag-and-drop-Spalten und Unterrichtseinheiten, erhalten eine dezente
  Hintergrundfläche und ausreichenden Kontrast zum Seitenhintergrund.

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
- Die Docker-/PostgreSQL-Entwicklungsdatenbank ist persistent und darf niemals
  automatisch zurückgesetzt werden. Ein Reset, `migrate:fresh`, `db:wipe` oder
  vergleichbare destruktive Vorgänge sind nur nach ausdrücklicher Anweisung
  erlaubt.
- Tests verwenden ausschließlich eine isolierte In-Memory- oder separate
  Testdatenbank und niemals die Docker-Entwicklungsdatenbank.

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

Fortschritt: `[~]` Importmodell, relationale Curriculumdaten, eine
bearbeitbare Arbeitsoberfläche für eigene Curricula, die
Curriculum-Schulzuordnung, ein read-only Curriculumvergleich und das Kopieren
eigener Fassungen sind umgesetzt. Weitere Fassungsfunktionen folgen im
nächsten Schnitt.

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
- Curriculum-Vergleich: zwei sichtbare Curricula mit Einheiten, Jahrgängen,
  Zeitbedarf und Kompetenzanzahl gegenüberstellen
- eigene Curriculumfassung als vollständige, bearbeitbare Kopie anlegen

---

## Phase 4 – Klassen, Schüler:innen und Unterrichtsgruppen

Fortschritt: `[~]` Unterrichtsgruppen, Schüler:innen, zeitliche
Mitgliedschaften, Mehrfach-Jahrgangsstufen, Stundenplantermine und
Curriculumzuordnungen sind als erster vertikaler Schnitt umgesetzt.

### Ziel

Reale Lerngruppen eines Schuljahres abbilden.

### Fachobjekte

- Student
- TeachingGroup
- TeachingGroupMembership
- TimetableSlot
- TeachingGroupCurriculum

### Funktionen

- Schüler:innen per CSV importieren und manuell anlegen, bearbeiten und löschen
- organisationsgeschützter CSV-Export von Schüler:innen einschließlich Schule
  und zugehöriger Schuljahre
- zentrale Schüler:innenübersicht mit organisationsweiter Suche, Filtern,
  Sortierung und Pagination
- Gruppe aus mehreren Klassen zusammensetzen
- Eintritt/Austritt zeitlich abbilden
- Stundenplan mit mehreren regelmäßigen Terminen
- primäres und ergänzende Curricula zuordnen

Curricula werden ausschließlich direkt an der Unterrichtsgruppe zugeordnet.
Die Zuordnung ist dadurch automatisch an deren konkretes Schuljahr gebunden;
Gültigkeitsdaten sind nicht erforderlich. Schulweite Curriculum-Zuordnungen
sind kein aktiver Planungsablauf mehr.


Das Stundenraster wird schulweit einmal pro Periodennummer (1 bis 12) mit
Beginn gepflegt. Jede Unterrichtsstunde dauert 45 Minuten; das Ende wird
berechnet. Unterrichtsgruppen wählen daraus Wochentag und Periodennummer,
beispielsweise Dienstag 1 und 2. Die Bearbeitung konkreter Stunden und
Perioden gehört zu den späteren Planungs- und Stundenphasen.

Die Klassenbezeichnung ist bewusst ein Feld an `Student` (`class_name`), keine
separat zu pflegende Entität. Eine Unterrichtsgruppe hat einen freien Namen,
kann ohne Mitglieder angelegt werden und erhält mindestens eine, beliebig viele
Jahrgangsstufen. So kann etwa die Gruppe „2ab“ Schüler:innen aus „2a“ und „2b“
enthalten.

### Datenschutz

- Policies und Mandantenscopes vollständig
- Export- und Löschpfade vorbereiten
- minimierter Schülerindex in Meilisearch: Nachname, Vorname, tatsächliche
  Klasse und zugehörige Unterrichtsgruppen; Notizen, Beobachtungen und
  Bewertungen werden nicht indexiert
- Der Index ist mandantengefiltert, intern zugriffsbeschränkt und muss bei
  Änderungen oder Löschungen synchron zur relationalen Quelle aktualisiert
  werden
- Das ist eine Maßnahme zur Datenminimierung: Ein Suchindex ist eine zusätzliche,
  dauerhaft gespeicherte Kopie außerhalb der relationalen Quelle. Bei einer
  Fehlkonfiguration, einem zu weit gefassten Suchschlüssel, Logs/Backups oder
  einem unvollständigen Löschlauf könnten Namen, Klassen und Suchfragmente
  darüber zusätzlich zugänglich werden. Meilisearch würde die Daten nicht von
  selbst veröffentlichen, aber die Angriffs- und Fehlerfläche vergrößern.
- Schüler:innen-CSV erwartet `Vorname`, `Nachname` und `Klasse`; `Notizen` ist optional.
- Mitgliedschaften können mit Beginn und Ende erfasst werden.

### UI-Konvention für Detailseiten

Auf einer Detailseite gibt es keine untergeordneten Speichern- oder
Absende-Buttons. Änderungen am Hauptobjekt werden über die zentrale
Seitenaktion gespeichert; eigenständige Abläufe wie Schüler:innen anlegen,
importieren oder zuordnen öffnen ein Modal. Das Anlegen bietet zusätzlich
„Speichern und neu“.

---

## Phase 5 – Wiederverwendbare Unterrichtsinhalte

Fortschritt: `[x]` UE-, Stunden- und Phasen-Vorlagen, wiederkehrende Phasen,
private Anhänge, Suche/Filter, Kopieren und versioniertes Aktualisieren sowie
relationale Sozialformen, Tags und Materialbestandteile sind umgesetzt.

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

Fortschritt: `[x]` Jahrespläne, visuelle Zeitachse, Ferien-/Ausnahmetage,
Drag-and-drop und Tastaturverschiebung, Teilung, Stunden-Erzeugung,
Durchführungsstatus, Planungsprüfungen und Revisionshistorie sind umgesetzt.

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

### Phase 6.1 – zentraler Jahresplanungsarbeitsbereich

Fortschritt: `[x]` Der dreispaltige Arbeitsbereich mit unabhängigen eigenen
Unterrichtseinheiten, Curriculum-Übernahme, generierten Unterrichtsslots,
Lesson-Editor, Kompetenzabdeckung, Puffer-/Ausfallstatus, Reflow, Undo und
zugänglichen Alternativaktionen ist umgesetzt. Curriculum- und
Bildungsplandaten bleiben dabei unverändert.

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

## UI-Konventionen

Die Oberfläche folgt auf Detailseiten einer festen Aktionslogik:

- Die Topbar enthält nur die Seiten-Navigation und die zentrale Aktion für das
  Hauptobjekt.
- Der Seitentitel steht im Inhaltsbereich, nicht zusätzlich in der Topbar.
- Zurück bzw. Schließen wird als X-Symbol dargestellt und mit Tooltip sowie
  zugänglichem Label versehen.
- „Änderungen speichern“ speichert das Hauptobjekt einschließlich seiner
  zusammengehörigen Detailänderungen und schließt anschließend zurück zur
  übergeordneten Übersicht.
- Fachliche Nebenaktionen stehen nicht in der Topbar. Sie werden rechts neben
  der Überschrift der jeweils zuständigen Karte platziert.
- Nebenaktionen verwenden kompakte sekundäre Buttons (`btn-sm
  btn-outline-primary`) und kurze Beschriftungen, etwa „Zuordnen“, „Anlegen“
  oder „Importieren“.
- Eigenständige Nebenabläufe wie Anlegen, Importieren, Zuordnen und Bearbeiten
  öffnen ein Modal. Das Modal enthält die Eingaben und die zugehörige
  Speichern-/Bestätigungsaktion.
- Auf der Detailseite gibt es keine untergeordneten Speichern-Buttons für
  das Hauptformular. Auswahl- und Rasterbuttons dürfen lokale Zustände ändern;
  gespeichert wird über die zentrale Seitenaktion.
- Kartenaktionen bleiben responsiv: Auf großen Bildschirmen stehen sie neben
  der Überschrift, auf kleinen Bildschirmen dürfen sie umbrechen.

### UI: Jahresplanung und Planungsebenen

Die Jahresplanung ist der zentrale Arbeitsbereich für die konkrete Unterrichtsplanung.

Auf Desktop folgt sie grundsätzlich einem Drei-Spalten-Modell:

1. **Jahresplan** – konkrete Unterrichtstermine und eingeplante Stunden,
2. **Meine Unterrichtseinheiten** – die tatsächlich von der Nutzer:in verwendeten und bearbeitbaren UEs,
3. **Curricula** – Unterrichtsvorschläge aus den für die Gruppe ausgewählten Curricula.

Die Curriculum-Spalte ist schmaler als die beiden Arbeitsbereiche und standardmäßig geöffnet, aber einklappbar.

Die UI muss die fachlichen Ebenen strikt trennen:

`EducationPlan → Curriculum → eigene Unterrichtsplanung → Jahresplan`

Curriculum-UEs und Curriculum-Stundenvorschläge sind niemals direkt planbar. Sie müssen zunächst als eigene, danach unabhängig bearbeitbare UE bzw. Stunde instanziiert werden.

Drag-and-drop ist die bevorzugte Desktop-Interaktion für Übernahme, Einplanung und Sortierung, darf aber niemals die einzige Möglichkeit zur Durchführung einer Aktion sein.

Verschachtelte Modals sind zu vermeiden. Für Detailbearbeitung innerhalb eines Modals (z. B. Phase innerhalb eines Stundenentwurfs) sind bevorzugt Offcanvas-/Side-Panel-Interaktionen zu verwenden.

Die UI arbeitet bei der Jahresplanung ausschließlich mit ganzen Schulstunden. Eine minutengenaue Verteilung von Inhalten über Unterrichtsstundengrenzen ist nicht vorgesehen.

Kompetenzbeziehungen werden über stabile fachliche Relationen/IDs abgebildet, niemals durch Textvergleich. Die UI muss langfristig zwischen Curriculum-Abdeckung und der maßgeblichen EducationPlan-Abdeckung unterscheiden können.

