# LSE-Kategorien, Erwartungslisten und manuelle Exemplare Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lernstandserhebungen erhalten Notenmix-Kategorien, Erwartungslisten als nicht druckbare Prozesskompetenz-Aufgaben und manuelle, direkt Schüler:innen zugeordnete Exemplare.

**Architecture:** Assessment-Kategorien referenzieren aktive Notenmix-Bestandteile derselben Unterrichtsgruppe und speichern zusätzlich einen historischen Bezeichnungs-Snapshot. `expectation_list` nutzt den bestehenden Erwartungs- und Review-Workflow, wird aber aus Dokumentexports ausgeschlossen. Manuelle Exemplare bleiben `AssessmentBooklet`-Datensätze ohne Fragmente; die Evaluation erzeugt dafür virtuelle Bewertungsziele und verwendet die bestehende Review-/Ergebnissynchronisierung.

**Tech Stack:** Laravel 13, PHP 8.4, Eloquent, Pest, Vue 3 Composition API, Inertia.js 3, Bootstrap 5.3, Vitest, Vite, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-08-28-assessment-categories-expectation-lists-manual-booklets-design.md`

## Global Constraints

- Die Oberfläche, Hilfetexte, Validierungsfehler und fachlichen Begriffe bleiben deutsch.
- Prozesskompetenz wird serverseitig über `EducationPlanCompetency.area.kind === 'process'` geprüft.
- Erwartungslisten erscheinen niemals in einem Dokumentexport.
- Ein manuelles Exemplar wird beim Anlegen direkt einer Schülerin bzw. einem Schüler zugeordnet.
- Kategorien dürfen nicht hart gelöscht werden, solange historische LSEs sie verwenden.
- Schüler:innendaten gelangen nicht in Logs oder öffentliche URLs.
- Tests verwenden ausschließlich die isolierte SQLite-Testdatenbank über `./roo test`.
- Vor Abschluss laufen fokussierte Backend-/Frontend-Tests, Vite-Build und `git diff --check`.

---

## Datei- und Modulkarte

- `src/database/migrations/`: Assessment-Kategorie, stabile/inaktive Gruppenkategorien und Booklet-Herkunft.
- `src/app/Models/TeachingGroupGradeComponent.php`: bestehende Kategorie-Entität um Stabilitäts-/Aktivitätsregeln ergänzen.
- `src/app/Models/Assessment.php`: Kategoriebeziehung und Snapshot.
- `src/app/Models/AssessmentBooklet.php`: `scan`-/`manual`-Herkunft.
- `src/app/Http/Controllers/AssessmentController.php:validatedAssessment()`: Gruppenscope und Kategorieoption.
- `src/app/Http/Controllers/AssessmentController.php`: Formularprops, Kategoriepersistenz, manuelle Exemplare, virtuelle Bewertungsziele und Exportfilter.
- `src/app/Http/Requests/AssessmentBookletManualRequest.php`: direkte Schüler:innen-Zuordnung.
- `src/app/Http/Controllers/ResourceLibraryController.php` und `src/app/Http/Controllers/LessonWorkspaceController.php`: Prozesskompetenz-Validierung für `expectation_list` in allen Task-Erstellungswegen.
- `src/resources/js/Pages/Assessments/Form.vue`: Kategorieauswahl und Erwartungslisten-Konfiguration.
- `src/resources/js/Pages/Assessment/Assess.vue`: Modal zum manuellen Exemplar und Darstellung ohne Scanbild.
- `src/resources/js/Pages/AssessmentTask/Edit.vue` sowie Bibliotheks-/Lesson-Task-Flows: neuer Task-Typ und Prozessfilter.
- `src/app/Services/AssessmentEvaluation/`: nur nötige Registry-/Synchronisierungsanpassungen; generische Erwartungsbewertung wiederverwenden.
- `src/resources/js/i18n/de.js`: sämtliche neuen deutschen Texte zentral.
- `src/tests/Feature/AssessmentCategoriesTest.php`: Kategorien und historische Stabilität.
- `src/tests/Feature/ExpectationListTest.php`: Task-Typ, Prozesskompetenz und Exportausnahme.
- `src/tests/Feature/ManualAssessmentBookletTest.php`: direkt zugeordnete Exemplare und Review-Workflow.
- `src/tests/frontend/assessmentExpectationList.test.js`: UI-Verträge für Typauswahl, Kategorie und manuelles Modal.

## Task 1: Datenmodell für Kategorien und Booklet-Herkunft

**Files:**
- Create: `src/database/migrations/2026_08_28_110000_add_assessment_categories_and_booklet_sources.php`
- Create or modify: `src/app/Models/TeachingGroupGradeComponent.php`
- Modify: `src/app/Models/Assessment.php`
- Modify: `src/app/Models/AssessmentBooklet.php`
- Modify: `src/app/Models/TeachingGroup.php`
- Test: `src/tests/Feature/AssessmentCategoriesTest.php`

**Interfaces:**
- Produces `Assessment::gradeComponent(): BelongsTo`, `Assessment::gradeComponentLabel` snapshot access, `AssessmentBooklet::source`, and stable active/inactive grade-component records.
- Existing `TeachingGroup::gradeComponents()` callers continue receiving active components by default; historical queries can explicitly include inactive records.

- [ ] **Step 1: Write the failing model/constraint tests**

In `AssessmentCategoriesTest.php`, create local Pest fixtures with an
organization, user, school, school year, group, grade component and
assessment. Add one test that saves the component ID and label, marks the
component inactive, and asserts that the assessment still exposes the stored
label while the active relation is absent. Add a second test that creates an
`AssessmentBooklet` with `source: 'manual'`, a real group student, no fragment,
and asserts the source and empty fragment relation.

- [ ] **Step 2: Run the focused tests and verify they fail because the columns/relations do not exist**

Run: `./roo test --compact tests/Feature/AssessmentCategoriesTest.php`

Expected: FAIL with missing model attributes/columns or relations.

- [ ] **Step 3: Add reversible migrations and model relations**

Add nullable `grade_component_id`, nullable `grade_component_label` and `source` with default `scan`. Add `is_active` and a stable per-group key to the existing grade-components table. Preserve existing rows and create no public identifiers containing student data. Keep referenced components as inactive rows rather than deleting them.

- [ ] **Step 4: Run the focused tests and verify they pass**

Run: `./roo test --compact tests/Feature/AssessmentCategoriesTest.php`

Expected: PASS.

- [ ] **Step 5: Run targeted formatting and commit**

Run: `./roo pint app/Models/Assessment.php app/Models/AssessmentBooklet.php app/Models/TeachingGroup.php app/Models/TeachingGroupGradeComponent.php tests/Feature/AssessmentCategoriesTest.php && git diff --check`

Commit: `git add src/database/migrations src/app/Models tests/Feature/AssessmentCategoriesTest.php && git commit -m "feat(lse): Kategorien und Booklet-Herkunft modellieren"`

## Task 2: Kategorieauswahl im Assessment-Formular

**Files:**
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php:validatedAssessment()`
- Modify: `src/resources/js/Pages/Assessments/Form.vue`
- Modify: `src/resources/js/Pages/TeachingGroups/Show.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/Feature/AssessmentCategoriesTest.php`

**Interfaces:**
- `formProps()` provides `gradeComponents` containing only active components for the current group.
- Assessment payload accepts nullable `grade_component_id`; the server verifies its `teaching_group_id` and active state.

- [ ] **Step 1: Add failing request and Inertia tests**

Cover creation with `grade_component_id`, explicit `null` (“Keine Zuordnung”), foreign-group rejection, inactive-component rejection, edit persistence, and category presentation in the group assessment list.

- [ ] **Step 2: Run the tests and verify category behavior is missing**

Run: `./roo test --compact tests/Feature/AssessmentCategoriesTest.php`

Expected: FAIL because form props, validation, and persistence do not yet expose the category.

- [ ] **Step 3: Implement scoped validation and snapshot persistence**

Load active group components in `formProps()`. In `validatedAssessment()`, validate nullable integer input, load the matching active component through the current group, and save both its ID and current label. Save `null` for “Keine Zuordnung”. Do not accept an ID from another group or an inactive component.

- [ ] **Step 4: Add the German category selector**

Place a Bootstrap select in `Form.vue` with `Keine Zuordnung` plus active components. Show the selected category in `TeachingGroups/Show.vue`. Use only keys from `de.js`; preserve existing return-tab behavior.

- [ ] **Step 5: Verify and commit**

Run: `./roo test --compact tests/Feature/AssessmentCategoriesTest.php && ./roo pint --test app/Http/Controllers/AssessmentController.php app/Http/Requests tests/Feature/AssessmentCategoriesTest.php && git diff --check`

Commit: `git add src/app src/resources/js src/tests/Feature/AssessmentCategoriesTest.php && git commit -m "feat(lse): Assessment-Kategorien auswählen"`

## Task 3: Erwartungsliste mit Prozesskompetenz

**Files:**
- Modify: `src/app/Models/AssessmentTask.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: task creation/update request or validation classes used by `ResourceLibraryController` and `LessonWorkspaceController`
- Modify: `src/resources/js/Pages/AssessmentTask/Edit.vue`
- Modify: competency-picker/task-library components that expose task types
- Modify: `src/app/Http/Controllers/AssessmentController.php` export task mapping
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/Feature/ExpectationListTest.php`
- Test: `src/tests/frontend/assessmentExpectationList.test.js`

**Interfaces:**
- `task_type === 'expectation_list'` is accepted only with an `education_plan_competency_id` whose area kind is `process`.
- Export task mapping excludes `expectation_list`; evaluation task data still includes its expectations.

- [ ] **Step 1: Write failing backend tests**

Build one local fixture containing an organization, authenticated user,
school, school year, teaching group, one process-area competency and one
content-area competency. Post a task payload with
`task_type: 'expectation_list'`, the process competency ID, a title and one
expectation; assert redirect and one persisted task. Post the same payload with
the content competency ID and assert a validation error on
`education_plan_competency_id`. Add an assessment containing the new task,
call the existing download endpoint, and assert the generated response does
not contain the expectation-list task.

- [ ] **Step 2: Run the tests and verify the new type is rejected or exported**

Run: `./roo test --compact tests/Feature/ExpectationListTest.php`

Expected: FAIL because the type and process validation/export filter do not exist.

- [ ] **Step 3: Implement the backend type and invariant**

Add the type to the accepted task-type lists. At every write boundary used by library, lesson, and assessment task creation, load the selected education-plan competency and reject any area whose `kind` is not `process`. Keep the existing expectation persistence and points/repetitions semantics.

- [ ] **Step 4: Implement the dedicated editor presentation**

Offer “Erwartungsliste” in the task type selector. When selected, hide print-only content fields, show only process competencies, and retain the existing expectations editor. Add a frontend test asserting the type label and process-only selection contract.

- [ ] **Step 5: Exclude the type from document output and verify generic evaluation data**

Filter the type before constructing `AssessmentDocument` tasks. Keep it in the evaluation payload with expectations and maximum points. Verify that generic `TaskEvaluation` renders expectation rows and saves review items without a scan-specific evaluator.

- [ ] **Step 6: Run checks and commit**

Run: `./roo test --compact tests/Feature/ExpectationListTest.php && ./roo npm run test:unit -- --run tests/frontend/assessmentExpectationList.test.js && ./roo pint --test app/Models/AssessmentTask.php tests/Feature/ExpectationListTest.php && git diff --check`

Commit: `git add src/app src/resources/js src/tests && git commit -m "feat(lse): Erwartungslisten für Prozesskompetenzen"`

## Task 4: Manuelle Exemplare mit direkter Zuordnung

**Files:**
- Create: `src/app/Http/Requests/AssessmentBookletManualRequest.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/routes/web.php`
- Modify: `src/app/Services/AssessmentEvaluation/AssignAssessmentBooklet.php` only if shared checks need a manual source branch
- Modify: `src/resources/js/Pages/Assessment/Assess.vue`
- Create: `src/resources/js/Features/AssessmentEvaluation/ManualBookletModal.vue`
- Modify: `src/resources/js/Features/AssessmentEvaluation/BookletList.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/Feature/ManualAssessmentBookletTest.php`
- Test: `src/tests/frontend/assessmentExpectationList.test.js`

**Interfaces:**
- `POST /unterrichtsgruppen/{teachingGroup}/lernstandserhebungen/{assessment}/auswertung/booklets/manuell` accepts required `student_id` and creates the next open manual booklet.
- Manual booklet payload includes `source: manual`, `student_id`, `fragment_count: 0`, and no scan URL.

- [ ] **Step 1: Write failing endpoint and authorization tests**

Cover direct student assignment, foreign-student rejection, assessment/group mismatch, sequential numbering, and duplicate active booklet conflict. Assert that a created manual booklet has no fragments.

- [ ] **Step 2: Run the tests and verify the endpoint is absent**

Run: `./roo test --compact tests/Feature/ManualAssessmentBookletTest.php`

Expected: FAIL with missing route/controller behavior.

- [ ] **Step 3: Implement the request, route, and transactional creation**

Authorize the group update policy, verify the student belongs to the group, lock the assessment while calculating the next booklet number, and create an open manual booklet with the selected student. Reuse the existing active-student uniqueness/conflict behavior and do not create files or fragments.

- [ ] **Step 4: Build the simple German modal**

Add a “Manuelles Exemplar anlegen” action in the evaluation page. The modal contains only a required student select, cancel, and create actions. On success, preserve the current evaluation tab and refresh the booklet list.

- [ ] **Step 5: Verify and commit**

Run: `./roo test --compact tests/Feature/ManualAssessmentBookletTest.php && ./roo npm run test:unit -- --run tests/frontend/assessmentExpectationList.test.js && git diff --check`

Commit: `git add src/app src/routes src/resources/js src/tests && git commit -m "feat(lse): manuelle Exemplare anlegen"`

## Task 5: Virtuelle Bewertungsziele ohne Scanfragment

**Files:**
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/resources/js/Pages/Assessment/Assess.vue`
- Modify: `src/resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue`
- Modify: `src/app/Services/AssessmentEvaluation/SaveAssessmentTaskReview.php` only if validation needs manual targets
- Test: `src/tests/Feature/ManualAssessmentBookletTest.php`
- Test: `src/tests/frontend/assessmentExpectationList.test.js`

**Interfaces:**
- Evaluation `taskFragments` contains a review target with `fragment_id: null` for every manual booklet/task pair and retains `booklet_id`, `assessment_task_id`, and existing review data.
- `PUT /unterrichtsgruppen/{teachingGroup}/lernstandserhebungen/{assessment}/auswertung/booklets/{booklet}/tasks/{assessmentTask}/review` accepts a manual booklet when the task belongs to the assessment; scan booklets still require a matching fragment.

- [ ] **Step 1: Write failing review-flow tests**

Create an assessment with an expectation list, one directly assigned manual booklet, and expectations. Assert evaluation props expose a target without an image and that a review request stores expectation items and creates `StudentAssessmentResult` for the assigned student.

- [ ] **Step 2: Run the tests and verify current fragment authorization blocks the review**

Run: `./roo test --compact tests/Feature/ManualAssessmentBookletTest.php`

Expected: FAIL at the current `booklet->fragments()` authorization check or with no task target in the evaluation props.

- [ ] **Step 3: Add virtual targets in the controller**

For manual open booklets, iterate the assessment’s tasks and append target arrays with `fragment_id: null`, no image URL, and the existing review. Keep scanned targets fragment-based and preserve ordering/progress semantics.

- [ ] **Step 4: Relax only the manual review authorization and render path**

Permit a review when the booklet source is `manual` and the task belongs to the assessment. Keep the fragment existence requirement for scan booklets. Make image-specific UI conditional on an image/fragment so generic expectation rows render cleanly without a scan.

- [ ] **Step 5: Verify result synchronization and regressions**

Run: `./roo test --compact tests/Feature/ManualAssessmentBookletTest.php tests/AssessmentEvaluationWorkflowTest.php tests/Feature/TeachingGroupAssessmentCleanupTest.php && ./roo npm run test:unit -- --run tests/frontend/assessmentEvaluation.test.js tests/frontend/assessmentExpectationList.test.js`

Expected: all focused tests pass; scanned booklet assignment and review behavior remains unchanged.

- [ ] **Step 6: Run formatting and commit**

Run: `./roo pint --test app/Http/Controllers/AssessmentController.php app/Services/AssessmentEvaluation tests/Feature/ManualAssessmentBookletTest.php && git diff --check`

Commit: `git add src/app src/resources/js src/tests && git commit -m "feat(lse): manuelle Exemplare bewerten"`

## Task 6: Integrated verification and handoff

**Files:**
- Modify: `docs/superpowers/plans/2026-08-28-assessment-categories-expectation-lists-manual-booklets.md` to mark completed steps only during execution.
- Modify: `docs/superpowers/specs/2026-08-28-assessment-categories-expectation-lists-manual-booklets-design.md` only if an approved implementation detail changes.

- [ ] **Step 1: Run the complete focused vertical slice**

Run:

```bash
./roo test --compact tests/Feature/AssessmentCategoriesTest.php tests/Feature/ExpectationListTest.php tests/Feature/ManualAssessmentBookletTest.php tests/Feature/AssessmentEvaluationWorkflowTest.php
./roo npm run test:unit -- --run tests/frontend/assessmentEvaluation.test.js tests/frontend/assessmentExpectationList.test.js
./roo npm run build
git diff --check
```

Expected: all listed tests and the build exit successfully. Report unrelated full-suite failures separately rather than attributing them to this feature.

- [ ] **Step 2: Inspect the final diff and migration reversibility**

Confirm no student data is logged, no public student identifiers are introduced, all new UI copy comes from `de.js`, and `down()` methods remove only the new schema elements.

- [ ] **Step 3: Run the full safe suite before completion**

Run: `./roo test --compact`

Expected: record exact pass/fail counts and any unrelated failures. Do not claim the full suite is green without the command output.
