# Roo-Schema: konfessionell-kooperative Curricula

## Version 1.1

Das Schema bleibt kompatibel mit dem zuvor verwendeten Root-Typ
`confessional_cooperative_curriculum`, präzisiert aber die Kompetenzabbildung.

## Root

- `schema_version`
- `type`
- `metadata`
- `education_plan_bindings`
- `units`

## Grundprinzip

Ein Curriculum ist **kein Bildungsplan**. Es referenziert Kompetenzen aus mehreren
konfessionellen Bildungsplänen und verbindet sie in Unterrichtseinheiten.

Konfessionen sind nicht als feste Enum im Schema definiert. Die hier vorliegenden
Dateien verwenden `evangelical` und `catholic`.

## `units[]`

```json
{
  "id": "ue-01",
  "number": 1,
  "title": "...",
  "hours": 6,
  "preparation_questions": [],
  "shared_plan": [],
  "denominational_profiles": {},
  "process_competencies": [],
  "raw_rows": []
}
```

### Nummern und Textnormalisierung

Word zerlegt Wörter und Nummern häufig über mehrere OOXML-Runs. Beim Import werden
Run-Texte daher **ohne künstlich eingefügte Leerzeichen** zusammengesetzt und erst
anschließend normale Mehrfach-Leerzeichen vereinheitlicht. Dadurch entstehen keine
Fehler wie eine auseinandergerissene `15` oder `3.1.5`.

`raw` bedeutet in diesem Schema deshalb „inhaltlich quellnah“, nicht
„byte-identische OOXML-Textdarstellung“.

## Inhaltsbezogene Kompetenzen

Sie liegen innerhalb des jeweiligen konfessionellen Profils:

```json
"denominational_profiles": {
  "evangelical": {
    "content_competencies": [
      {
        "id": "3.1.4.3",
        "number": 3,
        "display": "3.1.4 (3)",
        "denomination": "evangelical",
        "text": "...",
        "levels_mentioned": ["G", "M", "E"],
        "raw": "...",
        "references": [
          {"id": "3.1.4.3", "display": "3.1.4 (3)"}
        ]
      }
    ],
    "perspective": []
  }
}
```

**Jede im Quell-DOCX vorkommende Kompetenzposition wird als eigener Eintrag
importiert.** Mehrere Kompetenzen in derselben Tabellenzelle werden nicht zu einem
einzigen `raw`-Block zusammengezogen.

`levels_mentioned` ist rein deskriptiv. G/M/E werden nicht als Konfessionen oder
eigene Curriculum-Kompetenzen interpretiert.

## Prozessbezogene Kompetenzen

Prozesskompetenzen stehen auf Unit-Ebene, weil eine UE Kompetenzen mehrerer
Konfessionen kombiniert. **Jeder Eintrag trägt zwingend eine `denomination`:**

```json
{
  "id": "2.2.4",
  "display": "2.2.4",
  "denomination": "catholic",
  "text": "...",
  "raw": "...",
  "source_formatting": {
    "fill": "FFFF99",
    "fill_source": "paragraph",
    "attribution": "background_color"
  }
}
```

### Konfessionszuordnung über Hintergrundfarbe

Die offiziellen Beispielcurricula markieren:

- lila/purple -> `evangelical`
- gelb/yellow -> `catholic`

Die Word-Dateien enthalten mehrere technisch unterschiedliche Farbtöne derselben
visuellen Farbfamilien. Der Importer klassifiziert sie nach RGB-Nähe zu den
Referenzfarben `CCC0D9` (lila) und `FFFF99` (gelb).

Reihenfolge der Auswertung:

1. Absatz-Hintergrund (`w:pPr/w:shd`)
2. Run-Hintergrund
3. Zell-Hintergrund
4. nur wenn die Formatierung in einer konkreten Quelldatei fehlt:
   **exakte Textübereinstimmung** derselben Prozesskompetenz mit farbig markierten
   Vorkommen in den anderen offiziellen Curricula.

Der vierte Fall wird mit
`source_formatting.attribution = "exact_text_match"` dokumentiert und zusätzlich
im `*.validation.json` und `QA_REPORT.json` ausgewiesen.

Ein produktiv vollständiger Datensatz darf **keine** Prozesskompetenz ohne
`denomination` enthalten.

## `shared_plan[]`

Offene Liste gemeinsamer Unterrichtsinhalte, derzeit vor allem:

```json
{"type": "central_content", "text": "..."}
```

`type` ist absichtlich nicht fest auf eine Enum begrenzt.

## `education_plan_bindings`

```json
{
  "role": "denominational_basis",
  "denomination": "evangelical",
  "subject": "Evangelische Religionslehre",
  "plan_code": null
}
```

Die `plan_code`-Auflösung kann später gegen die in Roo importierten
EducationPlans erfolgen.

## `raw_rows`

Die normalisierten Zelltexte aller Tabellenzeilen einer UE bleiben erhalten.
Dadurch kann Roo später verbesserte Normalisierung durchführen, ohne erneut auf
die DOCX-Quelle zugreifen zu müssen.

## Vollständigkeitsprüfung

Zu jedem Curriculum gehört eine `*.validation.json`.

`metadata.conversion.complete = true` wird nur gesetzt, wenn:

- alle erkannten inhaltsbezogenen Kompetenzvorkommen im Output vorkommen,
- alle erkannten prozessbezogenen Kompetenzvorkommen im Output vorkommen,
- jede Prozesskompetenz eine Konfession besitzt,
- keine fehlerhaften Nummernabstände in normalisierten IDs/Nummern erkannt wurden.

`QA_REPORT.json` fasst die Prüfungen des Gesamtpakets zusammen.
