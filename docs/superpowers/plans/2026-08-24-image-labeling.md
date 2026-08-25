# Bild beschriften Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the `image_labeling` assessment task type with one library image, interactive reference points, and per-label evaluation.

**Architecture:** Reuse `assessment_task_images` for the single resource and add a relational label table storing normalized coordinates, solution text, and order. Reuse the existing editor, evaluation registry, and library upload/picker flows. ODT export remains unchanged.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Inertia/Vue 3, Vitest, Pest.

**Spec:** `docs/superpowers/specs/2026-08-24-image-labeling-design.md`

## Global Constraints

- German UI and validation messages; English code identifiers.
- Tests use an isolated test database only.
- No external image URLs; images come from the library.
- Do not change ODT export in this slice.

### Task 1: Persist image-label definitions

**Files:**
- Create: `src/database/migrations/2026_08_24_*_create_assessment_task_image_labels_table.php`
- Create: `src/app/Models/AssessmentTaskImageLabel.php`
- Modify: `src/app/Models/AssessmentTaskImage.php`, `src/app/Models/AssessmentTask.php`, `src/app/Enums/AssessmentTaskType.php`
- Test: `src/tests/Unit/ImageLabelingTaskTest.php`

- [ ] Write a failing model/relationship test for ordered labels and normalized coordinate casts.
- [ ] Run the focused test and verify it fails because the relation/type is absent.
- [ ] Add migration, model casts/fillable, relations, and enum value.
- [ ] Run the focused test and verify it passes.

### Task 2: Save and edit the task

**Files:**
- Modify: `src/app/Http/Controllers/ResourceLibraryController.php`, `src/app/Http/Controllers/LessonWorkspaceController.php`
- Modify: `src/resources/js/Pages/AssessmentTask/Edit.vue`, `src/resources/js/i18n/de.js`
- Test: `src/tests/Feature/ResourceLibraryTest.php`, `src/tests/frontend/assessmentTaskEditor.test.js`

- [ ] Add failing request assertions for one authorized resource, configuration bounds, and label persistence.
- [ ] Run focused tests and verify the new assertions fail.
- [ ] Add validation and synchronization for the single image and ordered labels.
- [ ] Add the image-labeling editor with relative click coordinates, focus/hover highlighting, and removal.
- [ ] Run backend and frontend focused tests.

### Task 3: Evaluate labels

**Files:**
- Create: `src/app/Services/AssessmentEvaluation/ImageLabelingTaskEvaluator.php`
- Modify: `src/app/Services/AssessmentEvaluation/AssessmentTaskEvaluatorRegistry.php`, `src/app/Models/AssessmentTask.php`, `src/app/Http/Controllers/AssessmentController.php`, `src/resources/js/Features/AssessmentEvaluation/TaskEvaluation.vue`, `src/resources/js/i18n/de.js`
- Test: `src/tests/Unit/ImageLabelingTaskEvaluatorTest.php`, `src/tests/frontend/imageLabelingTaskEvaluation.test.js`

- [ ] Write failing evaluator and UI tests for one checkbox per saved solution and points per selected correct label.
- [ ] Run them to verify the expected failures.
- [ ] Implement evaluator registration, evaluation props, checkbox UI, and result synchronization.
- [ ] Run all relevant tests.

### Task 4: Verify

- [ ] Run focused Pest and Vitest suites.
- [ ] Run `./roo npm run build`.
- [ ] Run `git diff --check`.
