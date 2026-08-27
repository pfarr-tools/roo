# Erweiterter Freitext Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `free_text` support reusable images and an optional reading text, while removing the unused `free_text_images` and `reading_text` task types.

**Architecture:** Reuse the existing `AssessmentTaskImage` persistence and `ImageLibraryUploadModal` editor flow for `free_text`. Store the new text as `content.optional_reading_text`; keep it outside question/expectation logic. Extend the primary-school ODT template with a dedicated free-text image block that lays out images in rows of up to three columns, followed by the styled reading text.

**Tech Stack:** Laravel 13, PHP 8.4, Vue 3/Inertia, Vitest, Pest, PhpWord/ODT export.

**Spec:** `docs/superpowers/specs/2026-08-27-free-text-images-reading-text-design.md`

## Global Constraints

- Keep all user-facing text in German and centralized in `src/resources/js/i18n/de.js`.
- Store image references through the existing `AssessmentTaskImage` relationship and filesystem abstraction.
- Do not introduce expectations or evaluation points for free-text images or optional reading text.
- Use `./roo` for backend tests and builds; never run tests against the development database.
- Preserve reversible, focused changes and verify ODT structure rather than relying only on source inspection.

---

### Task 1: Define the supported task shape and editor behavior

**Files:**
- Modify: `src/app/Enums/AssessmentTaskType.php`
- Modify: `src/resources/js/Pages/AssessmentTask/Edit.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `tests/frontend/assessmentTaskEditor.test.js`

**Interfaces:**
- Consumes: existing `form.images`, `ImageLibraryUploadModal`, `usesImages`, and image ordering helpers.
- Produces: `free_text` editor payload with `content.optional_reading_text`, `content.image_width_cm`, `images`, and existing `lines`/`lineated` fields; no `free_text_images` or `reading_text` choices.

- [ ] **Step 1: Write failing frontend tests** for the task-type list, free-text image/reading-text fields, and save payload cleanup.
- [ ] **Step 2: Run the focused Vitest file** and confirm the new assertions fail because the old task types and field conditions remain.
- [ ] **Step 3: Remove the two enum cases and their labels**, change `usesImages('free_text')` to true, and make the editor render the existing image chooser for `free_text`.
- [ ] **Step 4: Add the translated `Optionaler Lesetext` label** and bind an optional textarea to `form.content.optional_reading_text`.
- [ ] **Step 5: Retain image width, lines, and lineation only for `free_text` and the other relevant image/table types; delete `reading_text`, `questions`, and legacy image fields from the free-text payload.
- [ ] **Step 6: Run the focused frontend tests** and refactor only after they pass.

### Task 2: Validate and persist free-text images and optional reading text

**Files:**
- Modify: `src/app/Http/Controllers/ResourceLibraryController.php`
- Modify: `src/app/Http/Controllers/LessonWorkspaceController.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php` only if task presentation needs free-text image metadata
- Modify: `src/app/Models/AssessmentTask.php` only if image-width helpers need broadening
- Test: `src/tests/Feature/ResourceLibraryTest.php`
- Test: `src/tests/Feature/AssessmentTaskEditorTest.php`

**Interfaces:**
- Consumes: the editor payload from Task 1.
- Produces: persisted `AssessmentTask` records with `task_type = free_text`, `content.optional_reading_text`, `content.image_width_cm`, and ordered `AssessmentTaskImage` records.

- [ ] **Step 1: Add failing feature coverage** for create and update of a `free_text` task with two image references and an optional reading text, and assert the old type values are rejected.
- [ ] **Step 2: Run the focused backend tests** and confirm failure from enum validation and the current `content.images` prohibition/conditional image rules.
- [ ] **Step 3: Update both controller validation paths** to accept `content.optional_reading_text`, allow `content.image_width_cm` for `free_text`, and allow the existing `images` payload for `free_text`.
- [ ] **Step 4: Update image cleanup/ordering conditions** so saving a free-text task calls the existing `syncTaskImages` path and non-image task types still clear images.
- [ ] **Step 5: Remove obsolete `reading_text`/`free_text_images` validation branches without changing expectation handling for ordinary free text.
- [ ] **Step 6: Run focused backend tests and `git diff --check`.**

### Task 3: Render free-text images and optional reading text in ODT

**Files:**
- Modify: `src/app/Documents/Templates/PrimarySchoolAssessmentTemplate.php`
- Modify: `src/app/Services/PhpOfficeDocumentRenderer.php` only if the existing ODT post-processing incorrectly classifies free-text images
- Modify: `src/app/Documents/AssessmentDocument.php` only if ruling/image task classification needs updating
- Test: `src/tests/Feature/DocumentRenderingTest.php`

**Interfaces:**
- Consumes: resolved task `content` and image entries from the existing document assembly pipeline.
- Produces: ODT content ordered as prompt, up to three image columns per row, optional Atkinson Hyperlegible Next 14 pt normal reading text, and ordinary writing lines.

- [ ] **Step 1: Add a failing ODT test** with four free-text images and an optional reading text; inspect `content.xml` for three-column rows, image placement, text order, and font attributes.
- [ ] **Step 2: Run the focused document test** and confirm the current renderer emits no free-text images and treats only `reading_text` as reading text.
- [ ] **Step 3: Add a dedicated `addFreeTextImages` helper** that chunks valid images into groups of three, creates a borderless table with equal cells, applies the configured image width subject to available content width, and skips missing files.
- [ ] **Step 4: Move optional reading-text rendering into the free-text path** after images, using `name => self::ATKINSON`, `size => 14`, and normal font style; keep it absent when blank.
- [ ] **Step 5: Remove old type-specific export branches and ensure normal free-text writing-line rendering remains unchanged.**
- [ ] **Step 6: Run the focused document tests and inspect generated ODT XML for visible image/table structure.**

### Task 4: Remove stale references and run regression verification

**Files:**
- Modify: all remaining production/test files found by `rg -n "free_text_images|reading_text" src tests`
- Test: relevant existing assessment frontend/backend/export test files

**Interfaces:**
- Consumes: completed editor, persistence, and export behavior from Tasks 1–3.
- Produces: no selectable or accepted legacy task types and a green focused regression suite.

- [ ] **Step 1: Search production and tests** for `free_text_images` and `reading_text`, distinguishing intentional documentation/history from executable references.
- [ ] **Step 2: Update or remove stale tests and labels** so all supported-type assertions use `free_text` with the new content fields.
- [ ] **Step 3: Run focused backend tests with `./roo test`**, focused frontend tests with the project Vitest command, and the Vite build.
- [ ] **Step 4: Run `git diff --check` and review the final diff for German labels, authorization scope, and absence of duplicate point counting.
- [ ] **Step 5: Commit with `feat(assessment): erweitert Freitext um Bilder und Lesetext`.**
