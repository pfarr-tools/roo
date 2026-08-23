# Task 7: Regression, Dokumentation und Abschlussprüfung

## Status

Erledigt.

## Änderungen

- Regressionen sichern mehrteilige und wiederholte PDF-Uploads mit PAGE-
  Markern, unvollständige START/END-Paare und die idempotente
  Materialisierung einer Scan-Session.
- Die Decoder-Regression prüft den vollständigen 300-dpi-Seitenrand-Crop von
  2 cm und die unveränderte y-Koordinate.
- Die Zuordnungsregression erlaubt nach dem Verwerfen eines Booklets ein neues
  offenes Booklet für dieselbe Schüler:in.
- Die Vue-Regression prüft die nicht-sequenzielle Navigation zwischen allen
  Bereichen und zwischen Aufgaben.
- Phase 10 beschreibt nun den vollständigen Scan-zu-Bewertung-Ablauf und
  grenzt OCR sowie automatische Handschriftenbewertung ausdrücklich aus.

## Datenschutzprüfung

Der finale Controller-zu-Inertia-zu-Vue-Fluss übergibt Fragment- und
Namensausschnitte nur als autorisierte Routen. Temporäre Scan-Manifeste
speichern Assessment-, Organisations-, Seiten-, Marker- und Fragmentdaten,
aber keine Schülerdaten. Im Scan- und Auswertungsfluss werden keine
Schülerdaten geloggt.

## Prüfung

- `./roo test --compact && ./roo npm run build && git diff --check` wurde
  vollständig angestoßen, stoppt jedoch am vorbestehenden
  `CurriculumImportTest`: Erwartet werden 17 Themen ohne Jahrgang, tatsächlich
  sind es 0. Der Test und sein Importpfad sind gegenüber `3c76898`
  unverändert und liegen außerhalb dieses Tasks.
- `./roo test --compact --filter=AssessmentEvaluationWorkflowTest` — 16
  Tests, 129 Assertions bestanden.
- `./roo npm run test:unit -- tests/frontend/assessmentEvaluation.test.js` —
  15 Tests bestanden.
- `./roo pint --test tests/Feature/AssessmentEvaluationWorkflowTest.php` —
  bestanden.
- `./roo npm run build && git diff --check` — bestanden; der Build meldet nur
  die bekannten Sass-Deprecation- und Chunk-Size-Warnungen.
