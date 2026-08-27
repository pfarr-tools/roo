# Lückentext Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the `cloze` AssessmentTask type with bracket-derived scored blanks and inline ODT ruling images.

**Architecture:** Parse and normalize blanks in a dedicated application service, persist normalized blank metadata and generated expectations through the existing task save flow, and render each text/blank fragment in the primary-school ODT template. Reuse the existing expectation evaluation rows so review persistence and result aggregation need no new database tables.

**Tech Stack:** Laravel 13, PHP 8.4, Vue 3/Inertia, Pest, Vitest, PHPWord ODText, `pfarr-tools/roo-ruling` (`HandwritingSpaceEstimator`, `PngRulingRenderer`).

**Spec:** `docs/superpowers/specs/2026-08-27-cloze-text-design.md`

## Global Constraints

- All user-facing text and validation messages remain German.
- The Docker wrapper `./roo` is used for Composer, tests, and frontend builds.
- Tests use the isolated test database and never the persistent development database.
- Auto-generated blank expectations use `Du hast korrekt ausgefüllt: <solution>`.
- `src/composer.lock` must resolve `pfarr-tools/roo-ruling` to the current `cfec28d` commit.

### Task 1: Add parser and normalized cloze model

**Files:**
- Create: `src/app/Services/Assessment/ClozeText.php`
- Create: `src/app/Services/Assessment/ClozeTextParser.php`
- Test: `src/tests/Unit/ClozeTextParserTest.php`

**Interfaces:**
- `ClozeTextParser::parse(string $prompt, array $previousBlanks = []): array` returns normalized `prompt`, `blanks`, and `fragments` data.
- Each blank is `['id' => 'blank-N', 'solution' => string, 'points' => int]`.

- [ ] Write tests for extracting ordered blanks, preserving points by stable ID, rejecting empty/unclosed/nested brackets, and splitting words only for rendering.
- [ ] Run `./roo test --compact tests/Unit/ClozeTextParserTest.php` and verify the new tests fail because the parser does not exist.
- [ ] Implement the parser with a single-pass bracket validator and stable positional IDs.
- [ ] Run the focused test until all parser cases pass.
- [ ] Refactor only after the focused test is green.

### Task 2: Add task type and server-side persistence/validation

**Files:**
- Modify: `src/app/Enums/AssessmentTaskType.php`
- Modify: `src/app/Http/Controllers/LessonWorkspaceController.php`
- Modify: `src/app/Http/Controllers/ResourceLibraryController.php`
- Modify: `src/app/Models/AssessmentTask.php`
- Test: `src/tests/Feature/ResourceLibraryTest.php`

**Interfaces:**
- Both task save endpoints accept `task_type=cloze` and normalize `content.prompt`, `content.show_solutions`, `content.lineated`, `content.split_blank_words`, and `content.blanks`.
- `AssessmentTask::maximumPoints()` sums the normalized blank expectation points for `cloze` tasks.

- [ ] Add a failing feature test for creating and updating a cloze task, including a changed prompt that retains points for unchanged blank IDs.
- [ ] Run the focused feature test and confirm validation/type support is missing.
- [ ] Add the enum case, request rules, parser invocation, and generated expectation synchronization. Keep manual expectations separate and include them before Sonderpunkte.
- [ ] Add cloze maximum-point calculation without counting generated points twice.
- [ ] Run `./roo test --compact tests/Feature/ResourceLibraryTest.php` and verify all relevant tests pass.

### Task 3: Add editor UI and localization

**Files:**
- Modify: `src/resources/js/Pages/AssessmentTask/Edit.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/frontend/assessmentTaskEditor.test.js`

**Interfaces:**
- The editor shows the cloze textarea, three options, and one integer points input per parsed blank.
- The serialized payload contains only normalized cloze content and no obsolete separate word-list field.

- [ ] Add failing Vitest coverage for the task type, bracket-derived blank rows, option values, and integer point serialization.
- [ ] Run the focused Vitest file and verify the new assertions fail.
- [ ] Implement reactive parsing in the editor, retaining points by blank ID while typing and showing German labels.
- [ ] Add the task type to the already ordered task-type list and ensure switching away removes cloze-only fields.
- [ ] Run `./roo npm run test:unit -- --run tests/frontend/assessmentTaskEditor.test.js`.

### Task 4: Implement non-lineated ODT cloze rendering

**Files:**
- Modify: `src/app/Documents/Templates/PrimarySchoolAssessmentTemplate.php`
- Modify: `src/app/Documents/AssessmentDocument.php`
- Test: `src/tests/Unit/DocumentRenderingTest.php`

**Interfaces:**
- `PrimarySchoolAssessmentTemplate::addClozeTask(Section $section, array $content, string $gradeLevel): void` renders text fragments and estimator-sized underscores.
- `AssessmentDocument::odtRulings()` excludes cloze tasks from generic writing-line collection.

- [ ] Add a failing document test asserting task text, optional solution list, blank underscores, and the split-word option in `content.xml`.
- [ ] Run the focused document test and verify it fails before the renderer branch exists.
- [ ] Implement fragment rendering with `HandwritingSpaceEstimator`, using the selected grade and preserving ordinary text exactly.
- [ ] Add the `cloze` branch and prevent generic trailing writing lines from being appended.
- [ ] Run the focused document rendering tests.

### Task 5: Implement inline PNG ruling rendering

**Files:**
- Modify: `src/app/Documents/Templates/PrimarySchoolAssessmentTemplate.php`
- Modify: `src/app/Services/PhpOfficeDocumentRenderer.php`
- Test: `src/tests/Unit/DocumentRenderingTest.php`

**Interfaces:**
- The template emits deterministic image markers/data for each lineated blank using `PngRulingRenderer` and the selected grade ruling.
- The ODT post-processing path preserves and packages the generated inline PNGs.

- [ ] Add a failing document test for lineated cloze output that asserts embedded image entries and dimensions at least equal to the ruling band height.
- [ ] Run the focused test and verify the image is absent before implementation.
- [ ] Generate one-band transparent PNGs with width from the estimator and a preset line height; add them as inline PHPWord images with matching height.
- [ ] Extend ODT packaging only where the current renderer cannot retain generated binary image data.
- [ ] Run XML assertions and convert a representative ODT to PDF when LibreOffice is available.

### Task 6: Verify evaluation integration and complete documentation

**Files:**
- Modify: `build/AssessmentTaskTypes.md`
- Test: `src/tests/Feature/AssessmentEvaluationWorkflowTest.php`
- Test: `src/tests/frontend/assessmentEvaluation.test.js`

**Interfaces:**
- Evaluation receives one persisted expectation per cloze blank with the exact generated text and configured points.
- Existing `ExpectationEvaluationRow` renders and saves the rows without a task-specific evaluator.

- [ ] Add failing backend and frontend tests for the generated expectation text/points and review save path.
- [ ] Run the focused tests and verify the missing cloze rows fail.
- [ ] Confirm the existing evaluation component displays one row per blank and that saved review items aggregate only once.
- [ ] Update the generated task-type documentation.
- [ ] Run the complete focused suite, `./roo npm run build`, and `git diff --check`.
