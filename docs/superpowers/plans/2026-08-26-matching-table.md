# Zuordnungstabelle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Den AssessmentTask-Typ `matching_table` als bearbeitbare, speicherbare, exportierbare und evaluierbare Zuordnungstabelle umsetzen.

**Architecture:** Die Tabellenstruktur bleibt im bestehenden `AssessmentTask.content`-Array. Persistente Task-Daten enthalten Kategorien, Texte und korrekte Kategorie-IDs; die Evaluation erzeugt daraus temporäre Erwartungszeilen und speichert nur die Bewertungsentscheidungen über den bestehenden Review-Workflow.

**Tech Stack:** Laravel 13, PHP 8.4, Pest, Vue 3, Inertia, Vitest, PHPWord/ODT.

**Spec:** `docs/superpowers/specs/2026-08-26-matching-table-design.md`

## Global Constraints

- Deutsche UI-Texte werden zentral über `resources/js/i18n/de.js` gepflegt.
- Tests laufen ausschließlich über `./roo`.
- Fachlogik bleibt im Backend bzw. in bestehenden Evaluation-Komponenten nachvollziehbar.
- Temporäre Evaluationserwartungen dürfen nicht als `AssessmentExpectation` persistiert werden.
- Vor Abschluss werden fokussierte Backend-/Frontend-Tests, Build und `git diff --check` ausgeführt.

---

### Task 1: Task-Typ und Persistenz

**Files:**
- Modify: `src/app/Http/Controllers/ResourceLibraryController.php`
- Modify: `src/app/Http/Controllers/LessonWorkspaceController.php`
- Modify: `src/app/Models/AssessmentTask.php`
- Test: `src/tests/Feature/ResourceLibraryTest.php`

**Interfaces:**
- Consumes: Request payload `content.categories`, `content.rows`, `content.points_per_correct_answer`, `content.matching_scoring_mode`.
- Produces: Validated and normalized `matching_table` content and generated maximum points for both scoring modes.

- [ ] **Step 1: Write failing persistence tests**

Add tests that create a `matching_table` with two categories and two text rows, including a row with two correct categories, then assert content persistence and maximum points for `per_category` and `complete_row`. Add an update test asserting category removal does not retain removed category IDs.

- [ ] **Step 2: Run the focused tests and verify the expected failure**

Run:

```bash
./roo test --filter='speichert Zuordnungstabellen|aktualisiert Zuordnungstabellen'
```

Expected: validation or maximum-point assertions fail because `matching_table` is not yet handled.

- [ ] **Step 3: Implement validation and point calculation**

Accept the matching-table fields in both assessment-task controller paths, validate stable category/row IDs and category references, preserve the content, and calculate `max_points` as correct relation count times the point value in `per_category`, or row count times the point value in `complete_row`.

- [ ] **Step 4: Run the focused tests and verify they pass**

Run the same command and confirm all new persistence assertions pass.

- [ ] **Step 5: Commit the task**

```bash
git add src/app/Http/Controllers/ResourceLibraryController.php src/app/Http/Controllers/LessonWorkspaceController.php src/app/Models/AssessmentTask.php src/tests/Feature/ResourceLibraryTest.php
git commit -m "feat(assessment): speichert Zuordnungstabellen"
```

### Task 2: AssessmentTask editor

**Files:**
- Modify: `src/resources/js/Pages/AssessmentTask/Edit.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/frontend/assessmentTaskEditor.test.js`

**Interfaces:**
- Consumes: The `matching_table` content shape from Task 1.
- Produces: Editor normalization, category/row add-remove controls, scoring-mode selection, and save payload.

- [ ] **Step 1: Write failing editor tests**

Add tests asserting that selecting `matching_table` initializes one category and one text row, that adding a category adds a checkbox to each row, and that saving emits `category_ids`, `points_per_correct_answer`, and `matching_scoring_mode` without generic table fields.

- [ ] **Step 2: Run the focused frontend tests and verify failure**

Run:

```bash
./roo npm run test:unit -- --run tests/frontend/assessmentTaskEditor.test.js
```

Expected: the new assertions fail because the editor has no matching-table branch.

- [ ] **Step 3: Implement the editor branch**

Add factories and normalization for categories and text rows, expose the two scoring modes with German labels, render the category matrix with checkbox bindings, and include matching content in the save cleanup/preservation rules.

- [ ] **Step 4: Run frontend tests and verify pass**

Run the focused command and confirm the existing editor tests and new tests pass.

- [ ] **Step 5: Commit the task**

```bash
git add src/resources/js/Pages/AssessmentTask/Edit.vue src/resources/js/i18n/de.js src/tests/frontend/assessmentTaskEditor.test.js
git commit -m "feat(assessment): ergänzt Editor für Zuordnungstabellen"
```

### Task 3: ODT rendering

**Files:**
- Modify: `src/app/Documents/Templates/PrimarySchoolAssessmentTemplate.php`
- Modify: `src/app/Documents/AssessmentDocument.php`
- Test: `src/tests/Unit/DocumentRenderingTest.php`

**Interfaces:**
- Consumes: Normalized matching-table content from Task 1.
- Produces: A fixed-layout ODT table with a wide text column, narrow category columns, header labels, and empty X cells.

- [ ] **Step 1: Write the failing rendering test**

Render a matching table with two categories and assert that the ODT contains the text and category headings, multiple table columns, and no generic writing-lines table after the task marker.

- [ ] **Step 2: Run the rendering test and verify failure**

Run:

```bash
./roo test --filter='rendert eine Zuordnungstabelle'
```

Expected: the task falls through to generic rendering or produces no matching-table structure.

- [ ] **Step 3: Implement the renderer**

Add a dedicated renderer with a wide first column and equal narrow category columns. Render only the text and category headings plus blank answer cells; exclude `matching_table` from generic writing-line generation and ruling counts.

- [ ] **Step 4: Run the rendering test and verify pass**

Run the focused rendering test and confirm existing document-rendering tests remain green.

- [ ] **Step 5: Commit the task**

```bash
git add src/app/Documents/Templates/PrimarySchoolAssessmentTemplate.php src/app/Documents/AssessmentDocument.php src/tests/Unit/DocumentRenderingTest.php
git commit -m "feat(assessment): rendert Zuordnungstabellen"
```

### Task 4: Evaluation expectations and persistence

**Files:**
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue`
- Test: `src/tests/Feature/AssessmentEvaluationWorkflowTest.php`
- Test: `src/tests/frontend/assessmentEvaluation.test.js`

**Interfaces:**
- Consumes: Matching rows and scoring mode from the task payload.
- Produces: Temporary expectation occurrences with stable synthetic IDs and saved review points through the existing evaluation endpoint.

- [ ] **Step 1: Write failing evaluation tests**

Add backend and frontend tests for `per_category` expectation text and `complete_row` expectation text. Assert that two correct categories produce two expectations in the first mode and one combined expectation in the second, and that saved review results are restored.

- [ ] **Step 2: Run the focused evaluation tests and verify failure**

Run:

```bash
./roo test --filter='Zuordnungstabelle.*Evaluation|AssessmentEvaluationWorkflowTest'
./roo npm run test:unit -- --run tests/frontend/assessmentEvaluation.test.js
```

Expected: matching tasks expose no evaluation occurrences or use the wrong generic path.

- [ ] **Step 3: Implement temporary occurrence generation**

Extend the evaluation prop mapping and `TaskEvaluation.vue` occurrence construction with stable synthetic IDs derived from row and category IDs. Generate the exact German texts from the specification and keep matching tasks on the expectation-row UI path.

- [ ] **Step 4: Implement scoring-mode point behavior**

For `per_category`, each expectation receives the configured point value. For `complete_row`, each combined row expectation receives the configured point value. Ensure automatic matching rows are not counted twice with persisted expectations or extra points.

- [ ] **Step 5: Run all focused evaluation tests and verify pass**

Run both commands again and confirm all related tests pass.

- [ ] **Step 6: Commit the task**

```bash
git add src/app/Http/Controllers/AssessmentController.php src/resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue src/tests/Feature/AssessmentEvaluationWorkflowTest.php src/tests/frontend/assessmentEvaluation.test.js
git commit -m "feat(assessment): bewertet Zuordnungstabellen"
```

### Task 5: Integrated verification

**Files:**
- Modify: `docs/superpowers/specs/2026-08-26-matching-table-design.md` only if implementation decisions require clarification.

- [ ] **Step 1: Run the complete related backend test group**

```bash
./roo test --filter='ResourceLibraryTest|DocumentRenderingTest|AssessmentTaskEditorTest|AssessmentEvaluationWorkflowTest'
```

- [ ] **Step 2: Run the complete related frontend test group**

```bash
./roo npm run test:unit -- --run tests/frontend/assessmentTaskEditor.test.js tests/frontend/assessmentEvaluation.test.js
```

- [ ] **Step 3: Build and check the diff**

```bash
./roo npm run build && git diff --check
```

- [ ] **Step 4: Inspect the final status and summarize verified scope**

```bash
git status --short
git log -5 --oneline
```

