# Assessment-Evaluationsworkflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Einen dauerhaft speichernden, frei navigierbaren Assessment-
Auswertungsworkflow für Scan-Booklets, Schülerzuordnung und Aufgabenbewertung
implementieren.

**Architecture:** Die bestehende seitenweise Scan-Session bleibt der
Upload-/Erkennungsmechanismus. Ihr Abschluss materialisiert die erkannte
Struktur in `AssessmentBooklet`-, Fragment- und Review-Datensätze; der neue
GET-Workflow lädt diese Daten und schreibt Änderungen über autorisierte,
dünne Controller in Actions/Services. Die bestehende
`StudentAssessmentResult`-Tabelle bleibt das synchronisierte Ergebnisziel.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Inertia.js 3, Vue 3
Composition API, Bootstrap 5.3, Pest, Vitest, Docker über `./roo`.

**Spec:** `docs/superpowers/specs/2026-08-23-assessment-evaluation-workflow-design.md`

## Global Constraints

- Oberfläche, Fachbegriffe, Validierungsfehler und Hilfetexte sind deutsch.
- Quellcode, Klassennamen, Methodennamen und Datenbankspalten sind englisch.
- Schüler:innen werden nur über die aktuelle Unterrichtsgruppe angeboten.
- Fragmentbilder werden nur über autorisierte Controller ausgeliefert.
- Feste Namens- und Aufgaben-Ausschnitte verwenden das bekannte Template;
  OCR und Handschriftanalyse werden nicht eingeführt.
- Erwartungs-Wiederholungen werden als einzelne Bewertungszeilen gespeichert.
- Zusatzpunkte sind signierte numerische Werte ohne fachliche Grenzwerte.
- Tests verwenden eine isolierte Testdatenbank und setzen die
  Docker-Entwicklungsdatenbank nicht zurück.

---

### Task 1: Dauerhafte Booklet- und Bewertungsdaten

**Files:**
- Create: `src/database/migrations/2026_08_23_280000_create_assessment_booklet_tables.php`
- Create: `src/app/Models/AssessmentBooklet.php`
- Create: `src/app/Models/AssessmentBookletFragment.php`
- Create: `src/app/Models/AssessmentTaskReview.php`
- Create: `src/app/Models/AssessmentTaskReviewItem.php`
- Modify: `src/app/Models/Assessment.php`
- Modify: `src/app/Models/AssessmentTask.php`
- Modify: `src/app/Models/Student.php`
- Test: `src/tests/Feature/AssessmentEvaluationDataTest.php`

**Interfaces:** Die Modelle liefern `Assessment::booklets()`,
`AssessmentBooklet::fragments()`, `AssessmentBooklet::reviews()` und
`AssessmentTaskReview::items()` für die folgenden Tasks. Die Migration nutzt
integer-Fremdschlüssel wie die bestehenden Assessment-Tabellen, eindeutige
Review-Schlüssel je Booklet/Aufgabe sowie eine eindeutige aktive
Schülerzuordnung je Assessment.

- [ ] Failing Tests für Booklet, Fragment, Review, Wiederholungszeilen und
  Zuordnungsconstraint schreiben.
- [ ] `./roo test --filter=AssessmentEvaluationDataTest` ausführen und den
  erwarteten Fehlschlag bestätigen.
- [ ] Migration mit `open`/`discarded`, privaten Bildpfaden, Crop-Metadaten,
  signierten Dezimalpunkten und Foreign Keys implementieren.
- [ ] Eloquent-Modelle, Casts, Fillable-Felder und Assessment-/Task-/Student-
  Relationen ergänzen.
- [ ] Fokustests und `git diff --check` ausführen.
- [ ] Commit: `feat(assessment): speichere Auswertungsdaten`.

### Task 2: Sessionabschluss und feste Ausschnitte materialisieren

**Files:**
- Create: `src/app/Services/AssessmentEvaluation/MaterializeAssessmentScan.php`
- Create: `src/app/Services/AssessmentEvaluation/AssessmentTemplateCropper.php`
- Modify: `src/app/Services/AssessmentScan/AssessmentScanFragmentBuilder.php`
- Modify: `src/app/Services/AssessmentScan/AssessmentScanSessionStore.php`
- Modify: `src/config/filesystems.php`
- Test: `src/tests/Unit/AssessmentTemplateCropperTest.php`
- Test: `src/tests/Feature/AssessmentScanMaterializationTest.php`

**Interfaces:** `MaterializeAssessmentScan::handle(Assessment $assessment,
string $sessionId): Collection` erzeugt dauerhafte Booklets. Der
`AssessmentTemplateCropper` liefert PNG-Inhalte für den festen Namensausschnitt
der ersten Seite und für START-/END-Aufgabenausschnitte.

- [ ] Failing Tests für festen Namensrechteck-Crop, PAGE-Booklets,
  Aufgabenfragmente und Sessionlöschung schreiben.
- [ ] `documents`-Disk und zentrale Template-Cropkoordinaten ergänzen.
- [ ] Fragmentbuilder so erweitern, dass jedes Fragment Booklet und Aufgabe
  dauerhaft referenziert und Seite/y-Metadaten behält.
- [ ] Materialisierungsservice transaktional implementieren: Booklets mit
  laufender Nummer anlegen, PNGs speichern, Fragmente persistieren und die
  temporäre Session erst nach Erfolg löschen.
- [ ] Unit-/Feature-Tests und `git diff --check` ausführen.
- [ ] Commit: `feat(assessment): materialisiere Scan-Booklets`.

### Task 3: GET-Arbeitsansicht und autorisierte Booklet-API

**Files:**
- Modify: `src/routes/web.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Create: `src/app/Http/Requests/AssessmentBookletAssignmentRequest.php`
- Create: `src/app/Http/Requests/AssessmentTaskReviewRequest.php`
- Create: `src/app/Services/AssessmentEvaluation/AssignAssessmentBooklet.php`
- Create: `src/app/Services/AssessmentEvaluation/SaveAssessmentTaskReview.php`
- Test: `src/tests/Feature/AssessmentEvaluationWorkflowTest.php`

**Interfaces:** GET `assessments.evaluation` liefert Assessment-Aufgaben,
Gruppen-Schüler:innen, Booklets und Fortschrittszahlen. PUT/PATCH-Endpunkte
ändern Schülerzuordnung und `open`/`discarded`-Status. PUT
`assessments.task-reviews.update` speichert aufgeklappte Erwartungszeilen,
signierte Zusatzpunkte und Notizen. Ein autorisierter Fragment-Endpunkt liefert
private PNGs.

- [ ] Failing Feature-Tests für Navigation, Assessment-/Gruppenschutz,
  Mitgliedschaft, eindeutige Zuordnung, Verwerfen/Wiederherstellen und
  Fragmentzugriff schreiben.
- [ ] Routen und dünne Controller mit bestehender Gruppenautorisierung
  ergänzen.
- [ ] Assignment-/Status-Action transaktional implementieren.
- [ ] Review-Request und Review-Action mit serverseitiger Validierung für jede
  Erwartungs-Ausprägung implementieren.
- [ ] Props-Builder mit aktuellen Gruppenschüler:innen und bei jedem Request
  neu gemischten Task-Fragmenten ergänzen.
- [ ] Fokustests und `git diff --check` ausführen.
- [ ] Commit: `feat(assessment): ergänze Auswertungsendpunkte`.

### Task 4: Materialisierung in den bestehenden Uploadfluss integrieren

**Files:**
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/resources/js/Pages/Assessments/Form.vue`
- Modify: `src/resources/js/Features/AssessmentScan/scanClient.js`
- Test: `src/tests/Feature/AssessmentScanMaterializationTest.php`
- Test: `src/tests/frontend/assessmentScan.test.js`

- [ ] Completion-Tests auf dauerhafte Booklets/Fragmente, Sessionlöschung und
  Redirect auf `assessments.evaluation` erweitern.
- [ ] `completeScanSession` für seitenbasierte Sessions mit dem
  Materialisierungsservice verbinden und alte Client-Scan-Kompatibilität nur
  solange erhalten, wie bestehende Tests sie benötigen.
- [ ] Upload-Modal aus dem Editor in eine wiederverwendbare Komponente
  überführen; der Editor-Button wird ein GET-Link zur Auswertung.
- [ ] Scan-Client nach erfolgreichem Abschluss die dauerhafte Auswertungsseite
  öffnen lassen und Seiten-/Fehlerfortschritt unverändert beibehalten.
- [ ] Backend-/Frontend-Fokustests, Vite-Build und `git diff --check` ausführen.
- [ ] Commit: `feat(assessment): öffne Auswertung direkt`.

### Task 5: Scanbereich und manuelle Booklet-Zuordnung

**Files:**
- Modify: `src/resources/js/Pages/Assessment/Assess.vue`
- Create: `src/resources/js/Features/AssessmentEvaluation/BookletList.vue`
- Create: `src/resources/js/Features/AssessmentEvaluation/BookletAssignment.vue`
- Create: `src/resources/js/Features/AssessmentEvaluation/AssessmentScanUploadModal.vue`
- Modify: `src/resources/js/locales/de.js`
- Test: `src/tests/frontend/assessmentEvaluation.test.js`

- [ ] Vitest-Tests für frei wählbare Bereiche, Namensausschnitt,
  Gruppenmitglieder, Zuordnung, Verwerfen/Wiederherstellen und Uploadstatus
  schreiben.
- [ ] Upload-Modal mit Sessionstatus, aktueller Seite, Markern, Fragmenten,
  Retry und barrierefreiem Processing-Status extrahieren.
- [ ] Bookletliste und Zuordnungsansicht mit Leerzuständen, Fortschrittszahlen,
  Serverfehlern und deutschen Lokalisierungsschlüsseln implementieren.
- [ ] Alte markerbasierte `Assess.vue`-Darstellung durch die Auswertungshülle
  ersetzen und Warnungen/Diagnoseinformationen erhalten.
- [ ] Vitest und Vite-Build ausführen.
- [ ] Commit: `feat(assessment): ergänze Booklet-Zuordnung`.

### Task 6: Aufgabenbewertung und Ergebnis-Synchronisierung

**Files:**
- Create: `src/app/Services/AssessmentEvaluation/ExpectationOccurrences.php`
- Create: `src/app/Services/AssessmentEvaluation/SyncStudentAssessmentResult.php`
- Create: `src/resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue`
- Modify: `src/resources/js/Pages/Assessment/Assess.vue`
- Modify: `src/resources/js/locales/de.js`
- Test: `src/tests/Unit/ExpectationOccurrencesTest.php`
- Test: `src/tests/Feature/AssessmentTaskReviewTest.php`
- Test: `src/tests/frontend/assessmentEvaluation.test.js`

**Interfaces:** `ExpectationOccurrences::forTask(AssessmentTask $task): Collection`
expandiert Wiederholungen zu `{expectation_id, occurrence, text, points}`.
`SyncStudentAssessmentResult::handle(AssessmentBooklet $booklet,
AssessmentTask $task): void` summiert Erwartungs- und Zusatzpunkte für die
zugeordnete Schüler:in. `TaskEvaluation` zeigt die zufällig gelieferten
Fragmente und speichert jeden Booklet-/Aufgabenfall separat.

- [ ] Failing Tests für Wiederholungen, volle/teilweise Punkte, Erklärungen,
  signierte Zusatzpunkte und Resultat-Synchronisierung schreiben.
- [ ] Occurrence-Service und Review-Persistenz mit Ergebnis-Synchronisierung
  in einer Transaktion implementieren.
- [ ] Beim Ändern zu `unassigned` oder `discarded` das betroffene
  Schülerergebnis leeren bzw. neu berechnen.
- [ ] `TaskEvaluation.vue` mit neu gemischten Fragmenten je Aufgabenöffnung,
  Erwartungszeilen, Zusatzpunkten, Notizen und Fall-Fehlerstatus implementieren.
- [ ] Aufgabenwahl und offene/erledigte Zähler in `Assess.vue` integrieren,
  ohne Reihenfolge zu erzwingen.
- [ ] Backend-/Frontend-Tests, Vite-Build und `git diff --check` ausführen.
- [ ] Commit: `feat(assessment): ermögliche Aufgabenbewertung`.

### Task 7: Regression, Dokumentation und Abschlussprüfung

**Files:**
- Modify: `build/masterplan.md`
- Modify: `src/tests/Feature/AssessmentEvaluationWorkflowTest.php`
- Modify: `src/tests/frontend/assessmentEvaluation.test.js`

- [ ] Regressionstests für mehrere PDFs, mehrere PAGE-Marker,
  unvollständige Markerpaare, wiederholte Uploads und beliebige Bereichsfolge
  ergänzen.
- [ ] `./roo test --compact && ./roo npm run build && git diff --check`
  ausführen.
- [ ] Datenfluss Controller → Inertia → Vue prüfen und sicherstellen, dass
  Fragment-URLs, Manifeste und Logs keine Schülerdaten preisgeben.
- [ ] Phase 10 in `build/masterplan.md` auf den implementierten Workflow
  aktualisieren und die weiterhin ausgenommenen OCR-/Automatikfunktionen
  dokumentieren.
- [ ] Commit: `docs(assessment): aktualisiere Lernstandserhebung`.
