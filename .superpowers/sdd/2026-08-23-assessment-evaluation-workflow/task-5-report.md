# Task 5: Scanbereich und manuelle Booklet-Zuordnung

## Status

Erledigt.

## Umsetzung

- Die Auswertungsseite bietet drei frei wählbare Bereiche für Scans,
  Booklet-Zuordnung und die vorbereitete Aufgabenbewertung.
- Der Scanbereich zeigt Fortschrittszahlen sowie alle dauerhaft gespeicherten
  Booklets. Weitere PDFs werden über das wiederverwendbare Upload-Modal
  hochgeladen.
- Das Upload-Modal zeigt aktuelle Seite, Seiten-, Marker- und
  Fragmentfortschritt, den Verarbeitungsstatus, die Vorschau sowie einen
  erneuten Versuch nach Fehlern. Die bisherige Import-Schnittstelle bleibt
  über eine Kompatibilitätskomponente erhalten.
- Offene Booklets zeigen den geschützten Namensausschnitt der ersten Seite und
  ausschließlich Schüler:innen der aktuellen Unterrichtsgruppe. Bereits einem
  anderen offenen Booklet zugeordnete Schüler:innen sind nicht auswählbar;
  bestehende Zuordnungen können aufgehoben oder geändert werden.
- Booklets können verworfen und wiederhergestellt werden. Alle Änderungen
  verwenden die autorisierten Inertia-Endpunkte.
- Deutsche Texte liegen zentral in `resources/js/i18n/de.js`.

## Tests

- `./roo npm run test:unit` — 4 Dateien, 14 Tests bestanden
- `./roo npm run build` — erfolgreich
- `git diff --check` — erfolgreich

## Hinweise

Der Vite-Build gibt weiterhin die bestehenden Sass-Deprecation- sowie die
Chunk-Size-Warnungen aus; der Build selbst ist erfolgreich.
