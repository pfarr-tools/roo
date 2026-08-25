# Curriculum EducationPlan References

## Status

Proposed and approved in chat on 25 August 2026; implementation follows after
review of this written specification.

## Goal

Curricula remain structured collections of topics, competency selections,
denominational perspectives, and teaching guidance. Competencies are not
owned or described by a curriculum. Every curriculum competency selection is
instead a direct reference to the official competency stored in an associated
EducationPlan.

## Current problem

`CurriculumTopicCompetency` stores an `education_plan_competency_id` but also
duplicates an external identifier, display text, and raw text. Consumers then
fall back between curriculum data and EducationPlan data. Import resolution
currently searches all EducationPlan versions bound to a curriculum without
using the denomination/plan binding to make the reference unambiguous.

## Architecture

Replace the curriculum competency concept with a pure reference entity named
`CurriculumTopicEducationPlanReference`. Its persisted fields are:

- `curriculum_topic_id`
- `education_plan_competency_id` (required foreign key)
- `denomination` (nullable for common/process references)
- `competency_kind` (`content` or `process`)
- `position`

The reference has no curriculum-owned competency text, display label, raw text,
or independent competency identifier. The referenced `EducationPlanCompetency`
and its area/variants are the sole source for official number, text, levels,
and hierarchy. The original curriculum payload remains available through the
version import snapshot for auditability, but is not used as a fallback for
display or domain resolution.

The database migration must preserve existing rows by copying their valid
`education_plan_competency_id` values into the renamed table and must fail
clearly if legacy rows cannot be migrated. Existing consumers that need a
curriculum-to-topic relationship use the new reference relationship; planning,
assessment, and lesson competency records continue to use
`education_plan_competency_id` directly.

## Import and resolution

Curriculum import resolves every source competency reference before creating
the reference row. Resolution considers the competency's source identifier,
the reference denomination, and the curriculum version's EducationPlan
bindings. A denominational reference resolves against the binding for that
denomination; a common/process reference resolves against the unambiguous
applicable binding. The resolved EducationPlan competency must belong to an
EducationPlan version of the selected bound plan.

If zero or multiple candidates remain, the import records a useful diagnostic
containing the topic, source identifier, denomination, and candidate plans and
fails transactionally. No partially imported curriculum is accepted. The
import must not copy source competency text into the reference table.

When a curriculum has several bindings, two equal source numbers may validly
resolve to different official competencies because their denomination/plan
binding differs. If the source data does not provide enough information to
choose one, the importer reports the ambiguity instead of guessing.

## Application behavior

- Curriculum pages show official competency data through the EducationPlan
  relationship.
- Editing competency selections submits EducationPlan competency IDs and
  validates that each ID belongs to a plan bound to the current curriculum
  version.
- Denominational filtering remains a property of the reference row.
- Year planning no longer loads curriculum competency text or uses it as a
  fallback for areas or competencies.
- Assessment and lesson code no longer traverses `curriculumCompetency` to
  derive an EducationPlan competency.
- Copying curriculum versions copies reference IDs and their denomination,
  kind, and position only.

## Migration and compatibility

The change is implemented through a reversible migration. The old model and
relationships are removed after all application references are migrated. The
physical table may be renamed in the migration so existing data is retained;
the reverse migration restores the old table and columns only when the removed
legacy fields can be reconstructed from the reference data. No production
logic relies on the legacy text columns after the migration.

## Testing and import verification

Tests cover:

- migration preservation of reference rows;
- direct official-data loading without curriculum text fields;
- denomination-aware resolution across multiple EducationPlans;
- rejection of unresolved and ambiguous identifiers;
- transactional rollback after an import resolution error;
- curriculum copy/edit behavior;
- year-plan and assessment consumers using direct EducationPlan IDs.

An available curriculum import is run after implementation. Its diagnostics
are inspected, and each failure is classified as missing EducationPlan data,
identifier-format mismatch, denomination/binding mismatch, or a real source
data defect. Safe importer fixes are added and retested until the import can
resolve all valid references without guessing.

The supplied curriculum JSON files therefore provide a concrete `plan_code`
for each denominational binding. Incorrectly assigned source identifiers are
corrected in the JSON source instead of being hidden by importer fallbacks.

## Non-goals

- Redesigning EducationPlan import or its official text model.
- Removing curriculum topics, perspectives, profiles, or source snapshots.
- Introducing a generic polymorphic curriculum reference abstraction.
- Silently accepting unresolved references or using curriculum text as a
  substitute for official EducationPlan data.
