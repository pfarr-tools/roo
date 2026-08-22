# Assessment-PDF-Auswertung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upload scanned assessment PDFs, decode ROO DataMatrix markers, group pages into anonymous booklets, and display marker positions in `Assessment/Assess`.

**Architecture:** A pure parser/grouping layer handles ROO payloads and booklet state. An `AssessmentPdfScanner` renders each PDF page with `pdftoppm`, invokes `dmtxread -R`, converts marker corner coordinates to centimetres, and returns a non-persistent result DTO array. `AssessmentController` authorizes the group/assessment, accepts the PDF, and renders the result page; Vue provides the upload modal and table.

**Tech Stack:** Laravel 13, PHP 8.4, Symfony Process, Poppler `pdftoppm`, Debian `dmtx-utils`/`dmtxread`, Inertia.js 3, Vue 3, Bootstrap 5.3, Pest.

**Spec:** `docs/superpowers/specs/2026-08-22-assessment-pdf-auswertung-design.md`

## Global Constraints

- Scanned pages are rendered at 300 DPI before decoding.
- Uploads are PDF-only and limited to 50 MB.
- Booklets are anonymous; no student identifier is parsed or persisted.
- Temporary PDF and page images are removed in `finally` blocks.
- Assessment and teaching-group authorization must be enforced server-side.
- Tests must use the isolated test database and must not reset the development database.
- Visible UI text is German and comes from the central frontend localization.
- Existing unrelated worktree changes must remain untouched.

---

### Task 1: ROO marker parser and booklet grouping

**Files:**
- Create: `src/app/Services/AssessmentScan/RooMarker.php`
- Create: `src/app/Services/AssessmentScan/RooMarkerParser.php`
- Create: `src/app/Services/AssessmentScan/AssessmentScanResult.php`
- Create: `src/app/Services/AssessmentScan/AssessmentScanGrouper.php`
- Test: `src/tests/Unit/AssessmentScanTest.php`

**Interfaces:**
- `RooMarkerParser::parse(string $payload, int $page, float $yCm): ?RooMarker`
- `AssessmentScanGrouper::group(iterable $markers): AssessmentScanResult`
- `AssessmentScanResult::toArray(): array`

- [ ] **Step 1: Write the failing parser and grouping tests**

```php
it('parses page and task markers and ignores non-roo payloads', function () {
    $parser = new RooMarkerParser;

    expect($parser->parse('ROO1|A=42|L=M|K=PAGE', 1, 2.5)->toArray())
        ->toMatchArray(['page' => 1, 'y_cm' => 2.5, 'kind' => 'PAGE', 'assessment_id' => '42', 'task_id' => null, 'level' => 'M'])
        ->and($parser->parse('ROO1|T=17|K=START', 2, 8.25)->toArray())
        ->toMatchArray(['page' => 2, 'y_cm' => 8.25, 'kind' => 'START', 'task_id' => '17'])
        ->and($parser->parse('not-a-roo-code', 1, 1.0))->toBeNull();
});

it('starts anonymous booklets at page markers and warns about markers before a booklet', function () {
    $parser = new RooMarkerParser;
    $grouper = new AssessmentScanGrouper;

    $markers = collect([
        $parser->parse('ROO1|T=9|K=START', 1, 10),
        $parser->parse('ROO1|A=42|L=M|K=PAGE', 1, 2),
        $parser->parse('ROO1|T=9|K=END', 1, 20),
        $parser->parse('ROO1|A=42|L=M|K=PAGE', 3, 2),
    ]);

    expect($grouper->group($markers)->toArray())
        ->toMatchArray([
            'booklets.0.number' => 1,
            'booklets.0.start_page' => 1,
            'booklets.0.markers.0.kind' => 'PAGE',
            'booklets.0.markers.1.task_id' => '9',
            'booklets.1.number' => 2,
            'booklets.1.start_page' => 3,
        ])
        ->and($grouper->group($markers)->warnings)->toContain('Marker vor dem ersten Booklet auf Seite 1.');
});
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `./roo test --compact tests/Unit/AssessmentScanTest.php`

Expected: FAIL because the scan classes do not exist.

- [ ] **Step 3: Implement the smallest immutable marker/result objects and grouping state machine**

`RooMarkerParser` must accept only `ROO1` payloads, split `|` key/value fields, require `K=PAGE|START|END`, and require `A` for `PAGE` or `T` for task markers. `AssessmentScanGrouper` must preserve page/y order, start a new booklet at each `PAGE`, attach later markers to the current booklet, and append German warnings for pre-booklet and unpaired task markers.

- [ ] **Step 4: Run the focused test to verify it passes**

Run: `./roo test --compact tests/Unit/AssessmentScanTest.php`

Expected: all parser/grouping tests pass.

- [ ] **Step 5: Commit the pure scan-domain slice**

```bash
git add src/app/Services/AssessmentScan src/tests/Unit/AssessmentScanTest.php
git commit -m "feat(assessment): parse ROO-Scanmarker"
```

### Task 2: Scanned PDF decoder adapter

**Files:**
- Modify: `Dockerfile`
- Create: `src/app/Services/AssessmentScan/DataMatrixDecoder.php`
- Create: `src/app/Services/AssessmentScan/DmtxReadDecoder.php`
- Create: `src/app/Services/AssessmentScan/AssessmentPdfScanner.php`
- Modify: `src/app/Services/AssessmentScan/AssessmentScanResult.php`
- Test: `src/tests/Unit/AssessmentPdfScannerTest.php`

**Interfaces:**
- `DataMatrixDecoder::decode(string $imagePath): iterable<array{payload:string, y_px:float}>`
- `PdfPageRenderer::render(string $pdfPath): iterable<array{page:int, image_path:string, width_px:int, height_px:int}>`
- `AssessmentPdfScanner::scan(string $pdfPath): AssessmentScanResult`

- [ ] **Step 1: Write the failing scanner tests with a fake decoder and renderer process boundary**

```php
final class FakeDataMatrixDecoder implements DataMatrixDecoder
{
    public function __construct(private array $markersByPage) {}

    public function decode(string $imagePath): iterable
    {
        yield from $this->markersByPage[(int) pathinfo($imagePath, PATHINFO_FILENAME)] ?? [];
    }
}

final class FakePdfPageRenderer implements PdfPageRenderer
{
    public function __construct(private int $pageCount, private int $pageHeightPx) {}

    public function render(string $pdfPath): iterable
    {
        for ($page = 1; $page <= $this->pageCount; $page++) {
            yield ['page' => $page, 'image_path' => (string) $page, 'width_px' => 2481, 'height_px' => $this->pageHeightPx];
        }
    }
}

it('converts decoded marker coordinates from 300 dpi pixels to centimetres', function () {
    $decoder = new FakeDataMatrixDecoder([
        1 => [
            ['payload' => 'ROO1|A=42|L=M|K=PAGE', 'y_px' => 295.275],
            ['payload' => 'ROO1|T=7|K=START', 'y_px' => 1181.1],
        ],
    ]);
    $scanner = new AssessmentPdfScanner($decoder, new FakePdfPageRenderer(pageCount: 1, pageHeightPx: 3507));

    expect($scanner->scan('/tmp/input.pdf')->toArray()['booklets.0.markers'])
        ->sequence(fn ($marker) => $marker->y_cm->toBeIn([2.5, 10.0]));
});
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run: `./roo test --compact tests/Unit/AssessmentPdfScannerTest.php`

Expected: FAIL because the decoder/scanner contracts do not exist.

- [ ] **Step 3: Add the Docker decoder package and implement the adapter**

Add Debian package `dmtx-utils` to the runtime image. `DmtxReadDecoder` invokes `dmtxread -R -q 10 -n <png>`, parses each line's corner coordinates and payload, and returns the marker's top-most y coordinate. `AssessmentPdfScanner` renders pages with `pdftoppm -r 300 -png`, obtains page dimensions, feeds each image to the decoder, converts `y_px / 300 * 2.54` to `y_cm`, parses markers, groups them, and removes all temporary files in `finally`.

- [ ] **Step 4: Run scanner tests and the image-tool availability check**

Run: `./roo test --compact tests/Unit/AssessmentPdfScannerTest.php && ./roo exec sh -lc 'command -v pdftoppm && command -v dmtxread'`

Expected: focused scanner tests pass and both commands resolve in the app container after rebuild.

- [ ] **Step 5: Commit the decoder slice**

```bash
git add Dockerfile src/app/Services/AssessmentScan src/tests/Unit/AssessmentPdfScannerTest.php
git commit -m "feat(assessment): dekodiere gescannte ROO-Marker"
```

### Task 3: Authorized upload endpoint and result page

**Files:**
- Modify: `src/app/Http/Controllers/AssessmentController.php`
- Modify: `src/routes/web.php`
- Create: `src/resources/js/Pages/Assessment/Assess.vue`
- Test: `src/tests/Feature/AssessmentAssessmentTest.php`

**Interfaces:**
- `POST /unterrichtsgruppen/{teachingGroup}/lernstandserhebungen/{assessment}/auswerten`
- `AssessmentController::assess(Request $request, TeachingGroup $teachingGroup, Assessment $assessment)`

- [ ] **Step 1: Write failing feature tests**

```php
it('uploads a pdf and renders the scan result page', function () {
    $fixture = assessmentFixture();
    $this->mock(AssessmentPdfScanner::class)->shouldReceive('scan')->once()->andReturn(new AssessmentScanResult([
        ['number' => 1, 'start_page' => 1, 'markers' => []],
    ], []));

    $this->actingAs($fixture['user'])->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$fixture['assessment']->id}/auswerten", [
        'pdf' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
    ])->assertSuccessful()->assertInertia(fn ($page) => $page->component('Assessment/Assess')->where('assessment.id', $fixture['assessment']->id)->has('scan.booklets', 1));
});

it('rejects non-pdf uploads and assessments from another group', function () {
    $fixture = assessmentFixture();
    $this->actingAs($fixture['user'])->post($fixture['scanUrl'], [
        'pdf' => UploadedFile::fake()->createWithContent('scan.txt', 'not a pdf'),
    ])->assertUnprocessable();

    $other = assessmentFixture();
    $this->actingAs($fixture['user'])->post("/unterrichtsgruppen/{$fixture['group']->id}/lernstandserhebungen/{$other['assessment']->id}/auswerten", [
        'pdf' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
    ])->assertNotFound();
});
```

- [ ] **Step 2: Run the feature test to verify it fails**

Run: `./roo test --compact tests/Feature/AssessmentAssessmentTest.php`

Expected: FAIL because the route/controller/page do not exist.

- [ ] **Step 3: Implement authorization, validation, scanner invocation, and Inertia page**

Authorize `update` on the group and abort 404 when the assessment group differs. Validate `pdf` as required, file, mimes `pdf`, max `51200`. Invoke the scanner and render `Assessment/Assess` with group, assessment, scan, and return context. Add the named POST route inside the authenticated route group.

- [ ] **Step 4: Implement the result table**

Render German headings for Booklet, Startseite, Marker, Aufgabe, Seite, y-Position, and warnings. Keep the first toolbar action as the light close button returning to the assessment editor.

- [ ] **Step 5: Run the feature test to verify it passes**

Run: `./roo test --compact tests/Feature/AssessmentAssessmentTest.php`

Expected: upload, validation, authorization, and Inertia assertions pass.

- [ ] **Step 6: Commit the endpoint/page slice**

```bash
git add src/app/Http/Controllers/AssessmentController.php src/routes/web.php src/resources/js/Pages/Assessment/Assess.vue src/tests/Feature/AssessmentAssessmentTest.php
git commit -m "feat(assessment): zeige PDF-Scanergebnisse"
```

### Task 4: Editor upload modal and final verification

**Files:**
- Modify: `src/resources/js/Pages/Assessments/Form.vue`
- Modify: `src/resources/js/i18n/de.js`
- Test: `src/tests/Feature/AssessmentAssessmentTest.php`

- [ ] **Step 1: Write the failing editor contract assertion**

Assert the existing assessment editor props render the `Auswerten` label and the upload form target, while a new assessment does not offer the action.

- [ ] **Step 2: Implement the modal with Inertia FormData**

Add an `Auswerten` toolbar button for existing assessments. The modal contains a PDF-only file input, explanatory German text, cancel, and submit. Use `useForm({ pdf: null })`, `forceFormData: true`, and post to the named assessment scan route. Preserve the existing close/save/download ordering and disable controls during processing.

- [ ] **Step 3: Add all German localization keys and finish the frontend test/build checks**

Run: `./roo npm run build && git diff --check`

Expected: Vite build succeeds and no whitespace errors are reported.

- [ ] **Step 4: Run the complete relevant verification set**

Run: `./roo test --compact tests/Unit/AssessmentScanTest.php tests/Unit/AssessmentPdfScannerTest.php tests/Unit/DocumentRenderingTest.php tests/Feature/AssessmentAssessmentTest.php tests/Feature/AssessmentTaskEditorTest.php && ./roo npm run build && git diff --check`

- [ ] **Step 5: Commit the UI slice**

```bash
git add src/resources/js/Pages/Assessments/Form.vue src/resources/js/i18n/de.js src/tests/Feature/AssessmentAssessmentTest.php
git commit -m "feat(assessment): ergänze PDF-Auswertung im Editor"
```
