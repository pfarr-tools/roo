# Bewertungsvorlagen je Zeitraum Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bewertungszeiträume im Gruppen-Tab verwalten und editierbare, niveaudifferenzierte Vorschlagstexte aus behandelten Inhaltskompetenzen erzeugen.

**Architecture:** A relational snapshot table stores one editable/original template per report period and optional EducationPlan level. A dedicated generator service resolves scheduled content competencies and reads the keyed proposal sentences from `Kompetenzsaetze.json`; the controller orchestrates authorization, transactions, and Inertia responses.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Pest, Vue 3, Inertia.js 3, Bootstrap 5.3, Vitest.

**Spec:** `docs/superpowers/specs/2026-09-05-evaluation-template-proposals-design.md`

## Global Constraints

- Tests use only an isolated in-memory or separate test database.
- All UI text is German and all user-visible navigation uses Inertia-compatible routes.
- Controllers stay thin; generation belongs in a service and writes use transactions.
- Existing snapshots and unrelated untracked Bildungsplan files remain untouched.

---

### Task 1: Relational template snapshots and generator

**Files:**
- Create: `src/database/migrations/2026_09_05_100000_create_report_period_evaluation_templates_table.php`
- Create: `src/app/Models/ReportPeriodEvaluationTemplate.php`
- Create: `src/app/Services/EvaluationTemplateGenerator.php`
- Modify: `src/app/Models/ReportPeriod.php`
- Test: `src/tests/Feature/PhaseElevenTest.php`

**Interfaces:**
- `EvaluationTemplateGenerator::generate(ReportPeriod $period): Collection` returns rows with `level`, `original_text`, and `text`.
- `ReportPeriod::evaluationTemplates(): HasMany` exposes the persisted snapshots.

- [ ] **Step 1: Write failing tests** for JSON sentence lookup, first-versus-following placeholder replacement, deduplication, non-differentiated output, differentiated `G/M/E` output, and empty output when no scheduled content competency is in range.
- [ ] **Step 2: Run** `./roo test --compact tests/Feature/PhaseElevenTest.php --filter='template'` and verify the new assertions fail because the table/service do not exist.
- [ ] **Step 3: Add** the migration, model relation, and service. Load the JSON resource through Laravel's filesystem path, resolve `EducationPlanCompetency.external_identifier`, select variant-level sentences where available, preserve stable treatment order, and create original/current text snapshots.
- [ ] **Step 4: Run** the focused PhaseEleven tests and verify all template-generation assertions pass.
- [ ] **Step 5: Run** `./roo pint app/Models/ReportPeriod.php app/Models/ReportPeriodEvaluationTemplate.php app/Services/EvaluationTemplateGenerator.php tests/Feature/PhaseElevenTest.php`.

### Task 2: Period creation and template CRUD

**Files:**
- Modify: `src/app/Http/Controllers/EvaluationController.php`
- Modify: `src/routes/web.php`
- Create: `src/resources/js/Pages/Evaluations/TemplateEdit.vue`
- Test: `src/tests/Feature/PhaseElevenTest.php`

**Interfaces:**
- `editTemplate(TeachingGroup $teachingGroup, ReportPeriod $period)` returns `Evaluations/TemplateEdit` with the period and ordered templates.
- `updateTemplate(Request $request, TeachingGroup $teachingGroup, ReportPeriod $period)` validates `templates.*.id` and `templates.*.text`, updates only templates belonging to the period, and redirects to the group evaluations tab.

- [ ] **Step 1: Write failing feature tests** for template creation during period creation, authorized template edit/update, reset by posting `original_text`, cross-group 404/authorization behavior, and unsupported grading models producing no templates.
- [ ] **Step 2: Run** `./roo test --compact tests/Feature/PhaseElevenTest.php --filter='template|Vorlage'` and verify the new endpoint assertions fail.
- [ ] **Step 3: Integrate** generator invocation into `storePeriod` inside its existing transaction, add named GET/PUT routes, and implement scoped controller methods with German flash messages.
- [ ] **Step 4: Implement** `TemplateEdit.vue` with one textarea per template, a per-template reset button that restores `original_text` locally, and an Inertia form submission.
- [ ] **Step 5: Run** the focused feature tests and a Vitest test for render/reset/form payload.

### Task 3: Group evaluations tab and navigation

**Files:**
- Modify: `src/app/Http/Controllers/TeachingGroupController.php`
- Modify: `src/resources/js/Pages/TeachingGroups/Show.vue`
- Modify: `src/resources/js/i18n/de.js`
- Modify: `src/tests/Feature/TeachingGroupTabsTest.php`
- Create or modify: `src/tests/frontend/teachingGroupShow.test.js`

**Interfaces:**
- The group show props include ordered `reportPeriods` with `evaluation_templates` for the evaluations tab.
- Each period row links to `/unterrichtsgruppen/{group}/bewertungen/zeiträume/{period}/vorlage`.

- [ ] **Step 1: Write failing feature/UI tests** asserting that the competency-text grading mode has a Topbar `+ Zeitraum` action, lists template edit links and levels, while observation scales retain settings without template controls.
- [ ] **Step 2: Run** the focused tests and verify the new assertions fail.
- [ ] **Step 3: Load** the template relation in `TeachingGroupController` and render the period list plus the existing grading settings in the evaluations tab. Add the create-period topbar link only for `competency_texts_and_grades`.
- [ ] **Step 4: Run** the focused backend/frontend tests and verify the UI behavior.

### Task 4: Full vertical verification

**Files:**
- Modify: documentation only if implementation details require clarification.

- [ ] **Step 1: Run** `./roo test --compact tests/Feature/PhaseElevenTest.php tests/Feature/TeachingGroupTabsTest.php`.
- [ ] **Step 2: Run** `./roo npm run test:unit -- --run tests/frontend/evaluationEdit.test.js tests/frontend/evaluationIndex.test.js tests/frontend/teachingGroupShow.test.js`.
- [ ] **Step 3: Run** `./roo pint app/Http/Controllers/EvaluationController.php app/Http/Controllers/TeachingGroupController.php app/Models/ReportPeriod.php app/Models/ReportPeriodEvaluationTemplate.php app/Services/EvaluationTemplateGenerator.php tests/Feature/PhaseElevenTest.php tests/Feature/TeachingGroupTabsTest.php`.
- [ ] **Step 4: Run** `./roo npm run build && git diff --check`.
- [ ] **Step 5: Review** `git status --short`, keep unrelated `data/bildungsplaene/Kompetenzsaetze.json` and `data/bildungsplaene/Operatoren.txt` uncommitted, and report exact verification results.
