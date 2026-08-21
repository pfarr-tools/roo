# KoKo-Curricula für Roo

Neuimport der 16 offiziellen Beispielcurricula für konfessionell-kooperativen
Religionsunterricht.

Gesamtumfang:

- 16 Curricula
- 160 Unterrichtseinheiten
- 1295 Vorkommen inhaltsbezogener Kompetenzen
- 1465 Vorkommen prozessbezogener Kompetenzen

Alle 16 Dateien bestehen die Vollständigkeitsprüfung.

Besondere Qualitätsregeln dieses Imports:

1. OOXML-Runs werden ohne künstliche Zwischenräume zusammengesetzt.
2. Jede inhaltsbezogene Kompetenz wird einzeln importiert.
3. Jede Prozesskompetenz erhält eine `denomination`.
4. Lila Hintergrund = evangelisch; gelber Hintergrund = katholisch.
5. Fehlende Formatierung in einzelnen Absätzen wird nur bei exakter,
   anderweitig farbig belegter Textübereinstimmung aufgelöst.
6. `QA_REPORT.json` und die einzelnen `*.validation.json` dokumentieren die
   Vollständigkeitsprüfung.

Siehe `SCHEMA.md` für das Datenmodell.

## Perspectives-Ergänzung (Schema 1.2)

Jede UE enthält nun zusätzlich:

```json
"perspectives": {
  "evangelical": "...",
  "catholic": "...",
  "common": "..."
}
```

Das restliche Datenmodell wurde nicht verändert. Siehe `SCHEMA.md` und
`QA_PERSPECTIVES.json`.
