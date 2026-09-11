# Search All Lesson Text Fields Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every text field on songs and lesson-related models searchable in both the global search and Scout payloads.

**Architecture:** Keep the existing global SQL search and Scout indexing, but derive each model's indexed fields from an explicit complete text-field list. Add missing searchable Lesson and LessonPhase models only if their results can be represented by the existing search page; recursively flatten JSON text values for assessment/task content where applicable.

**Tech Stack:** Laravel 13, Eloquent, Laravel Scout, PostgreSQL, Pest, Vue 3/Inertia.

**Spec:** User request: “For songs and any lesson-related Model, make sure that all text fields are considered in search.”

## Global Constraints

- Use `./roo test` with the isolated SQLite test database.
- Keep student records out of Meilisearch.
- Keep UI text in the central German frontend localization.
- Use English source identifiers and German user-facing labels.

### Task 1: Complete searchable field coverage

**Files:**
- Modify: `src/app/Models/Song.php`
- Modify: `src/app/Models/SongVersion.php`
- Modify: `src/app/Models/Lesson.php`
- Modify: `src/app/Models/LessonPhase.php`
- Modify: `src/app/Models/LessonTemplate.php`
- Modify: `src/app/Models/PhaseTemplate.php`
- Modify: `src/app/Models/TeachingUnit.php`
- Modify: `src/app/Models/UnitTemplate.php`
- Modify: `src/app/Models/ResourceReference.php`
- Modify: `src/app/Models/ResourceLink.php`
- Modify: `src/app/Models/MaterialItem.php`
- Modify: `src/app/Models/Assessment.php`
- Modify: `src/app/Models/AssessmentTask.php`
- Modify: `src/app/Search/SearchableFields.php`
- Test: `src/tests/Feature/SearchTest.php`

**Interfaces:** Existing `searchableFields(): array` remains the model contract; `searchablePayload()` must recursively include scalar text values inside cast arrays.

- [x] Write tests asserting song metadata, song-version lyrics/chords, lesson fields, phase fields, and nested task content appear in `search_text`.
- [x] Run the focused tests and verify they fail because fields are omitted or models are not searchable.
- [x] Add complete text-field lists, add `Searchable` to missing lesson models, and recursively flatten array payloads.
- [x] Run the focused tests and verify they pass.

### Task 2: Align global search with the complete field lists

**Files:**
- Modify: `src/app/Http/Controllers/SearchController.php`
- Modify: `src/resources/js/Pages/Search/Index.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/Feature/SearchTest.php`

**Interfaces:** The controller continues returning grouped result arrays; new lesson/phase groups include IDs and parent IDs needed by their links.

- [x] Add failing feature assertions for global matches in song lyrics/notes, lesson notes/homework, and phase didactic text.
- [x] Run the feature test and verify those assertions fail with the current per-column filters.
- [x] Expand each relevant SQL predicate to every text column and add Lesson/LessonPhase result groups with German labels and valid routes.
- [x] Run the feature test and verify all groups and fields are returned.

### Task 3: Verification and documentation

**Files:**
- Modify: `build/masterplan.md` only if the search coverage contract is not already documented.
- Test: `src/tests/Feature/SearchTest.php`

- [x] Run the complete relevant backend test set, Pint, frontend build, and `git diff --check`.
- [x] Confirm no student model gained Scout indexing and no unrelated files changed.
