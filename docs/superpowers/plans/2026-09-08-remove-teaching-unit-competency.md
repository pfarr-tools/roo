# Remove TeachingUnitCompetency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `EducationPlanCompetency` the sole official competency entity and remove `TeachingUnitCompetency` without losing curriculum context, lesson assignments, evidence, assessments, or evaluation behavior.

**Architecture:** Replace the current unit-scoped competency model with direct EducationPlan foreign keys. Preserve unit-level curriculum context in a dedicated pivot, keep lesson assignments as a direct lesson-to-EducationPlan pivot, and keep custom process competencies separate from official competencies. Use additive migrations and explicit data assertions before dropping legacy structures.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Eloquent, Inertia/Vue 3, Pest, Vitest, Docker via `./roo`.

**Spec:** `build/decisions/0011-kompetenzpraesentation.md` and the competency definition in `build/masterplan.md`.

## Global Constraints

- The UI and validation messages remain German; code identifiers remain English.
- Existing curriculum, lesson, observation, assessment, evaluation, resource, and export behavior must remain available.
- Migrations are additive first and must preserve all existing rows and curriculum metadata.
- Tests use only the isolated test database and run through `./roo`.
- Student observations and ratings remain scoped and must not leak into logs or search.

### Task 1: Lock down the current invariants

**Files:** `src/tests/Feature/TeachingUnitCompetencyMigrationTest.php`

- [x] Add migration guards and schema tests for official references and preserved assignment context.
- [x] Run `./roo test --compact tests/Feature/TeachingUnitCompetencyMigrationTest.php` against the migrated schema.

### Task 2: Add the replacement schema and reversible backfill

**Files:** additive migrations under `src/database/migrations/`, `src/tests/Feature/TeachingUnitCompetencyMigrationTest.php`

- [x] Add a unit assignment pivot retaining assignment context and a direct official reference.
- [x] Add the direct official reference to the existing lesson pivot and preserve its curriculum reference.
- [x] Add nullable `education_plan_competency_id` to `competence_evidences` for official evidence alongside custom process evidence.
- [x] Backfill pivots and evidence with explicit guards against unmappable assignments.
- [x] Document the safe rollback boundary for the forward-only drop migration.
- [x] Run migration tests and verify the migrated schema.

### Task 3: Remove runtime dependencies on the legacy model

**Files:** `src/app/Models/`, `src/app/Http/Controllers/`, `src/app/Services/`

- [x] Change unit and lesson relations to direct EducationPlan competencies plus replacement pivot metadata.
- [x] Persist and validate direct official competency IDs using permitted EducationPlans.
- [x] Translate evidence, assessment, evaluation, resource, attachment, and export paths to direct official IDs.
- [x] Keep custom process evidence independent and preserve authorization and organization scoping.

### Task 4: Update frontend payloads and tests

**Files:** planning components under `src/resources/js/Components/Planning/`, lesson/year-plan pages, `src/tests/frontend/`

- [x] Use official competency IDs as option values and stable keys.
- [x] Preserve assignment metadata in unit planning requests.
- [x] Remove legacy competency payloads and update frontend tests.

### Task 5: Drop the legacy table and compatibility code

**Files:** final migration under `src/database/migrations/`, `src/app/Models/TeachingUnitCompetency.php`, all legacy reference matches, `build/masterplan.md`, new ADR

- [x] Add preconditions to the drop migration and drop legacy structures only after they pass.
- [x] Remove the legacy model and runtime references; route names remain stable for URLs.
- [x] Update architecture documentation and migration rollback notes.

### Task 6: Full verification and integration

- [x] Run `./roo test --compact` (346 tests, 2585 assertions).
- [x] Run `./roo npm run test:unit -- --run` (113 tests).
- [x] Run `./roo npm run build`.
- [x] Run `git diff --check` and a final runtime reference search.
- [x] Keep the development database untouched during verification.
- [x] Commit only after final review.
