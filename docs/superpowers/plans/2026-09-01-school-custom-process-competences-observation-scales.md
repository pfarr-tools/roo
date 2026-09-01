# School Custom Process Competences and Observation Scales Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable schools to manage their own process competences and a shared `+` observation scale that is immediately available to all matching teaching groups and is historically stable after evaluation confirmation.

**Architecture:** Add a school-owned `CustomProcessCompetence` model and a school-level interval-count setting. Draft observation/evaluation data resolves the active school definitions live; confirmation stores immutable text, position, interval-count, and selected-level snapshots. Keep these references separate from imported education-plan process competences.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Pest, Vue 3, Inertia.js 3, Bootstrap 5.3, Vitest, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-09-01-school-custom-process-competences-observation-scales-design.md`

## Global Constraints

- User-facing labels, validation errors, and domain terms are German.
- Source code, class names, method names, database columns, and technical comments are English.
- Tests must use only the isolated test database and must never reset the Docker development database.
- Controllers remain thin; validation uses Form Requests, authorization uses Policies, and related writes use transactions.
- Imported `EducationPlanCompetency` records and school-owned custom process competences remain separate reference types.
- Active school competences are automatically visible only to teaching groups whose `grading_model` is `observation_scales`.
- `ne` is a separate status, not one of the configured numeric observation intervals.
- Competence and confirmed-evaluation data must not expose student data in logs or public URLs.

## File Map

- Create `src/database/migrations/2026_09_01_100000_add_observation_scale_settings_to_schools.php`: add the school interval-count setting.
- Create `src/database/migrations/2026_09_01_110000_create_custom_process_competences_table.php`: persist school-owned competence definitions.
- Create `src/database/migrations/2026_09_01_120000_extend_competence_evidences_for_custom_competences.php`: allow observation evidence to reference either an imported teaching-unit competence or a custom school competence, with database-level exclusivity.
- Modify `src/app/Models/School.php`: fillable setting and custom-competence relationship.
- Create `src/app/Models/CustomProcessCompetence.php`: school-scoped model and relationships.
- Modify `src/app/Models/CompetenceEvidence.php`: custom-competence relationship and casts/fillable fields.
- Modify `src/app/Models/StudentEvaluation.php`: confirmed scale snapshot relationship.
- Create `src/app/Models/StudentEvaluationObservationScale.php`: immutable per-evaluation scale result/snapshot.
- Create `src/app/Http/Requests/UpdateSchoolObservationScaleRequest.php`: validate the school setting and competence management payload.
- Create `src/app/Http/Requests/UpdateSchoolObservationScaleRequest.php`: validate scale count and ordered competence definitions.
- Modify `src/app/Http/Controllers/SchoolController.php`: load and update the school scale configuration through authorized actions.
- Create `src/app/Http/Controllers/CustomProcessCompetenceController.php`: authorize, validate, and persist competence changes while keeping `SchoolController` focused on school settings.
- Modify `src/routes/web.php`: add school configuration routes under `/schulen/{school}`.
- Create `src/app/Policies/CustomProcessCompetencePolicy.php`: enforce the school/organization scope.
- Modify `src/app/Http/Controllers/LessonWorkspaceController.php`: expose active custom competences for observation-scale groups and validate custom evidence.
- Modify `src/app/Http/Controllers/EvaluationController.php`: expose live definitions to drafts and materialize snapshots on confirmation.
- Modify `src/resources/js/Pages/Schools/Show.vue`: manage scale count and ordered custom competence texts.
- Modify `src/resources/js/Pages/Lessons/Show.vue`: render custom observation-scale inputs where the group mode allows them.
- Modify `src/resources/js/Pages/Evaluations/Edit.vue`: show/edit live custom scale results and invalid-level corrections.
- Modify `src/resources/js/i18n/de.js`: centralize all new German labels and validation copy.
- Create `src/tests/Feature/CustomProcessCompetenceTest.php`: school CRUD, authorization, ordering, activation, and visibility behavior.
- Modify `src/tests/Feature/PhaseNineTest.php`: custom competence observation evidence and `ne` behavior.
- Modify `src/tests/Feature/PhaseElevenTest.php`: live draft behavior, confirmation snapshots, and invalid-level handling.
- Create `src/tests/frontend/customProcessCompetences.test.js`: scale rendering and school/group UI behavior.

---

### Task 1: Persist the school scale and custom competence definitions

**Files:**
- Create the three migrations listed in the File Map.
- Modify `src/app/Models/School.php`.
- Create `src/app/Models/CustomProcessCompetence.php`.
- Create `src/app/Policies/CustomProcessCompetencePolicy.php`.
- Create `src/tests/Feature/CustomProcessCompetenceTest.php`.

**Interfaces:**
- Produces `School::customProcessCompetences(): HasMany`.
- Produces `CustomProcessCompetence` with fillable `school_id`, `text`, `position`, and `is_active`.
- Produces a school setting named `observation_scale_interval_count` with a default of `4`.
- Produces policy checks that require the authenticated user’s organization to match the school’s organization.

- [ ] **Step 1: Write failing persistence and authorization tests**

Add tests covering: a school defaults to four intervals; a custom competence belongs to exactly one school; competence text and position persist; deleting a used competence is not the supported operation; a user from another organization receives 403/404 according to existing policy conventions; and ordering is deterministic by `position`, then `id`.

- [ ] **Step 2: Run the focused test and verify failure**

Run: `./roo test --compact tests/Feature/CustomProcessCompetenceTest.php`

Expected: FAIL because the migrations, model, relationship, and policy do not exist yet.

- [ ] **Step 3: Add reversible migrations and models**

Use a nullable-safe migration for existing schools, then backfill `4` and make the setting non-null. Validate and constrain the interval count to `2..6`. Add a `custom_process_competences` table with a foreign key to `schools`, required text, unsigned position, active flag, timestamps, and an index on `(school_id, is_active, position)`. Do not add an education-plan foreign key. Make the custom competence inactive rather than hard-deletable once referenced.

- [ ] **Step 4: Implement the policy and model relationships**

Use the existing `SchoolPolicy` organization scope as the authorization boundary. Add `School::customProcessCompetences()` ordered by position. Add `CustomProcessCompetence::school()` and casts for `is_active` and `position`.

- [ ] **Step 5: Run the focused test and inspect the schema**

Run: `./roo test --compact tests/Feature/CustomProcessCompetenceTest.php`

Expected: PASS, with the test database migrated only in the isolated test environment.

- [ ] **Step 6: Commit the persistence slice**

Run:

```bash
git add src/database/migrations src/app/Models/School.php src/app/Models/CustomProcessCompetence.php src/app/Policies/CustomProcessCompetencePolicy.php src/tests/Feature/CustomProcessCompetenceTest.php
git commit -m "feat(assessment): schulische Prozesskompetenzen speichern"
```

### Task 2: Add school administration and immediate scale configuration

**Files:**
- Create `src/app/Http/Requests/UpdateSchoolObservationScaleRequest.php`.
- Modify `src/app/Http/Controllers/SchoolController.php` and `src/routes/web.php`.
- Modify `src/resources/js/Pages/Schools/Show.vue` and `src/resources/js/i18n/de.js`.
- Extend `src/tests/Feature/CustomProcessCompetenceTest.php`.
- Create `src/tests/frontend/customProcessCompetences.test.js`.

**Interfaces:**
- Add `PUT /schulen/{school}/beobachtungsskala` named `schools.observation-scale.update`.
- Request payload: `{ observation_scale_interval_count: number, competences: [{ id?: number, text: string, position: number, is_active: boolean }] }`.
- Response behavior: redirect back with a German success toast; all future Inertia loads resolve the new setting immediately.

- [ ] **Step 1: Write failing request/controller tests**

Cover minimum/maximum interval validation, required competence text, duplicate positions, foreign competence IDs, inactive competence preservation, and immediate persistence for all groups sharing the school. Add a test that a user can update only a school in their organization.

- [ ] **Step 2: Run backend tests and frontend tests to verify failure**

Run: `./roo test --compact tests/Feature/CustomProcessCompetenceTest.php` and `./roo npm run test:unit -- --run tests/frontend/customProcessCompetences.test.js`

Expected: FAIL because the route, request, controller payload, and Vue component behavior are absent.

- [ ] **Step 3: Implement the validated school update**

Authorize the school update, validate an integer interval count in the chosen bounded range, validate each text, and update the school plus competence rows inside one transaction. Existing IDs must belong to the school. Missing existing rows are deactivated, not deleted, when they have historical references; new rows receive the next stable ID. Reject a duplicate position server-side.

- [ ] **Step 4: Build the school UI**

In `Schools/Show.vue`, add a card titled “Eigene Prozesskompetenzen und Beobachtungsskala”. Show the generated labels from `+` through the configured count plus a separate `ne` item. Provide add, edit, reorder, and deactivate controls. Keep all labels in `de.js`; display server errors through the existing form error pattern.

- [ ] **Step 5: Verify immediate propagation and UI rendering**

Run: `./roo test --compact tests/Feature/CustomProcessCompetenceTest.php` and `./roo npm run test:unit -- --run tests/frontend/customProcessCompetences.test.js`

Expected: PASS; a subsequent group request sees the active school definitions without a group-level assignment record.

- [ ] **Step 6: Commit the administration slice**

```bash
git add src/app/Http/Requests/UpdateSchoolObservationScaleRequest.php src/app/Http/Controllers/SchoolController.php src/routes/web.php src/resources/js/Pages/Schools/Show.vue src/resources/js/i18n/de.js src/tests/Feature/CustomProcessCompetenceTest.php src/tests/frontend/customProcessCompetences.test.js
git commit -m "feat(assessment): Beobachtungsskalen je Schule verwalten"
```

### Task 3: Record custom competence evidence during lessons

**Files:**
- Modify `src/database/migrations/2026_09_01_120000_extend_competence_evidences_for_custom_competences.php`: add the custom evidence columns and constraints.
- Modify `src/app/Models/CompetenceEvidence.php`.
- Modify `src/app/Http/Controllers/LessonWorkspaceController.php`.
- Modify `src/resources/js/Pages/Lessons/Show.vue` and `src/resources/js/i18n/de.js`.
- Modify `src/tests/Feature/PhaseNineTest.php`.

**Interfaces:**
- Evidence accepts exactly one of `teaching_unit_competency_id` and `custom_process_competence_id`.
- Custom scale values are integer levels `1..observation_scale_interval_count` or the literal status `ne`.
- Custom evidence is exposed only when the lesson’s teaching group has `grading_model = observation_scales`.

- [ ] **Step 1: Write failing evidence tests**

Cover loading active school competences into the lesson observation workspace, saving one custom level and `ne`, rejecting an out-of-range level, rejecting a custom competence from another school, and rejecting custom evidence when the group is not in `observation_scales` mode.

- [ ] **Step 2: Run the Phase 9 test to verify failure**

Run: `./roo test --compact tests/Feature/PhaseNineTest.php`

Expected: FAIL because `competence_evidences` currently requires only a teaching-unit competence and the controller does not expose custom definitions.

- [ ] **Step 3: Extend the evidence schema and model**

Make the existing teaching-unit foreign key nullable, add a nullable custom-competence foreign key, and enforce the exactly-one-reference rule with a PostgreSQL check constraint. Store custom evidence with nullable `custom_scale_level` (an integer from `1` through the current interval count) and nullable `custom_scale_status` (`ne`); when the custom reference is present, enforce that exactly one custom scale value is present, and do not silently overload unrelated imported-competence semantics.

- [ ] **Step 4: Implement scoped controller validation**

Load active custom competences through the lesson group’s school only for `observation_scales`. Validate student membership, competence ownership, mode, and current level range in the request/controller transaction. Preserve current imported-competence behavior unchanged.

- [ ] **Step 5: Add the fast observation UI**

Render one row per student and custom competence with buttons for generated plus labels and `ne`. Keep the existing lesson workspace keyboard/table behavior. Show stale values from a reduced scale as a correction state instead of remapping them.

- [ ] **Step 6: Run tests and commit**

Run: `./roo test --compact tests/Feature/PhaseNineTest.php tests/Feature/CustomProcessCompetenceTest.php` and `./roo npm run test:unit -- --run tests/frontend/customProcessCompetences.test.js`

Expected: PASS.

```bash
git add src/database/migrations src/app/Models/CompetenceEvidence.php src/app/Http/Controllers/LessonWorkspaceController.php src/resources/js/Pages/Lessons/Show.vue src/resources/js/i18n/de.js src/tests/Feature/PhaseNineTest.php
git commit -m "feat(assessment): eigene Kompetenzen beobachten"
```

### Task 4: Use live definitions in drafts and snapshot confirmed evaluations

**Files:**
- Create `src/database/migrations/2026_09_01_130000_create_student_evaluation_observation_scales_table.php`.
- Create `src/app/Models/StudentEvaluationObservationScale.php`.
- Modify `src/app/Models/StudentEvaluation.php`.
- Modify `src/app/Http/Controllers/EvaluationController.php`.
- Modify `src/resources/js/Pages/Evaluations/Edit.vue`.
- Modify `src/tests/Feature/PhaseElevenTest.php`.

**Interfaces:**
- Draft evaluations resolve active school definitions and current interval count on load.
- Confirmation writes one immutable scale-result row per selected custom competence, containing `custom_process_competence_id` when still available, text snapshot, position snapshot, interval-count snapshot, and level/status.
- Confirmed evaluations cannot be changed through the draft update path.

- [ ] **Step 1: Write failing evaluation tests**

Cover draft loading with active custom competences, immediate text and scale-count changes in an existing draft, confirmation snapshots, preservation after competence deactivation, and a reduced-scale draft whose old level is flagged for correction rather than automatically changed.

- [ ] **Step 2: Run Phase 11 tests to verify failure**

Run: `./roo test --compact tests/Feature/PhaseElevenTest.php`

Expected: FAIL because evaluations currently store only free text/status and have no structured custom-scale results.

- [ ] **Step 3: Add the immutable result table/model**

Create a table linked to `student_evaluations` with nullable custom competence ID, required text/position/interval-count snapshots, nullable integer level, a constrained `ne` status, and timestamps. Add a unique key for evaluation plus competence. The down migration must remove only this table.

- [ ] **Step 4: Load live definitions into the evaluation editor**

In `EvaluationController::edit`, verify the evaluation’s group and school scope, then pass the current active definitions and interval count only for observation-scale groups. Keep confirmed result rows visible even when their definition is inactive.

- [ ] **Step 5: Confirm inside a transaction**

When status changes to `confirmed`, validate every submitted level against the current school count, reject unresolved stale levels with a German correction error, write the snapshots, set `confirmed_at`, and prevent later draft mutation. When status remains `draft`, use live definitions and do not freeze text or count snapshots.

- [ ] **Step 6: Add the evaluation UI and verify**

Render each custom competence with generated plus labels and `ne`, show a correction warning for stale levels, and keep the final selection manually editable before confirmation. Run: `./roo test --compact tests/Feature/PhaseElevenTest.php` and `./roo npm run build`.

Expected: PASS and a successful Vite build.

- [ ] **Step 7: Commit the evaluation slice**

```bash
git add src/database/migrations src/app/Models/StudentEvaluation.php src/app/Models/StudentEvaluationObservationScale.php src/app/Http/Controllers/EvaluationController.php src/resources/js/Pages/Evaluations/Edit.vue src/tests/Feature/PhaseElevenTest.php
git commit -m "feat(assessment): Beobachtungsskalen in Bewertungen sichern"
```

### Task 5: Full focused verification and documentation alignment

**Files:**
- Modify `build/masterplan.md` with the completed phase/module note.
- Modify `build/masterplan.md` only after the focused verification confirms the delivered behavior.

- [ ] **Step 1: Run focused backend coverage**

Run: `./roo test --compact tests/Feature/CustomProcessCompetenceTest.php tests/Feature/PhaseNineTest.php tests/Feature/PhaseElevenTest.php`

Expected: PASS, including organization scope, mode filtering, immediate draft changes, `ne`, deactivation, reduced-scale correction, and confirmation snapshots.

- [ ] **Step 2: Run focused frontend tests and build**

Run: `./roo npm run test:unit -- --run tests/frontend/customProcessCompetences.test.js` and `./roo npm run build`

Expected: PASS with no Vue template or localization errors.

- [ ] **Step 3: Run formatting and repository checks**

Run: `./roo pint` and `git diff --check`

Expected: changed PHP files are formatted and the diff has no whitespace errors. Report unrelated pre-existing Pint failures separately.

- [ ] **Step 4: Update the masterplan and commit documentation**

Document the delivered school-level custom process competence and observation-scale capability under the relevant assessment/evaluation phase without claiming broader evaluation features that were not implemented.

```bash
git add build/masterplan.md
git commit -m "docs(assessment): Beobachtungsskalen im Masterplan dokumentieren"
```

## Completion Checklist

- [ ] All five tasks have their focused tests passing.
- [ ] The school UI uses only centralized German localization strings.
- [ ] Imported and custom process competence references remain distinct.
- [ ] Active definitions propagate immediately to matching groups.
- [ ] Confirmed evaluations retain immutable snapshots.
- [ ] Migrations are reversible and no development database reset was used.
- [ ] Focused Pint, frontend build/tests, and `git diff --check` are complete.
