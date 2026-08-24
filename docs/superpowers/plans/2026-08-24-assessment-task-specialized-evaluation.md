# AssessmentTask Specialized Evaluation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a specialized, server-calculated checkbox evaluator while preserving generic manual expectations and extra points, and remove `multiple_choice` as a public task type.

**Architecture:** `checkbox` becomes the specialized task type. Its definition stores stable option IDs and one points-per-correct-answer value; its review stores option selections in a normalized table. A task-type evaluator registry selects the checkbox UI and server scoring, while manual expectations and extra points remain shared components. Existing `multiple_choice` rows are converted through a reversible backup-table migration, and legacy checkbox tasks with generated expectations remain in an explicit legacy evaluation mode until edited.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Pest, Vue 3, Inertia.js 3, Vitest, happy-dom, Bootstrap 5.3.

**Spec:** `docs/superpowers/specs/2026-08-24-assessment-task-specialized-evaluation-design.md`

## Global Constraints

- `checkbox` is the public task type for multiple-choice tasks; `multiple_choice` is not a public type.
- Correctly selected options receive the configured points; incorrectly selected options receive zero and never negative points.
- Manual expectations are not generated from options for new checkbox tasks.
- Manual expectations appear below the specialized checkbox UI and extra points remain unchanged.
- The server validates option IDs and calculates option points; browser-supplied points are never trusted.
- Student and assessment data remain within authorized Laravel endpoints; no student data is logged.
- Tests use only the isolated test database and never reset the Docker development database.
- Every production change is preceded by a failing test and verified with focused tests, the full relevant frontend suite, build, and `git diff --check`.

---

### Task 1: Establish checkbox data contracts and reversible legacy migration

**Files:**
- Create: `src/database/migrations/2026_08_24_290000_create_assessment_task_review_options_table.php`
- Create: `src/database/migrations/2026_08_24_291000_normalize_assessment_task_types.php`
- Create: `src/app/Models/AssessmentTaskReviewOption.php`
- Modify: `src/app/Models/AssessmentTaskReview.php`
- Modify: `src/app/Models/AssessmentTask.php`
- Modify: `src/app/Enums/AssessmentTaskType.php`
- Test: `src/tests/Feature/AssessmentTaskTypeMigrationTest.php`
- Test: `src/tests/Feature/AssessmentEvaluationDataTest.php`

**Interfaces:**
- Produces `AssessmentTaskReview::options(): HasMany`, `AssessmentTaskReviewOption::review(): BelongsTo`, and a stable option payload with `id`, `text`, and `correct`.
- Produces a reversible conversion for rows with `task_type = 'multiple_choice'`.
- Produces an explicit legacy marker in `AssessmentTask.content` for pre-existing checkbox tasks whose expectations were generated automatically.

- [ ] **Step 1: Write the failing migration/data tests.**

Add tests that create a task with `task_type = 'multiple_choice'`, run the
migration, and expect `checkbox`. Add a rollback assertion that restores only
the converted task by storing original types in a migration backup table. Add
a review option test that persists two stable option IDs and verifies the
relationship. Add a legacy-checkbox fixture with `content.automatic_expectations`
and verify that its content receives `evaluation_mode = 'legacy_checkbox'`
without deleting expectations or review rows.

- [ ] **Step 2: Run the focused tests and verify the expected failure.**

Run:

```bash
./roo test --filter=AssessmentTaskTypeMigrationTest
./roo test --filter=AssessmentEvaluationDataTest
```

Expected: failures because the review-option relation, migration tables, and
legacy marker do not exist yet.

- [ ] **Step 3: Add the normalized review-option table and model relation.**

Create `assessment_task_review_options` with a foreign key to
`assessment_task_reviews`, a string `option_id`, a boolean `selected`, and a
unique constraint on `(assessment_task_review_id, option_id)`. Add
`options()` to `AssessmentTaskReview`, fillable/casts to the model, and the
inverse relation on `AssessmentTaskReviewOption`.

- [ ] **Step 4: Add the reversible task-type migration.**

Create a small backup table keyed by `assessment_task_id` with the original
type. In `up()`, copy all `multiple_choice` rows to the backup and update them
to `checkbox`. For checkbox rows whose content has
`automatic_expectations !== false`, set the legacy evaluation marker without
changing their expectations or reviews. In `down()`, restore only IDs in the
backup table and remove the backup rows/table after restoration. Do not change
new checkbox rows created after the migration.

- [ ] **Step 5: Remove the public enum value only after conversion support exists.**

Remove `MULTIPLE_CHOICE` from `AssessmentTaskType`. Keep the database column a
string so the rollback can restore old values. Add model casts/normalization
that treats a legacy marker as `legacy_checkbox` for evaluation selection.

- [ ] **Step 6: Run the focused tests and commit.**

Run the two focused Pest commands again and then:

```bash
git diff --check
git add src/database/migrations src/app/Models src/app/Enums src/tests/Feature/AssessmentTaskTypeMigrationTest.php src/tests/Feature/AssessmentEvaluationDataTest.php
git commit -m "feat(assessment): strukturiere Checkbox-Bewertungen"
```

Expected: migration, relationship, rollback, and legacy-preservation tests
pass; unrelated worktree changes remain unstaged.

### Task 2: Implement server-side evaluator and result synchronization

**Files:**
- Create: `src/app/Services/AssessmentEvaluation/CheckboxTaskEvaluator.php`
- Create: `src/app/Services/AssessmentEvaluation/AssessmentTaskEvaluatorRegistry.php`
- Modify: `src/app/Services/AssessmentEvaluation/SaveAssessmentTaskReview.php`
- Modify: `src/app/Services/AssessmentEvaluation/SyncStudentAssessmentResult.php`
- Test: `src/tests/Unit/CheckboxTaskEvaluatorTest.php`
- Test: `src/tests/Feature/AssessmentEvaluationWorkflowTest.php`

**Interfaces:**
- `CheckboxTaskEvaluator::supports(AssessmentTask $task): bool`.
- `CheckboxTaskEvaluator::score(AssessmentTask $task, array $options): float`.
- `CheckboxTaskEvaluator::validate(AssessmentTask $task, array $options): void`.
- `AssessmentTaskEvaluatorRegistry::for(AssessmentTask $task): ?object`.
- `SaveAssessmentTaskReview` continues to own the transaction and calls the
  selected evaluator before writing review options and synchronizing results.

- [ ] **Step 1: Write failing unit tests for checkbox scoring.**

Cover selected correct options, selected incorrect options, omitted/false
options, zero correct options, decimal points, duplicate option IDs, unknown
option IDs, and legacy-checkbox tasks. Assert that only selected correct
options contribute `points_per_correct_answer`.

- [ ] **Step 2: Run the unit test and verify RED.**

Run:

```bash
./roo test --filter=CheckboxTaskEvaluatorTest
```

Expected: failure because the evaluator and registry do not exist.

- [ ] **Step 3: Implement the checkbox evaluator and registry.**

Read options from `task->content['options']`, require a stable string `id`,
read a non-negative numeric `points_per_correct_answer`, and reject duplicate
or unknown IDs. Return zero for incorrect selections. Return `null` from the
registry for legacy-checkbox tasks so their existing expectation evaluator is
used without double counting.

- [ ] **Step 4: Extend review saving with option selections.**

Extend the validated review data shape to include
`options: list<array{id: string, selected: bool}>`. For specialized tasks,
require every current option exactly once; for generic and legacy tasks,
retain the existing expectation contract. Replace existing review-option rows
inside the existing transaction and calculate the task’s option score from
the evaluator.

- [ ] **Step 5: Synchronize combined results.**

Update `SyncStudentAssessmentResult` to sum manual expectation review items,
specialized evaluator points, and extra points exactly once. Preserve its
existing behavior for unassigned/discarded booklets and for legacy tasks.

- [ ] **Step 6: Add feature tests and run the green cycle.**

Add feature coverage for saving/reloading selections, invalid payloads,
manual expectations plus checkbox points, extra points, and reassigned or
discarded booklets. Run:

```bash
./roo test --filter=CheckboxTaskEvaluatorTest
./roo test --filter=AssessmentEvaluationWorkflowTest
```

Expected: all focused unit and feature tests pass.

- [ ] **Step 7: Commit the server evaluator.**

```bash
git diff --check
git add src/app/Services/AssessmentEvaluation src/tests/Unit/CheckboxTaskEvaluatorTest.php src/tests/Feature/AssessmentEvaluationWorkflowTest.php
git commit -m "feat(assessment): berechne Checkbox-Aufgaben serverseitig"
```

### Task 3: Expose specialized review data through the controller and request

**Files:**
- Modify: `src/app/Http/Requests/AssessmentTaskReviewRequest.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Test: `src/tests/Feature/AssessmentEvaluationWorkflowTest.php`

**Interfaces:**
- Evaluation props expose `task_type`, `content.options`,
  `points_per_correct_answer`, `expectations`, and existing review selections.
- Review requests accept `options.*.id` and `options.*.selected` while keeping
  `items` and `extra_points` unchanged.

- [ ] **Step 1: Write failing feature assertions for specialized props and payload validation.**

Assert that an evaluation response includes checkbox task type, stable option
IDs, point configuration, and previously saved selection state. Assert that a
review with an unknown option, duplicate option, missing option, or browser
supplied point value is rejected.

- [ ] **Step 2: Run the focused feature test and verify RED.**

Run:

```bash
./roo test --filter=AssessmentEvaluationWorkflowTest
```

Expected: the new response and validation assertions fail.

- [ ] **Step 3: Add conditional request validation.**

Keep the existing generic rules, add `options` as a present array for
specialized tasks, validate string IDs and booleans, and leave the final
task-specific completeness/ownership checks to the evaluator service after
route authorization has established the task.

- [ ] **Step 4: Add task-type-aware evaluation props.**

Load `tasks.content`, `tasks.expectations`, and review options. Serialize only
the fields needed by the evaluator; never include student data in logs or
unrelated private task content. Include `evaluation_mode` when a task is
legacy.

- [ ] **Step 5: Run feature tests and commit.**

```bash
./roo test --filter=AssessmentEvaluationWorkflowTest
git diff --check
git add src/app/Http/Requests/AssessmentTaskReviewRequest.php src/app/Http/Controllers/AssessmentController.php src/tests/Feature/AssessmentEvaluationWorkflowTest.php
git commit -m "feat(assessment): übertrage Checkbox-Auswertung"
```

### Task 4: Update the AssessmentTask editor and task-definition validation

**Files:**
- Modify: `src/resources/js/Pages/AssessmentTask/Edit.vue`
- Modify: `src/app/Http/Controllers/LessonWorkspaceController.php`
- Modify: `src/app/Http/Controllers/ResourceLibraryController.php`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/frontend/assessmentTaskEditor.test.js`
- Test: `src/tests/Feature/AssessmentTaskEditorTest.php`

**Interfaces:**
- New checkbox definitions submit `content.options` with stable IDs,
  `content.points_per_correct_answer`, and only manual expectations.
- Both lesson and resource-library creation/update paths validate the same
  checkbox content contract.

- [ ] **Step 1: Write failing editor and backend tests.**

Test that selecting `checkbox` shows the points-per-correct-answer input,
does not show the automatic-expectations toggle, retains manual expectation
rows, submits stable option IDs, and removes `multiple_choice` from the type
choices. Add backend tests requiring a non-negative numeric point value and
rejecting malformed options.

- [ ] **Step 2: Run the tests and verify RED.**

Run:

```bash
./roo npm run test:unit -- --run tests/frontend/assessmentTaskEditor.test.js
./roo test --filter=AssessmentTaskEditorTest
```

Expected: the new checkbox-specific assertions fail.

- [ ] **Step 3: Replace automatic checkbox expectations in the editor.**

Give each option a stable generated ID when created, bind the numeric points
input to `content.points_per_correct_answer`, remove checkbox expectation
synchronization, and leave `form.expectations` for manually entered rows.
When loading a legacy task, preserve its legacy marker and existing
expectations instead of silently rewriting it in the browser.

- [ ] **Step 4: Align both backend controllers.**

Use shared validation rules or an extracted request helper for option IDs,
text, correct flags, and points. Remove `multiple_choice` from allowed task
types after the conversion migration. Calculate `max_points` as specialized
checkbox maximum plus manual expectation maximum; legacy tasks retain their
existing calculation path.

- [ ] **Step 5: Add German labels and run the green cycle.**

Add translations for points per correct answer and the specialized evaluator,
then run both focused test commands again. Commit only editor/controller/i18n
files and their tests:

```bash
git diff --check
git add src/resources/js/Pages/AssessmentTask/Edit.vue src/app/Http/Controllers/LessonWorkspaceController.php src/app/Http/Controllers/ResourceLibraryController.php src/resources/js/i18n/de.js src/tests/frontend/assessmentTaskEditor.test.js src/tests/Feature/AssessmentTaskEditorTest.php
git commit -m "feat(assessment): erweitere Checkbox-Aufgabeneditor"
```

### Task 5: Add the specialized Vue evaluator and compose it with shared UI

**Files:**
- Create: `src/resources/js/Features/AssessmentEvaluation/CheckboxTaskEvaluation.vue`
- Modify: `src/resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue`
- Modify: `src/resources/js/Features/AssessmentEvaluation/presentation.js`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/frontend/assessmentEvaluation.test.js`

**Interfaces:**
- `CheckboxTaskEvaluation` accepts `options`, `selection`, and `processing`;
  emits `update:selection` with `{ id, selected }` rows.
- `TaskEvaluation` renders the specialized component only for non-legacy
  `checkbox` tasks, followed by the existing expectation rows and extra-points
  controls.

- [ ] **Step 1: Write failing component tests.**

Cover rendering every option, toggling a selection, preserving saved
selections, showing the configured point value, rendering manual expectations
below the custom component, retaining extra points, and not rendering the
custom component for legacy tasks.

- [ ] **Step 2: Run the frontend test and verify RED.**

Run:

```bash
./roo npm run test:unit -- --run tests/frontend/assessmentEvaluation.test.js
```

Expected: failures because the specialized component and task-type branch do
not exist.

- [ ] **Step 3: Implement `CheckboxTaskEvaluation.vue`.**

Render a compact accessible list of options with checked state, correct-answer
indicator only where appropriate for the teacher, and a stable option ID in
the emitted payload. Do not calculate or submit points in the component.

- [ ] **Step 4: Integrate the component into `TaskEvaluation.vue`.**

Initialize option selection state from the server review, include options in
the existing save payload, keep generic expectation rows below the custom
component, and leave extra points and per-case save/error state unchanged.

- [ ] **Step 5: Run all frontend tests and commit.**

```bash
./roo npm run test:unit -- --run
./roo npm run build
git diff --check
git add src/resources/js/Features/AssessmentEvaluation src/resources/js/i18n/de.js src/tests/frontend/assessmentEvaluation.test.js
git commit -m "feat(assessment): ergänze Checkbox-Bewertungskomponente"
```

### Task 6: Verify migration, exports, and complete integration

**Files:**
- Modify: `src/app/Documents/AssessmentDocument.php` only if checkbox maximums are currently derived solely from expectations
- Modify: `src/app/Documents/Templates/PrimarySchoolAssessmentTemplate.php` only if checkbox rendering needs the new point field
- Modify: `src/tests/Unit/DocumentRenderingTest.php` if the document contract changes
- Modify: `docs/superpowers/specs/2026-08-24-assessment-task-specialized-evaluation-design.md` only for verified implementation clarifications

**Interfaces:**
- Document exports continue to render checkbox options and show the correct
  maximum points for new and legacy tasks.
- No assessment workflow outside specialized evaluation changes behavior.

- [ ] **Step 1: Inspect current export behavior against the new content contract.**

Run the existing document rendering tests and compare the checkbox maximum
point calculation with the evaluator’s calculation. Only modify export files
if a new checkbox task would otherwise show the wrong maximum.

- [ ] **Step 2: Run the complete backend assessment test set.**

Run:

```bash
./roo test --filter=Assessment
./roo test --filter=ResourceLibraryTest
```

Expected: all assessment and resource-library tests pass, including migration,
review, editor, assignment, and result synchronization coverage.

- [ ] **Step 3: Verify the database conversion command when Docker is available.**

Run the read-only count before migration:

```bash
./roo artisan tinker --execute="echo App\\Models\\AssessmentTask::where('task_type', 'multiple_choice')->count();"
```

After migration, run the same query and expect `0`. If the pre-migration count
is non-zero, verify each converted task retains its options, manual
expectations, and review rows. Do not reset or refresh the development
database.

- [ ] **Step 4: Run final verification.**

```bash
./roo test
./roo npm run test:unit -- --run
./roo npm run build
git diff --check
git status --short
```

Expected: focused and full relevant tests pass, build exits zero with only
known dependency warnings, diff check is clean, and unrelated existing
worktree changes are preserved.

- [ ] **Step 5: Update the progress documentation and commit the final docs.**

Record the completed task commits, the Docker database check result, and any
known dependency warnings in
`.superpowers/sdd/2026-08-23-assessment-evaluation-workflow/progress.md` only
if the user requests that cross-workflow ledger to be updated. Otherwise leave
that unrelated ledger untouched.
