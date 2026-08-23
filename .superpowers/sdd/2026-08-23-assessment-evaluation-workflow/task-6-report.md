# Task 6: Aufgabenbewertung und Ergebnis-Synchronisierung

## Status

Erledigt.

## Umsetzung

- `ExpectationOccurrences` expandiert jede Wiederholung einer Erwartung in eine
  separat bewertbare Zeile mit Erwartung, Ausprägungsnummer und Maximalpunkten.
- `SaveAssessmentTaskReview` verwendet diese Expansion weiterhin für die
  vollständige Servervalidierung und synchronisiert die gespeicherte Bewertung
  innerhalb derselben Transaktion in `StudentAssessmentResult`.
- Ergebniswerte sind nun signierte Dezimalwerte. Sie enthalten die Summe aller
  gespeicherten Ausprägungen und der Zusatzpunkte, auch bei Aufgaben ohne
  Erwartungen.
- Zuordnen, Aufheben einer Zuordnung, Verwerfen und Wiederherstellen eines
  Booklets entfernen oder berechnen die betroffenen Schülerergebnisse neu.
- `TaskEvaluation` zeigt private Antwortfragmente einer Aufgabe bei jedem
  Öffnen in neuer Reihenfolge, getrennte Bewertungszeilen für jede Ausprägung,
  volle bzw. manuelle Punkte, Erläuterungen sowie Zusatzpunkte mit je eigenem
  Speicher- und Fehlerzustand.
- Die Auswertungsseite zeigt pro Aufgabe offene, bewertete und gesamte Fälle.
- Der bisherige Scan-Test liest nach der Modal-Extraktion wieder die tatsächliche
  wiederverwendbare Komponente statt der Kompatibilitätsweiterleitung.

## Tests

- Zuerst fehlgeschlagen: Occurrence-Service nicht vorhanden, keine
  Ergebnis-Synchronisierung und keine Aufgabenkomponente.
- `./roo test --filter=Assessment` — 38 Tests, 307 Assertions bestanden.
- `./roo npm run test:unit` — 4 Dateien, 21 Tests bestanden.
- `./roo pint …` für alle geänderten PHP-Dateien — 9 Dateien formatiert und
  geprüft.
- `./roo npm run build` — erfolgreich.
- `git diff --check` — erfolgreich.

## Hinweise

Der Vite-Build meldet weiterhin die bestehenden Sass-Deprecation- und
Chunk-Size-Warnungen; der Build selbst ist erfolgreich.
