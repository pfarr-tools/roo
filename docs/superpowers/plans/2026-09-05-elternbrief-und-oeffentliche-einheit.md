# Elternbrief und öffentliche Einheit – Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eine konkrete Unterrichtseinheit kann einen Elternbrief als DOCX/ODT erzeugen und über eine dauerhaft signierte Blade-Seite aktuelle, phasenbezogen freigegebene Materialien öffentlich bereitstellen.

**Architecture:** Die bestehenden relationalen Einheits-, Stunden-, Phasen- und Ressourcenbeziehungen bleiben die Quelle der Wahrheit. Ein zentraler Sichtbarkeitsresolver erzeugt sowohl die Daten für die öffentliche Blade-Seite als auch für den Elternbrief; separate signierte Downloadrouten prüfen beim Abruf erneut den aktuellen Freigabestatus. Die UI speichert Freigabestatus direkt an Material- oder Phasen-Zuordnungen.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL/SQLite-Testdatenbank, Vue 3/Inertia, Blade, PhpWord DOCX/ODT, Tecnick QR-Code, Vite, Comic Neue und Atkinson Hyperlegible Next.

**Spec:** [docs/superpowers/specs/2026-09-05-elternbrief-und-oeffentliche-einheit-design.md](</home/christoph/dev/pfarr.tools/roo/docs/superpowers/specs/2026-09-05-elternbrief-und-oeffentliche-einheit-design.md>)

## Global Constraints

- Die öffentliche Seite bezieht sich immer auf genau eine `TeachingUnit` in genau einer `TeachingGroup`.
- Öffentliche Materialien stammen ausschließlich aus aktuell freigegebenen konkreten Phasen-Zuordnungen.
- Freigabestatus sind `not_shared`, `shared_immediately` und `shared_with_lesson`; direkte Einheitsmaterialien dürfen nur die ersten beiden Werte verwenden.
- `shared_with_lesson` wird erst ab dem Startzeitpunkt der frühesten konkreten, nicht ausgefallenen Stunde sichtbar.
- Einheitsseite und Dateidownloads verwenden dauerhaft gültige signierte URLs; Dateidownloads prüfen den Status zusätzlich serverseitig.
- Die öffentliche Seite ist Blade ohne Login, Adminlayout, Inertia oder Menüverknüpfung.
- Überschriften verwenden Comic Neue bold, `h1` 24 pt; übrige Texte Atkinson Hyperlegible Next normal, 14 pt.
- Schülerdaten, Beobachtungen und Bewertungen dürfen weder öffentlich dargestellt noch indexiert werden.
- Tests laufen ausschließlich in der isolierten Testdatenbank; keine destruktiven Befehle gegen die Entwicklungsdatenbank.
- Fachliche UI-Texte bleiben zentral in `resources/js/i18n/de.js`; Controller bleiben dünn.

## File Map

- `src/app/Enums/PublicationStatus.php`: stabile Statuswerte und deutsche Darstellungslabels.
- `src/database/migrations/2026_09_05_120000_add_publication_to_teaching_units.php`: Einführungstext, Ersteller und Statusspalten/Pivotstatus.
- `src/app/Models/TeachingUnit.php`, `src/app/Models/ResourceReference.php`, `src/app/Models/ResourceLink.php`, `src/app/Models/LessonPhase.php`: Beziehungen, Fillables, Casts und Pivot-Attribute.
- `src/app/Services/TeachingUnitPublicView.php`, `src/app/Services/TeachingUnitPublicViewResolver.php`: typisierte, zeitabhängige öffentliche Projektion.
- `src/app/Http/Controllers/PublicTeachingUnitController.php`: signierte Blade-Seite und signierte Dateidownloads.
- `src/resources/views/public/teaching-units/show.blade.php`, `src/resources/views/public/layouts/teaching-unit.blade.php`, `src/resources/css/public-teaching-unit.scss`: öffentliches Layout und Fonts.
- `src/app/Documents/ParentLetterDocument.php`, `src/app/Documents/Templates/ParentLetterTemplate.php`, `src/app/Services/QrCodeRenderer.php`: strukturierter Elternbrief und QR-Code.
- `src/app/Http/Controllers/TeachingUnitDocumentController.php`: validierter Elternbrief-Download.
- `src/resources/js/Pages/TeachingUnits/Index.vue`, `src/resources/js/Components/Planning/PhaseResourcePicker.vue`, `src/resources/js/Components/Planning/LessonPhasesTab.vue`, `src/resources/js/Components/Planning/LessonEditorModal.vue`, `src/resources/js/i18n/de.js`: Editormodal, Phasenstatus und Exportaktion.
- `src/tests/Feature/TeachingUnitPublicPageTest.php`, `src/tests/Feature/ParentLetterTest.php`, `src/tests/Unit/TeachingUnitPublicViewResolverTest.php`, `src/tests/Unit/ParentLetterRenderingTest.php`, `src/tests/frontend/teachingUnitPublication.test.js`: vertikale Tests.

### Task 1: Relationales Datenmodell und Statusverträge

**Files:**
- Create: `src/app/Enums/PublicationStatus.php`
- Create: `src/database/migrations/2026_09_05_120000_add_publication_to_teaching_units.php`
- Modify: `src/app/Models/TeachingUnit.php`
- Modify: `src/app/Models/ResourceReference.php`
- Modify: `src/app/Models/ResourceLink.php`
- Modify: `src/app/Models/LessonPhase.php`
- Modify: `src/app/Http/Controllers/YearPlanController.php`
- Modify: `src/app/Http/Controllers/TeachingUnitController.php`
- Test: `src/tests/Feature/TeachingUnitTest.php`

**Interfaces:**
- Produces `PublicationStatus::NOT_SHARED`, `PublicationStatus::SHARED_IMMEDIATELY`, `PublicationStatus::SHARED_WITH_LESSON` and `PublicationStatus::allowsPublicAccess(...)`.
- Produces `TeachingUnit::$introduction_text`, `TeachingUnit::$created_by_user_id` and `TeachingUnit::creator(): BelongsTo`.
- Produces `publication_status` on direct resource/link records and on both phase-assignment pivots.

- [ ] **Step 1: Write failing persistence tests.** Cover default `not_shared`, unit introduction/creator persistence, pivot status persistence, and rejection of `shared_with_lesson` for a direct unit material.
- [ ] **Step 2: Run the focused test and verify failure.**

```bash
./roo test --compact tests/Feature/TeachingUnitTest.php
```

Expected: failure because the new columns, enum casts, and creator relationship do not exist.

- [ ] **Step 3: Add the reversible migration.** Add nullable `introduction_text`, nullable `created_by_user_id` with `nullOnDelete`, default `not_shared` status columns, and string status columns to `lesson_phase_resources` and `lesson_phase_resource_links` with composite-key-compatible indexes. Down migration removes all additions in reverse order.
- [ ] **Step 4: Add model contracts and creation behavior.** Cast all status columns to `PublicationStatus`, expose pivot attributes through `withPivot('publication_status')`, set `created_by_user_id` on new units and copied units, and preserve null for historical units.
- [ ] **Step 5: Enforce server-side status scope.** Validate direct unit statuses against `not_shared|shared_immediately`; validate phase statuses against all three values and verify every submitted resource/link belongs to the same organization, unit, lesson, and phase.
- [ ] **Step 6: Run the focused test and inspect the migration.**

```bash
./roo test --compact tests/Feature/TeachingUnitTest.php && git diff --check
```

- [ ] **Step 7: Commit the data contract.**

```bash
git add src/app/Enums/PublicationStatus.php src/database/migrations/2026_09_05_120000_add_publication_to_teaching_units.php src/app/Models src/app/Http/Controllers/YearPlanController.php src/app/Http/Controllers/TeachingUnitController.php src/tests/Feature/TeachingUnitTest.php
git commit -m "feat: ergänze freigabestatus für einheitenmaterial"
```

### Task 2: Zentrale öffentliche Projektion und Zeitlogik

**Files:**
- Create: `src/app/Services/TeachingUnitPublicView.php`
- Create: `src/app/Services/TeachingUnitPublicViewResolver.php`
- Create: `src/tests/Unit/TeachingUnitPublicViewResolverTest.php`
- Modify: `src/app/Models/ScheduledLesson.php`, `src/app/Models/ScheduleSlot.php`, `src/app/Models/Lesson.php`, `src/app/Models/LessonPhase.php`

**Interfaces:**
- Produces `TeachingUnitPublicViewResolver::resolve(TeachingUnit $unit, CarbonImmutable $now): TeachingUnitPublicView`.
- The projection contains `unit`, `creator`, `group`, `school`, `introductionText`, `competencies`, `scheduledLessons`, `visiblePhaseResources`, and `visiblePhaseLinks`.
- `visiblePhaseResources` exposes a separate signed download URL later, never a storage path.

- [ ] **Step 1: Write resolver tests.** Cover immediate visibility, pre-start hidden status, post-start visibility, no-slot hidden status, earliest non-cancelled slot for consecutive lessons, cancelled-slot exclusion, current titles/text/competencies, and omission of direct lesson/unit materials.
- [ ] **Step 2: Run the resolver tests to verify failure.**

```bash
./roo test --compact tests/Unit/TeachingUnitPublicViewResolverTest.php
```

- [ ] **Step 3: Implement eager-loading and visibility predicates.** Load only the concrete unit/group/school/creator, unit competencies, lessons with concrete schedule slots, phases, phase resource/link pivots, and safe resource metadata. Compare all timestamps in `Europe/Berlin`; require an actual slot and exclude cancelled slots for `shared_with_lesson`.
- [ ] **Step 4: Add cache-key and invalidation contract.** Define a unit-scoped cache key containing the unit identity and a version value that can be bumped by unit, lesson, phase, resource, link, pivot, group, school, or creator changes. The resolver must expose the next visibility timestamp so cache expiry cannot hide a material after its first eligible start.
- [ ] **Step 5: Run unit tests and `git diff --check`.**

### Task 3: Signierte Blade-Seite und Dateidownloads

**Files:**
- Create: `src/app/Http/Controllers/PublicTeachingUnitController.php`
- Create: `src/resources/views/public/layouts/teaching-unit.blade.php`
- Create: `src/resources/views/public/teaching-units/show.blade.php`
- Create: `src/resources/css/public-teaching-unit.scss`
- Modify: `src/routes/web.php`
- Modify: `src/vite.config.js`
- Create: `src/tests/Feature/TeachingUnitPublicPageTest.php`

**Interfaces:**
- Route `public.teaching-units.show` accepts a concrete unit identifier and Laravel's permanent `signed` middleware.
- Route `public.teaching-units.resources.download` accepts unit/resource identifiers plus the permanent signature.
- Controller methods call the resolver and never query storage or public data independently.

- [ ] **Step 1: Write feature tests.** Verify anonymous signed page access, invalid signature rejection, wrong-organization/wrong-unit rejection, Blade response without Inertia/admin shell, group/school/creator/content output, direct-material omission, immediate/lesson-timed material visibility, and separate signed file download.
- [ ] **Step 2: Run tests to verify failure.**

```bash
./roo test --compact tests/Feature/TeachingUnitPublicPageTest.php
```

- [ ] **Step 3: Add permanent signed routes and controller checks.** Generate page and file links with `URL::signedRoute` without an expiry. Verify unit/resource/phase relationships and current resolver visibility before returning Blade or `Storage::disk('local')->download(...)`; never expose `storage_path` in a view.
- [ ] **Step 4: Build the Blade layout and view.** Render only public data, list concrete scheduled slots, render file links with signed download URLs, render external URLs with `target="_blank"` and `rel="noreferrer"`, and include no navigation or authenticated shell.
- [ ] **Step 5: Bundle the existing fonts.** Add `@font-face` declarations pointing at `src/resources/fonts/ComicNeue-Bold.ttf` and `src/resources/fonts/AtkinsonHyperlegibleNext-Regular.otf`; set `h1` to 24pt Comic Neue bold and all other text to 14pt Atkinson normal, then add the public stylesheet as a Vite input used by the Blade layout.
- [ ] **Step 6: Add targeted cache invalidation hooks.** Invalidate the unit public cache after relevant model saves/deletes and when phase pivots change; cap time-based cache entries at the next eligible lesson start.
- [ ] **Step 7: Run feature tests, Vite, and diff validation.**

```bash
./roo test --compact tests/Feature/TeachingUnitPublicPageTest.php && ./roo npm run build && git diff --check
```

- [ ] **Step 8: Commit the public slice.**

```bash
git add src/app/Http/Controllers/PublicTeachingUnitController.php src/resources/views/public src/resources/css/public-teaching-unit.scss src/routes/web.php src/vite.config.js src/tests/Feature/TeachingUnitPublicPageTest.php
git commit -m "feat: veröffentliche einheiten über signierte blade-seite"
```

### Task 4: Elternbrief-Dokument und QR-Code

**Files:**
- Create: `src/app/Documents/ParentLetterDocument.php`
- Create: `src/app/Documents/Templates/ParentLetterTemplate.php`
- Create: `src/app/Services/QrCodeRenderer.php`
- Create: `src/app/Http/Controllers/TeachingUnitDocumentController.php`
- Modify: `src/app/Providers/AppServiceProvider.php`
- Modify: `src/routes/web.php`
- Create: `src/tests/Unit/ParentLetterRenderingTest.php`
- Create: `src/tests/Feature/ParentLetterTest.php`

**Interfaces:**
- `ParentLetterDocument` carries title, group, school, creator, introduction, competencies, scheduled lessons, optional public URL, and optional QR PNG bytes.
- `ParentLetterTemplate::key()` returns `parent-letter.default` and `render(Document $document): PhpWord`.
- `TeachingUnitDocumentController::download(Request $request, TeachingUnit $teachingUnit)` accepts validated `format=docx|odt`, saves the submitted introduction, resolves current public data, and returns an attachment.
- `QrCodeRenderer::png(string $url): string` returns PNG bytes using the installed Tecnick barcode package.

- [ ] **Step 1: Write document and endpoint tests.** Assert registry registration, DOCX/ODT ZIP output, German content, 24pt Comic Neue bold headings, 14pt Atkinson body text, QR only when a visible phase material exists, and no QR/URL otherwise.
- [ ] **Step 2: Run the tests to verify failure.**

```bash
./roo test --compact tests/Unit/ParentLetterRenderingTest.php tests/Feature/ParentLetterTest.php
```

- [ ] **Step 3: Implement the typed document and template.** Create PhpWord styles with `Comic Neue`/700/24pt for headings and `Atkinson Hyperlegible Next`/400/14pt for body text; use deterministic German date/time formatting and structured tables/lists for competencies and scheduled lessons.
- [ ] **Step 4: Implement QR generation and conditional public link.** Ask the resolver whether at least one phase material is currently visible; only then create the permanent page URL and QR PNG. Do not include storage paths or unpublished materials.
- [ ] **Step 5: Register the template and return downloads.** Register `ParentLetterTemplate` in `AppServiceProvider`, use `DocumentOutputFormat`, preserve the existing ODT post-processing pipeline, and generate a safe German filename from the unit/group context.
- [ ] **Step 6: Run tests, inspect ZIP members, and commit.**

```bash
./roo test --compact tests/Unit/ParentLetterRenderingTest.php tests/Feature/ParentLetterTest.php && git diff --check
git add src/app/Documents/ParentLetterDocument.php src/app/Documents/Templates/ParentLetterTemplate.php src/app/Services/QrCodeRenderer.php src/app/Http/Controllers/TeachingUnitDocumentController.php src/app/Providers/AppServiceProvider.php src/routes/web.php src/tests/Unit/ParentLetterRenderingTest.php src/tests/Feature/ParentLetterTest.php
git commit -m "feat: ergänze elternbrief als docx-und-odt-export"
```

### Task 5: Admin-UI für Einheit und Phase

**Files:**
- Modify: `src/resources/js/Pages/TeachingUnits/Index.vue`
- Modify: `src/resources/js/Components/Planning/PhaseResourcePicker.vue`
- Modify: `src/resources/js/Components/Planning/LessonPhasesTab.vue`
- Modify: `src/resources/js/Components/Planning/LessonEditorModal.vue`
- Modify: `src/resources/js/i18n/de.js`
- Create: `src/tests/frontend/teachingUnitPublication.test.js`

**Interfaces:**
- Unit editor keeps `introduction_text` in its form state and opens a dedicated `ParentLetterModal` with `format`, `introductionText`, and `processing` state.
- Phase picker emits `update:resource-publication-status` and `update:resource-link-publication-status` with IDs and `PublicationStatus` values.
- Lesson form submits phase pivot statuses alongside existing `resource_ids` and `resource_link_ids` without dropping unsaved phases.

- [ ] **Step 1: Add frontend tests.** Cover modal initialization from saved text, format selection, export request, three phase choices, two direct-unit choices, and preservation of existing resource selections.
- [ ] **Step 2: Run frontend tests to verify failure.**

```bash
./roo npm run test:unit -- --run tests/frontend/teachingUnitPublication.test.js
```

- [ ] **Step 3: Add central German labels.** Add labels for `Elternbrief`, `Einführungstext`, the three statuses, format choices, public-link/QR hints, and validation/download errors in `resources/js/i18n/de.js`.
- [ ] **Step 4: Implement the parent-letter modal.** Add an `Elternbrief` action to the unit edit modal, render the modal at 80% width and 80vh, load the saved introduction, submit the text plus format to the document route, and start the returned attachment download without losing the editor context.
- [ ] **Step 5: Add direct-unit selectors.** Show only `nicht freigegeben` and `sofort freigegeben` for direct unit files and URLs, include values in the existing unit update payload, and display server validation errors.
- [ ] **Step 6: Add phase selectors.** Show the three statuses beside each file/URL in the phase editor, initialize from pivot data, and submit statuses through `LessonEditorModal` while keeping material/link/song selection behavior unchanged.
- [ ] **Step 7: Run frontend tests, Vite, and diff validation.**

```bash
./roo npm run test:unit -- --run tests/frontend/teachingUnitPublication.test.js && ./roo npm run build && git diff --check
```

- [ ] **Step 8: Commit the admin UI slice.**

```bash
git add src/resources/js/Pages/TeachingUnits/Index.vue src/resources/js/Components/Planning/PhaseResourcePicker.vue src/resources/js/Components/Planning/LessonPhasesTab.vue src/resources/js/Components/Planning/LessonEditorModal.vue src/resources/js/i18n/de.js src/tests/frontend/teachingUnitPublication.test.js
git commit -m "feat: steuere öffentliche materialfreigaben im editor"
```

### Task 6: Integrierte Sicherheits-, Cache- und Rendering-Prüfung

**Files:**
- Modify: `src/tests/Feature/TeachingUnitPublicPageTest.php`
- Modify: `src/tests/Feature/ParentLetterTest.php`
- Modify: `src/tests/Unit/TeachingUnitPublicViewResolverTest.php`
- Modify: `src/tests/Unit/ParentLetterRenderingTest.php`
- Modify: `docs/superpowers/specs/2026-09-05-elternbrief-und-oeffentliche-einheit-design.md` only if implementation evidence requires a precise correction

- [ ] **Step 1: Add end-to-end authorization cases.** Prove organization isolation, old signed download rejection after status reset, hidden resources before lesson start, no-slot hiding, and no authenticated data leakage.
- [ ] **Step 2: Add mutation/cache cases.** Change introduction, unit title, competency, lesson slot, phase status, and resource metadata; assert the next public response contains the current value and never serves stale visibility.
- [ ] **Step 3: Validate generated artifacts.** Inspect DOCX `word/document.xml` and ODT `content.xml`/`styles.xml` for text, font names, sizes, QR relationship, and absent unpublished links. If LibreOffice is available, convert both to PDF and inspect at least the first page visually.
- [ ] **Step 4: Run the complete focused verification.**

```bash
./roo test --compact tests/Feature/TeachingUnitTest.php tests/Feature/TeachingUnitPublicPageTest.php tests/Feature/ParentLetterTest.php tests/Unit/TeachingUnitPublicViewResolverTest.php tests/Unit/ParentLetterRenderingTest.php && ./roo npm run test:unit -- --run tests/frontend/teachingUnitPublication.test.js && ./roo npm run build && git diff --check
```

- [ ] **Step 5: Run the project formatter on changed PHP files and repeat verification.**

```bash
./roo pint app/Enums/PublicationStatus.php app/Services/TeachingUnitPublicViewResolver.php app/Http/Controllers/PublicTeachingUnitController.php app/Http/Controllers/TeachingUnitDocumentController.php app/Documents/ParentLetterDocument.php app/Documents/Templates/ParentLetterTemplate.php app/Services/QrCodeRenderer.php && ./roo test --compact tests/Feature/TeachingUnitPublicPageTest.php tests/Feature/ParentLetterTest.php tests/Unit/TeachingUnitPublicViewResolverTest.php tests/Unit/ParentLetterRenderingTest.php && git diff --check
```

- [ ] **Step 6: Commit verification-only corrections as one final feature commit if required.**

```bash
git status --short && git diff --check
```

## Plan Self-Review

- Spec coverage: data model, three statuses, concrete slots, public Blade page, permanent signatures, separate downloads, live current data, cache invalidation, parent-letter modal, DOCX/ODT, QR condition, fonts, German labels, authorization, tests, and exclusions each map to Tasks 1–6.
- Placeholder scan: no `TBD`, `TODO`, or unbounded “handle edge cases” steps remain; all routes, classes, fields, status values, and verification commands are named.
- Type consistency: `PublicationStatus`, `TeachingUnitPublicViewResolver`, `ParentLetterDocument`, `ParentLetterTemplate`, `QrCodeRenderer`, and route names are used consistently across tasks.
- Scope: implementation is decomposed into independently testable data, resolver, public page, document, UI, and verification slices without adding public student data or unrelated refactoring.
