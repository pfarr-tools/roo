# Assessment Browser-Scanpipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]).

**Goal:** Process scanned assessment PDFs page by page in the browser, show live progress, crop task fragments, upload them sequentially to a temporary authorized session, and retain the existing server scanner as fallback.

**Architecture:** A Vue scan client opens the local PDF with pdfjs-dist, sends one rendered page at a time to a Web Worker, and receives normalized ROO markers and fragment jobs. A browser decoder adapter is piloted with @zxing/browser; if real scans cannot provide all marker coordinates reliably, the browser pipeline falls back to the existing server decoder while retaining the same progress and fragment-upload contracts. Temporary fragments are stored by an authorized Laravel session service and expire without creating permanent assessment or booklet records.

**Tech Stack:** Vue 3 Composition API, Inertia.js, pdfjs-dist, @zxing/browser, Web Workers, Vitest, Laravel Form Requests, Laravel Filesystem temporary disk, Pest.

**Spec:** docs/superpowers/specs/2026-08-22-assessment-pdf-auswertung-design.md

## Global Constraints

- Keep the original PDF in the browser for the preferred pipeline; upload only temporary cropped fragments.
- Keep AssessmentPdfScanner and the existing PDF upload route as a server-side fallback.
- Do not add permanent scan, booklet, student, result, or grading tables in this slice.
- Authorize every session and fragment request through the teaching-group assessment scope.
- Do not log PDF contents, fragment contents, student data, or local file paths.
- Process at most one rendered page and one fragment upload at a time.
- All user-visible text must come from resources/js/i18n/de.js.
- Use an isolated frontend test command and never use the development PostgreSQL database for tests.
- Use apply_patch for edits and commit each independently testable task.

---

### Task 1: Frontend test harness and browser scan domain primitives

**Files:**
- Modify: src/package.json
- Modify: src/package-lock.json
- Create: src/resources/js/Features/AssessmentScan/markerParser.js
- Create: src/resources/js/Features/AssessmentScan/bookletGrouper.js
- Create: src/resources/js/Features/AssessmentScan/fragmentBounds.js
- Create: src/tests/frontend/assessmentScan.test.js

**Interfaces:**
- parseRooMarker(payload, page, yCm) returns a normalized marker object or null.
- groupRooMarkers(markers) returns { booklets, warnings } with the same field names as the PHP AssessmentScanResult.
- fragmentBounds(startMarker, endMarker, pageWidth, pageHeight) returns a bounded crop rectangle or null.
- npm run test:unit -- --run executes Vitest without a browser or database.

- [ ] **Step 1: Write the failing tests**

Test valid PAGE/START/END payloads, ignored payloads, a marker-before-booklet warning, booklet boundaries, same-page fragment bounds, and cross-page fragments returning null.

~~~js
import { expect, it } from 'vitest'
import { parseRooMarker } from '../../resources/js/Features/AssessmentScan/markerParser'
import { groupRooMarkers } from '../../resources/js/Features/AssessmentScan/bookletGrouper'
import { fragmentBounds } from '../../resources/js/Features/AssessmentScan/fragmentBounds'

it('normalizes the Roo marker contract', () => {
    expect(parseRooMarker('ROO1|T=7|K=START', 2, 4.5)).toMatchObject({
        page: 2, y_cm: 4.5, kind: 'START', task_id: '7',
    })
})

it('groups pages and task markers into anonymous booklets', () => {
    const result = groupRooMarkers([
        parseRooMarker('ROO1|A=42|L=M|K=PAGE', 1, 2),
        parseRooMarker('ROO1|T=7|K=START', 1, 4),
        parseRooMarker('ROO1|T=7|K=END', 1, 20),
        parseRooMarker('ROO1|A=42|L=M|K=PAGE', 3, 2),
    ])
    expect(result.booklets).toHaveLength(2)
    expect(result.booklets[0].markers[1].task_id).toBe('7')
})

it('returns a crop rectangle only for an ordered same-page pair', () => {
    expect(fragmentBounds(
        { page: 1, y_px: 200 },
        { page: 1, y_px: 1000 },
        1600,
        2200,
    )).toEqual({ x: 0, y: 200, width: 1600, height: 800 })
})
~~~

- [ ] **Step 2: Run the tests and confirm the intended failure**

Run: cd src && npm run test:unit -- --run

Expected: FAIL because the test script and three modules do not exist.

- [ ] **Step 3: Add the minimal Vitest setup and implementations**

Add vitest as a development dependency and the test:unit script. Implement the parser with the same required keys and validation as RooMarkerParser; implement grouping with the existing warning semantics; reject cross-page pairs for this first fragment slice rather than inventing crop geometry.

- [ ] **Step 4: Run the tests and confirm they pass**

Run: cd src && npm run test:unit -- --run

Expected: all frontend scan primitive tests pass.

- [ ] **Step 5: Commit**

~~~bash
git add src/package.json src/package-lock.json src/resources/js/Features/AssessmentScan src/tests/frontend/assessmentScan.test.js
git commit -m "feat(assessment): ergänze Browser-Scanprimitiven"
~~~

---

### Task 2: PDF page worker and DataMatrix decoder pilot

**Files:**
- Modify: src/package.json
- Modify: src/package-lock.json
- Create: src/resources/js/Features/AssessmentScan/scanWorker.js
- Create: src/resources/js/Features/AssessmentScan/browserDecoder.js
- Create: src/tests/frontend/scanWorker.test.js
- Modify: src/vite.config.js only if worker bundling requires an explicit worker setting.

**Interfaces:**
- createScanWorker(pdfFile, callbacks) owns the worker lifecycle and emits phase, page, marker, fragment, warning, done, and error events.
- Worker messages use { type: 'scan-page', pageNumber, imageBitmap, width, height } and return { type: 'page-result', pageNumber, markers, fragments, decoder: 'zxing' }.
- decodeDataMatrix(imageData) returns [{ payload, xPx, yPx, widthPx, heightPx }]; decoder failure returns an empty result and a warning, not a worker crash.

- [ ] **Step 1: Add failing worker and decoder contract tests**

Mock the decoder boundary and assert that one page is rendered, one result message is produced, marker coordinates are converted to centimetres, and the worker reports page progress. Add a test that an unavailable browser decoder reports a fallback warning.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: cd src && npm run test:unit -- --run src/tests/frontend/scanWorker.test.js

Expected: FAIL because the worker and decoder modules do not exist.

- [ ] **Step 3: Add browser dependencies and minimal implementation**

Install pdfjs-dist and @zxing/browser. Configure PDF.js worker loading through the Vite module URL. Render only the current page at the configured scan resolution, transfer the resulting ImageBitmap or ImageData to the worker, and release canvases after each page.

Use a dedicated DataMatrix reader and record decoder capability in the result. Do not silently claim complete multi-marker recognition: if the pilot decoder returns fewer markers than the page needs, emit a German warning and leave the server fallback available.

- [ ] **Step 4: Run the tests and a production build**

Run: cd src && npm run test:unit -- --run && npm run build

Expected: worker tests pass and Vite emits the worker bundle.

- [ ] **Step 5: Commit**

~~~bash
git add src/package.json src/package-lock.json src/vite.config.js src/resources/js/Features/AssessmentScan src/tests/frontend/scanWorker.test.js
git commit -m "feat(assessment): prüfe Browser-DataMatrix-Scan"
~~~

---

### Task 3: Temporary scan sessions and sequential fragment uploads

**Files:**
- Create: src/app/Services/AssessmentScan/AssessmentScanSessionStore.php
- Create: src/app/Http/Requests/AssessmentScanSessionRequest.php
- Create: src/app/Http/Requests/AssessmentScanFragmentRequest.php
- Modify: src/app/Http/Controllers/AssessmentController.php
- Modify: src/routes/web.php
- Create: src/tests/Feature/AssessmentScanSessionTest.php

**Interfaces:**
- AssessmentScanSessionStore::create(Assessment $assessment) returns session_id and expires_at.
- AssessmentScanSessionStore::storeFragment(string $sessionId, array $metadata, UploadedFile $fragment) returns fragment_id and checksum.
- AssessmentScanSessionStore::delete(string $sessionId) removes temporary files.
- POST /unterrichtsgruppen/{group}/lernstandserhebungen/{assessment}/auswertung/session creates a session.
- POST .../auswertung/session/{session}/fragments stores one PNG fragment.
- DELETE .../auswertung/session/{session} removes temporary files.
- All endpoints reject foreign assessments and sessions belonging to another user/group.

- [ ] **Step 1: Add failing feature tests**

Cover session creation, one valid PNG fragment upload with metadata, checksum response, foreign group rejection, invalid metadata/file rejection, deletion, and expiry metadata. Use RefreshDatabase with the existing isolated test configuration and fake the temporary disk.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: ./roo test --compact tests/Feature/AssessmentScanSessionTest.php

Expected: FAIL because routes, requests, and store do not exist.

- [ ] **Step 3: Implement the session store and endpoints**

Use a random ULID session identifier and store fragments below a non-public temporary disk path scoped by assessment and session. Validate page, booklet number, task ID, y positions, MIME type, and a bounded PNG size. Return only opaque IDs and checksums. Re-check the assessment/group relationship for every request.

- [ ] **Step 4: Run focused backend tests**

Run: ./roo test --compact tests/Feature/AssessmentScanSessionTest.php tests/Feature/AssessmentAssessmentTest.php

Expected: all session and existing assessment tests pass.

- [ ] **Step 5: Commit**

~~~bash
git add src/app/Services/AssessmentScan/AssessmentScanSessionStore.php src/app/Http/Requests/AssessmentScanSessionRequest.php src/app/Http/Requests/AssessmentScanFragmentRequest.php src/app/Http/Controllers/AssessmentController.php src/routes/web.php src/tests/Feature/AssessmentScanSessionTest.php
git commit -m "feat(assessment): speichere temporaere Scanfragmente"
~~~

---

### Task 4: Vue scan client, progress state, and sequential upload queue

**Files:**
- Create: src/resources/js/Features/AssessmentScan/scanClient.js
- Create: src/resources/js/Features/AssessmentScan/scanQueue.js
- Create: src/tests/frontend/scanClient.test.js
- Modify: src/resources/js/Pages/Assessments/Form.vue
- Modify: src/resources/js/i18n/de.js

**Interfaces:**
- createScanClient({ pdf, sessionUrl, fragmentUrl, onProgress, onFragmentError }) returns start(), cancel(), and retry(fragmentId).
- Progress state is { phase, totalPages, currentPage, detectedBooklets, detectedMarkers, queuedFragments, uploadedFragments, failedFragments, status }.
- scanQueue uploads one Blob at a time and resolves each item only after the server acknowledges it.
- A failed fragment remains retryable and does not re-upload already acknowledged fragments.

- [ ] **Step 1: Add failing client and queue tests**

Test sequential ordering, progress counters, retrying only a failed fragment, cancellation cleanup, and the empty/no-marker case.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: cd src && npm run test:unit -- --run src/tests/frontend/scanClient.test.js

Expected: FAIL because the client and queue do not exist.

- [ ] **Step 3: Implement the queue and client**

Create the session before starting page processing. Handle one page at a time, create same-page PNG fragments from the worker result, post each fragment with fetch/FormData, and call onProgress after every phase transition and server acknowledgement. Abort the current request and delete the session on cancellation. Do not close the modal while processing.

- [ ] **Step 4: Connect the existing modal**

Replace the current direct PDF POST with a Browseranalyse starten action. Keep a clearly labeled Server-Fallback verwenden action that invokes the current Inertia PDF upload. Show phase text, determinate page progress, fragment counters, warning list, retry buttons, and completion/failure states using localized German strings.

- [ ] **Step 5: Run frontend tests and build**

Run: cd src && npm run test:unit -- --run && npm run build

Expected: all client tests pass and the application builds.

- [ ] **Step 6: Commit**

~~~bash
git add src/resources/js/Features/AssessmentScan src/resources/js/Pages/Assessments/Form.vue src/resources/js/i18n/de.js src/tests/frontend
git commit -m "feat(assessment): zeige Browser-Scanfortschritt"
~~~

---

### Task 5: Result handoff, fallback behavior, and real-scan pilot

**Files:**
- Modify: src/resources/js/Pages/Assessment/Assess.vue
- Modify: src/resources/js/Pages/Assessments/Form.vue
- Modify: src/resources/js/i18n/de.js
- Create: src/tests/Feature/AssessmentScanFallbackTest.php
- Create: docs/superpowers/notes/assessment-browser-scan-pilot.md

**Interfaces:**
- Completed browser sessions navigate to Assessment/Assess with the recognized marker/booklet summary and temporary fragment acknowledgements.
- Browser decoder warnings visibly identify pages that require server fallback.
- The existing server route remains callable from the modal and continues to render the current result page.

- [ ] **Step 1: Add failing fallback and result tests**

Assert that a browser decoder warning exposes a fallback action, a completed session returns the assessment result page, and the original server route remains unchanged.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: ./roo test --compact tests/Feature/AssessmentScanFallbackTest.php

Expected: FAIL because the handoff and fallback summary are not implemented.

- [ ] **Step 3: Implement result handoff and localized fallback states**

Display browser marker results and fragment acknowledgement counts on Assessment/Assess. Preserve anonymous booklet numbering. Ensure all errors are safe German messages without command output or local paths.

- [ ] **Step 4: Run the real-scan pilot**

Use a real Roo-generated assessment PDF in the Docker/browser environment. Record page count, detected markers, missing markers, fragment dimensions, upload count, and elapsed time in the pilot note. If multi-marker browser detection is incomplete, mark the decoder as fallback-only and keep server analysis as the default for that case.

- [ ] **Step 5: Run final verification**

Run:

~~~bash
./roo test --compact tests/Unit/AssessmentScanTest.php tests/Unit/AssessmentPdfScannerTest.php tests/Feature/AssessmentAssessmentTest.php tests/Feature/AssessmentScanSessionTest.php
cd src && npm run test:unit -- --run && npm run build
git diff --check
~~~

Expected: all focused tests pass; any unrelated full-suite failures are reported separately.

- [ ] **Step 6: Commit**

~~~bash
git add src/resources/js/Pages/Assessment/Assess.vue src/resources/js/Pages/Assessments/Form.vue src/resources/js/i18n/de.js src/tests/Feature/AssessmentScanFallbackTest.php docs/superpowers/notes/assessment-browser-scan-pilot.md
git commit -m "feat(assessment): integriere Browser-Scanpilot"
~~~

## Review Checkpoints

- After Task 1, approve the shared marker and fragment contracts before decoder work.
- After Task 2, decide from the real decoder pilot whether browser decoding is primary or fallback-only.
- After Task 3, verify authorization and temporary-file cleanup before connecting the UI.
- After Task 4, verify progress and cancellation behavior in the browser.
- After Task 5, review the pilot note before considering the browser path production-ready.

