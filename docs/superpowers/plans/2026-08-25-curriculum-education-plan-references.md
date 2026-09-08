# Curriculum EducationPlan References – abgeschlossener Implementierungsplan

**Status:** Abgeschlossen. Die Curriculumreferenzen und die direkte
Bildungsplan-Kompetenzarchitektur sind im aktuellen Stand umgesetzt.

Dieser Plan ist als abgeschlossener Implementierungsnachweis dokumentiert; die
Checkboxen spiegeln den umgesetzten Stand wider.

**Goal:** Replace curriculum-owned competency data with denomination-aware direct references to official `EducationPlanCompetency` records and make all curriculum imports resolve those references safely.

**Architecture:** Rename the curriculum competency model/table to a reference model/table containing only topic, official EducationPlan competency, denomination, kind, and position. Resolve imported identifiers against the matching curriculum EducationPlan binding, fail transactionally on missing or ambiguous candidates, and migrate all consumers to load official competency data directly.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, Pest, Vue 3, Inertia, Vite, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-08-25-curriculum-education-plan-references-design.md`

## Global Constraints

- The user-facing application remains fully German; source code and technical identifiers remain English.
- Official competency text, number, variants, and hierarchy come only from `EducationPlanCompetency` and its relations.
- Curriculum topics, perspectives, profiles, source snapshots, and the curriculum-to-topic selection remain intact.
- Imports never guess when an identifier is unresolved or ambiguous and must roll back the curriculum transaction.
- Tests must use the isolated test database through `./roo`; never reset the persistent Docker development database.
- Preserve unrelated worktree changes and commit only this feature's files.

### Task 1: Establish failing reference-model and migration tests

**Files:**
- Modify: `src/tests/Feature/CurriculumImportTest.php`
- Modify: `src/tests/Feature/PhaseSixOneTest.php`
- Create: `src/tests/Feature/CurriculumEducationPlanReferenceTest.php`
- Create: `src/database/migrations/2026_08_25_120000_rename_curriculum_topic_competencies_to_education_plan_references.php`

**Interfaces:**
- Produces the required database contract for `curriculum_topic_education_plan_references` and the future `CurriculumTopicEducationPlanReference` model.
- Preserves `education_plan_competency_id`, denomination, kind, position, and topic IDs from existing rows.

- [x] **Step 1: Write the failing migration/model contract test**

Add a test that creates a curriculum topic and official EducationPlan competency, inserts one legacy-style reference fixture through the test schema, runs the application migration path, and asserts the new table contains only the reference columns and the official competency foreign key. Assert the old duplicated columns `external_identifier`, `display`, `text`, and `raw_text` are absent from the new table.

- [x] **Step 2: Write the failing official-data behavior test**

Create a curriculum topic reference with no curriculum text fields and load it with `educationPlanCompetency.area` and `educationPlanCompetency.variants`. Assert the returned number/text comes from the EducationPlan record, proving the curriculum reference does not provide a fallback text.

- [x] **Step 3: Run the focused tests and verify the expected failure**

Run:

```bash
./roo test --compact tests/Feature/CurriculumEducationPlanReferenceTest.php
```

Expected: failure because the new table/model and direct relationship do not exist yet.

- [x] **Step 4: Define the reversible migration contract**

Implement the migration so `up()` renames `curriculum_topic_competencies` to `curriculum_topic_education_plan_references`, drops the duplicated text/identifier columns, and keeps the official competency foreign key. Before dropping columns, detect rows with a null `education_plan_competency_id` and throw a clear `RuntimeException` so invalid legacy data cannot be silently lost. Implement `down()` by restoring the old table name and re-adding legacy columns as nullable compatibility columns; do not fabricate curriculum-owned text.

- [x] **Step 5: Run the focused migration test**

Run:

```bash
./roo test --compact tests/Feature/CurriculumEducationPlanReferenceTest.php
```

Expected: migration assertions pass after the model and schema test fixture are updated to the new contract.

- [x] **Step 6: Commit the schema contract**

```bash
git add src/database/migrations/2026_08_25_120000_rename_curriculum_topic_competencies_to_education_plan_references.php src/tests/Feature/CurriculumEducationPlanReferenceTest.php src/tests/Feature/CurriculumImportTest.php src/tests/Feature/PhaseSixOneTest.php
git commit -m "refactor(curricula): ersetze Kompetenzzeilen durch Planreferenzen"
```

### Task 2: Replace the model and curriculum relationships

**Files:**
- Delete: `src/app/Models/CurriculumTopicCompetency.php`
- Create: `src/app/Models/CurriculumTopicEducationPlanReference.php`
- Modify: `src/app/Models/CurriculumTopic.php`
- Modify: `src/app/Models/EducationPlanCompetency.php`
- Modify: `src/tests/Feature/CurriculumEducationPlanReferenceTest.php`

**Interfaces:**
- `CurriculumTopic::educationPlanReferences(): HasMany` returns `CurriculumTopicEducationPlanReference`.
- `CurriculumTopicEducationPlanReference::educationPlanCompetency(): BelongsTo` returns the official record.
- `EducationPlanCompetency::curriculumTopicReferences(): HasMany` returns only reference rows.
- Unterrichtseinheiten verwenden den direkten Pivot
  `teaching_unit_education_plan_competencies`; dessen
  `education_plan_competency_id` bleibt autoritativ.

- [x] **Step 1: Add failing relationship assertions**

Assert that a topic's `educationPlanReferences` relation returns the reference model and that its nested official competency returns the EducationPlan text. Assert that unit and lesson planning use direct official competency IDs.

- [x] **Step 2: Implement the slim reference model**

Create the fillable model with timestamps disabled and only `curriculum_topic_id`, `education_plan_competency_id`, `denomination`, `competency_kind`, and `position`. Add the two direct `BelongsTo`/`HasMany` relationships with explicit foreign keys.

- [x] **Step 3: Update the owning models**

Replace `competencies()` on `CurriculumTopic` with `educationPlanReferences()` and replace the reverse EducationPlan relationship. Update the direct unit and lesson pivots and all model imports.

- [x] **Step 4: Run the focused model tests**

Run:

```bash
./roo test --compact tests/Feature/CurriculumEducationPlanReferenceTest.php tests/Feature/PhaseSixOneTest.php
```

Expected: all new relationship assertions pass; remaining consumer failures identify the migration sites for Task 4.

- [x] **Step 5: Commit the model boundary**

```bash
git add src/app/Models src/tests/Feature/CurriculumEducationPlanReferenceTest.php src/tests/Feature/PhaseSixOneTest.php
git commit -m "refactor(curricula): benenne Planreferenzmodell eindeutig"
```

### Task 3: Make curriculum import resolution denomination- and binding-aware

**Files:**
- Modify: `src/app/Actions/Curricula/ImportCurriculum.php`
- Modify: `src/tests/Feature/CurriculumImportTest.php`
- Modify: `src/tests/Feature/CurriculumEducationPlanReferenceTest.php`
- Modify: `data/curricula/SCHEMA.md` only if the implemented diagnostic/input contract differs from the documented format

**Interfaces:**
- `ImportCurriculum::execute()` remains the public entry point and returns the same curriculum/version/import-run structure on success.
- Import diagnostics include topic identifier/title, source competency identifier, denomination, and candidate EducationPlan bindings.
- The importer creates only `CurriculumTopicEducationPlanReference` rows with a non-null official competency ID.

- [x] **Step 1: Add failing denomination-resolution tests**

Create two EducationPlans with the same `external_identifier` competency number but different plan bindings and official texts. Import a curriculum containing evangelical and catholic references and assert each reference points to the competency from its matching denomination binding.

- [x] **Step 2: Add failing unresolved/ambiguous rollback tests**

Add one test with a missing identifier and one with a denomination that matches multiple candidate bindings. Assert `InvalidArgumentException` (or the chosen domain exception), no curriculum/version/topics remain, no reference rows remain, and the import run records `failed` with a diagnostic containing the topic and identifier.

- [x] **Step 3: Run the new tests and confirm RED**

Run:

```bash
./roo test --compact tests/Feature/CurriculumImportTest.php tests/Feature/CurriculumEducationPlanReferenceTest.php
```

Expected: the new tests fail because current resolution searches all plan versions and persists nullable/unofficial curriculum fields.

- [x] **Step 4: Implement binding-aware candidate selection**

Build a lookup from the current version's `CurriculumEducationPlanBinding` rows. For a denominational source entry, restrict candidates to the binding whose denomination matches. For a `common` perspective or process entry, use the explicitly applicable binding; accept one candidate only. Query `EducationPlanCompetency` by the source identifier and the selected EducationPlan version IDs, then reject zero or more than one result with a diagnostic exception.

- [x] **Step 5: Persist only direct references**

Change the import helper to accept a resolved `EducationPlanCompetency` and write only the topic ID, official competency ID, denomination, kind, and position. Do not persist `display`, source text, raw text, or a curriculum external identifier.

- [x] **Step 6: Run the import tests GREEN**

Run:

```bash
./roo test --compact tests/Feature/CurriculumImportTest.php tests/Feature/CurriculumEducationPlanReferenceTest.php
```

Expected: denomination-specific resolution, missing/ambiguous rollback, import statistics, and existing valid imports pass.

- [x] **Step 7: Commit importer behavior**

```bash
git add src/app/Actions/Curricula/ImportCurriculum.php src/tests/Feature/CurriculumImportTest.php src/tests/Feature/CurriculumEducationPlanReferenceTest.php data/curricula/SCHEMA.md
git commit -m "fix(curricula): löse Kompetenznummern über Planbindungen auf"
```

### Task 4: Migrate curriculum controller, copying, and curriculum UI data

**Files:**
- Modify: `src/app/Http/Controllers/CurriculumController.php`
- Modify: `src/resources/js/Pages/Curricula/Show.vue`
- Modify: `src/resources/js/Pages/Curricula/Compare.vue` if its props use competency fields
- Modify: `src/tests/Feature/CurriculumImportTest.php`

**Interfaces:**
- Curriculum controller props expose references with nested official EducationPlan data.
- Curriculum edit requests submit official EducationPlan competency IDs, denomination, kind, and position.
- Copying a curriculum version copies only the direct reference fields.

- [x] **Step 1: Add failing copy/edit assertions**

Assert that copying a version preserves reference IDs and metadata while no curriculum text columns are written. Assert that editing a topic rejects an official competency ID not belonging to the current version's bound plans and accepts a valid bound ID.

- [x] **Step 2: Replace controller relations and payloads**

Update `with()` chains, copy loops, topic update validation, and resolution helpers to use `educationPlanReferences`. Remove all reads/writes of `display`, `text`, `raw_text`, and `external_identifier` from curriculum competency payloads. Validate reference IDs against bound EducationPlan versions and preserve the existing denominator/kind/position semantics.

- [x] **Step 3: Update curriculum page formatting**

Change Vue bindings to read number/text/variants from `reference.education_plan_competency` and retain German UI labels and denomination presentation. Do not add a local text fallback from the curriculum reference.

- [x] **Step 4: Run focused backend/frontend checks**

Run:

```bash
./roo test --compact tests/Feature/CurriculumImportTest.php tests/Feature/CurriculumEducationPlanReferenceTest.php
./roo npm run test:unit -- --run
```

Expected: curriculum import/copy/edit tests pass and frontend tests have no curriculum-reference regressions.

- [x] **Step 5: Commit controller/UI migration**

```bash
git add src/app/Http/Controllers/CurriculumController.php src/resources/js/Pages/Curricula src/tests/Feature/CurriculumImportTest.php src/tests/Feature/CurriculumEducationPlanReferenceTest.php
git commit -m "refactor(curricula): zeige offizielle Planinhalte an"
```

### Task 5: Remove curriculum fallbacks from planning, lessons, assessments, and overview services

**Files:**
- Modify: `src/app/Services/CompetencyResolver.php`
- Modify: `src/app/Services/TeachingGroupCompetencyOverview.php`
- Modify: `src/app/Services/YearPlanningWorkspace.php`
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/app/Http/Controllers/LessonWorkspaceController.php`
- Modify: `src/app/Http/Controllers/TeachingGroupController.php`
- Modify: `src/app/Http/Controllers/YearPlanController.php`
- Modify: `src/resources/js/Components/Planning/CompetencyPickerModal.vue`
- Modify: `src/resources/js/Pages/YearPlans/Show.vue`
- Modify: `src/tests/Feature/PhaseSixOneTest.php`
- Modify: `src/tests/Feature/PhaseTenTest.php`
- Modify: `src/tests/Unit/CompetencyResolverTest.php`

**Interfaces:**
- Planning and assessment data use `education_plan_competency_id` and direct `educationPlanCompetency` relations.
- Curriculum references remain available only for topic membership, denomination filtering, kind, and ordering.
- No production code calls `curriculumCompetency` or reads curriculum competency text fields.

- [x] **Step 1: Add failing regression assertions**

Create a reference whose legacy source text differs from the official EducationPlan text. Assert resolver, year-plan presentation, assessment grouping, and lesson scan metadata all use the official text/identifier and never the legacy value.

- [x] **Step 2: Update backend eager loads and mappings**

Replace `curriculumCompetency` eager-load paths with `educationPlanReferences` where curriculum topic membership is needed. Remove fallback chains that derive area, identifier, kind, or text from a curriculum competency. Keep direct EducationPlan eager loads for every consumer that renders official data.

- [x] **Step 3: Normalize overview and workspace calculations**

Use the direct EducationPlan ID as the identity for covered/planned competence calculations. Keep a separate reference ID only when grouping curriculum topic membership; do not compare source identifiers to determine identity.

- [x] **Step 4: Update Vue competency presentation**

Simplify `competencyText` and picker representation to use `education_plan_competency`/`educationPlanCompetency`, with only local wording as the intentional user override. Remove curriculum text/display/raw-text branches while retaining denomination and content/process grouping.

- [x] **Step 5: Run all affected tests**

Run:

```bash
./roo test --compact tests/Feature/PhaseSixOneTest.php tests/Feature/PhaseTenTest.php tests/Unit/CompetencyResolverTest.php tests/Feature/CurriculumEducationPlanReferenceTest.php
./roo npm run test:unit -- --run
```

Expected: all direct-reference regressions pass and no affected consumer references the removed model.

- [x] **Step 6: Commit consumer migration**

```bash
git add src/app/Services src/app/Http/Controllers/AssessmentController.php src/app/Http/Controllers/LessonWorkspaceController.php src/app/Http/Controllers/TeachingGroupController.php src/app/Http/Controllers/YearPlanController.php src/resources/js/Components/Planning/CompetencyPickerModal.vue src/resources/js/Pages/YearPlans/Show.vue src/tests/Feature/PhaseSixOneTest.php src/tests/Feature/PhaseTenTest.php src/tests/Unit/CompetencyResolverTest.php
git commit -m "refactor(kompetenzen): nutze EducationPlan direkt"
```

### Task 6: Run real curriculum imports, fix data-resolution gaps, and document results

**Files:**
- Modify: `src/tests/Feature/CurriculumImportTest.php`
- Modify: `src/tests/Feature/CurriculumEducationPlanReferenceTest.php`
- Modify: `src/app/Actions/Curricula/ImportCurriculum.php` only for demonstrated resolution defects
- Modify: `src/app/Services` or controllers only for demonstrated consumer defects
- Modify: `build/decisions/0006-curriculum-relational-import-und-ableitungen.md`
- Modify: `build/masterplan.md` if the curriculum architecture status or phase description changes

**Interfaces:**
- The importer remains strict: every stored curriculum reference has an official EducationPlan target.
- Import failures are classified and fixed through source-aware resolution, never by restoring curriculum-owned competency text.

- [x] **Step 1: Run the focused complete curriculum import suite**

Run:

```bash
./roo test --compact tests/Feature/CurriculumImportTest.php tests/Feature/CurriculumEducationPlanReferenceTest.php
```

Record each failure's topic, source identifier, denomination, and candidate bindings from the diagnostic.

- [x] **Step 2: Inspect the curriculum and EducationPlan source data**

For each failure, compare the JSON reference identifier and denomination with the relevant `education_plan_bindings` and imported EducationPlan external identifiers. Correct only demonstrable normalization rules such as identifier formatting or explicit common-binding selection; do not broaden matching across unrelated plans.

- [x] **Step 3: Add a regression fixture for every importer fix**

Encode each fixed case as a small test payload or targeted assertion so future imports prove the same resolution behavior and ambiguity remains rejected.

- [x] **Step 4: Run the complete relevant suite and build**

Run:

```bash
./roo test --compact tests/Feature/CurriculumImportTest.php tests/Feature/CurriculumEducationPlanReferenceTest.php tests/Feature/PhaseSixOneTest.php tests/Feature/PhaseTenTest.php tests/Unit/CompetencyResolverTest.php
./roo npm run test:unit -- --run
./roo npm run build
git diff --check
```

Expected: focused backend/frontend tests and Vite build pass; `git diff --check` produces no output. Report unrelated known failures separately rather than weakening this feature's assertions.

- [x] **Step 5: Update architecture documentation**

Record that curriculum competency records are EducationPlan references, document the binding-aware resolution rule and strict failure behavior in ADR 0006, and update the masterplan curriculum phase only with verified status.

- [x] **Step 6: Commit verification and documentation**

```bash
git add src/app src/tests data/curricula/SCHEMA.md build/decisions/0006-curriculum-relational-import-und-ableitungen.md build/masterplan.md
git commit -m "docs(curricula): dokumentiere direkte Planreferenzen"
```

## Final verification

After all tasks, verify that:

```bash
rg -n "CurriculumTopicCompetency|curriculumCompetency|curriculum_topic_competenc" src/app src/database src/resources src/tests
```

returns no production references except the deliberate reversible migration compatibility code and migration test fixtures. Then run the complete relevant suite, frontend unit tests, Vite build, and `git diff --check` again before claiming completion.
