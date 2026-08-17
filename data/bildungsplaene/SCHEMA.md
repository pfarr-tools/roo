# Roo-Importformat für Bildungspläne

## 1. Zweck

Dieses Format dient dazu, Bildungspläne unterschiedlicher Schularten und Revisionen in Roo mit einem gemeinsamen Datenmodell abzubilden. Das Schema bildet **keine fachliche Gliederung fest ab**. Insbesondere sind Namen und Anzahl der inhaltsbezogenen Bereiche (`domains`) Daten des jeweiligen Bildungsplans und keine zulässigen Werte des Schemas.

Aktuelle Schemaversion: `2.0.0`.

## 2. Gestaltungsprinzipien

1. **Offizielle Gliederung erhalten.** Soweit vorhanden, werden die amtlichen Gliederungsnummern als IDs übernommen, z. B. `3.1.4.2`.
2. **Keine fest codierten Domains.** Ein Plan kann beliebig viele Bereiche mit beliebigen Namen enthalten. Eine künftige Revision darf sie umbenennen, hinzufügen, entfernen oder völlig anders ordnen.
3. **Differenzierung als Variante.** Formulierungen derselben Kompetenzposition auf G-, M- oder E-Niveau werden nicht zu drei unabhängigen Kompetenzen, sondern zu `variants` derselben Kompetenz.
4. **Stufen sind generisch.** Jahrgangsbänder, einzelne Klassen, Kursarten und Niveaus werden über `stages`, `grades`, `course` und `levels` beschrieben.
5. **Quelltreue vor Normalisierung.** `source_raw` und `references_raw` bewahren Informationen, die ein Importer später weiter normalisieren kann.
6. **Keine implizite Vollständigkeit.** Der Konvertierungsstatus kann in `metadata.conversion` dokumentiert werden. Ein Importer sollte `complete: false` sichtbar behandeln.

## 3. Top-Level-Struktur

```json
{
  "schema_version": "2.0.0",
  "type": "education_plan",
  "metadata": {},
  "guiding_principles": [],
  "process_competencies": [],
  "stages": [],
  "supplementary_content_raw": ""
}
```

### `schema_version`
Version dieses Austauschformats, nicht die Version des Bildungsplans.

### `type`
Derzeit konstant `education_plan`.

### `metadata`
Beschreibt die Quelle und den Plan. Übliche Felder:

| Feld | Typ | Bedeutung |
|---|---|---|
| `country` | string | ISO-Länderkürzel, hier `DE` |
| `state` | string | Bundesland, hier `BW` |
| `subject` | string | Fachbezeichnung |
| `school_types` | string[] | Schulart(en), frei benennbar |
| `plan_code` | string | stabiler Quell-/Planbezeichner |
| `version` | string/null | Version/Fassung des Plans |
| `version_date` | string/null | Datum der Fassung, ISO `YYYY-MM-DD` |
| `title` | string | offizielle bzw. eindeutige Bezeichnung |
| `source_url` | string | Primärquelle |
| `conversion` | object/null | optionaler Status der Konvertierung |

`metadata` darf bei anderen Quellen um zusätzliche Metadaten erweitert werden.

## 4. Leitgedanken

`guiding_principles` ist eine geordnete Liste frei benannter Abschnitte:

```json
{
  "id": "1.1",
  "title": "Bildungswert des Faches …",
  "text": "…"
}
```

Es wird nicht vorausgesetzt, dass jeder Bildungsplan solche Abschnitte besitzt oder dieselbe Gliederung verwendet.

## 5. Prozessbezogene Kompetenzen

```json
{
  "id": "2.1",
  "title": "Wahrnehmungs- und Darstellungsfähigkeit",
  "introduction": "…",
  "competencies": [
    {
      "id": "2.1.1",
      "number": 1,
      "text": "…"
    }
  ]
}
```

Auch Namen, Anzahl und Nummerierung dieser Bereiche sind nicht als Enum festgelegt.

## 6. Stufen (`stages`)

Eine Stufe bildet einen Abschnitt des Plans ab, z. B. Klassen 1/2, Klassen 7/8/9, Klasse 11 oder die Kursstufe als Basisfach.

```json
{
  "id": "3.4",
  "label": "Klassen 12/13 (Basisfach)",
  "grades": [12, 13],
  "course": {"id": "basic", "label": "Basisfach"},
  "levels": [],
  "domains": []
}
```

### `grades`
Liste der zugeordneten Klassen/Jahrgänge. Das Label bleibt maßgeblich, falls ein anderer Plan eine nicht rein numerische Stufung verwendet.

### `course`
`null` oder ein frei benanntes Objekt. Beispiel Basis-/Leistungsfach:

```json
{"id": "advanced", "label": "Leistungsfach"}
```

Es gibt keine festgelegte Liste zulässiger Kursarten.

### `levels`
Definiert Differenzierungsniveaus, die innerhalb dieser Stufe bei Kompetenzvarianten verwendet werden können:

```json
[
  {"id": "G", "label": "Grundlegendes Niveau"},
  {"id": "M", "label": "Mittleres Niveau"},
  {"id": "E", "label": "Erweitertes Niveau"}
]
```

Auch diese IDs sind nicht global festgelegt.

## 7. Inhaltsbereiche (`domains`)

**`domains` ist ausdrücklich generisch.** Das Schema kennt weder „Mensch“ noch „Bibel“ noch irgendeinen anderen fachlichen Bereich als vorgegebenen Wert.

```json
{
  "id": "3.1.4",
  "title": "Gott",
  "introduction": "…",
  "competencies": [],
  "notes": {},
  "source_raw": "…"
}
```

Ein künftiger Plan könnte beispielsweise stattdessen Bereiche `A`, `B`, `C` oder vollkommen andere Titel besitzen. Ein Importer darf deshalb **nicht** anhand des Titels auf eine feste Domain-Tabelle mappen, sofern Roo dies nicht bewusst als separate, optionale fachliche Zuordnung anbietet.

### `id`
Bevorzugt die offizielle Gliederungs-ID. Sie ist innerhalb eines Plans eindeutig.

### `title`
Titel genau dieser Planfassung.

### `introduction`
Einleitender Kompetenztext des Bereichs, sofern vorhanden.

### `source_raw`
Bereinigte, aber strukturell möglichst quellnahe Textrepräsentation des gesamten Bereichs. Dient Audit, Nachparsern und späteren Importverbesserungen.

## 8. Kompetenzen und Varianten

```json
{
  "id": "3.1.1.1",
  "number": 1,
  "variants": [
    {"level": "G", "text": "…"},
    {"level": "M", "text": "…"},
    {"level": "E", "text": "…"}
  ],
  "references_raw": []
}
```

`number` ist die in der Quelle sichtbare laufende Nummer. `id` entsteht bevorzugt aus Domain-ID plus Nummer.

### Nicht differenzierte Kompetenz

```json
"variants": [
  {"level": null, "text": "…"}
]
```

### Differenzierte Kompetenz

Der Wert in `variant.level` verweist auf eine ID aus `stage.levels`. Dadurch kann ein anderer Bildungsplan andere Niveaubezeichnungen verwenden, ohne das Schema zu ändern.

## 9. Verweise

Die aktuelle Konvertierung bewahrt Verweise zunächst verlustarm als:

```json
"references_raw": [
  "2.2 Deuten (1)",
  "3.1.2 Welt und Verantwortung (3)",
  "D 3.1.1.1 Literarische Texte"
]
```

Für Roo empfiehlt sich in einem zweiten Import-/Normalisierungsschritt zusätzlich eine strukturierte Relationstabelle. Ein mögliches internes Zielmodell wäre:

```json
{
  "type": "content_competency",
  "target_plan": "self",
  "target_subject": "REV",
  "target": "3.1.2.3",
  "raw": "3.1.2 Welt und Verantwortung (3)"
}
```

Das Austauschformat legt die möglichen `type`-Werte absichtlich noch nicht als Enum fest, weil die baden-württembergischen Pläne unterschiedliche Verweistypen (Prozesskompetenzen, andere Inhaltsbereiche, andere Fächer, Leitperspektiven, Leitfäden usw.) enthalten.

## 10. Hinweise (`notes`)

`notes` enthält maschinell erkannte Zusatzangaben der Quelle, z. B. mögliche Bibeltexte, Fachbegriffe oder Personen. Diese Schlüssel sind **keine obligatorischen Schemafelder**. Ein Plan darf andere oder keine Hinweise enthalten.

Beispiel:

```json
{
  "notes": {
    "bible_passages": ["Gen 1,1–2,4a", "Ps 23"],
    "terms": ["Schöpfung", "Segen"]
  }
}
```

Importer müssen unbekannte Schlüssel tolerieren.

## 11. Ergänzende Inhalte

`supplementary_content_raw` enthält nach den inhaltsbezogenen Kompetenzen folgende Inhalte, die noch nicht stärker strukturiert wurden, beispielsweise Operatoren oder Anhänge. Das Feld darf leer sein.

## 12. Erweiterbarkeit und Importregeln

Für Roo sollten folgende Regeln gelten:

- Unbekannte zusätzliche Felder ignorieren bzw. als Metadaten erhalten, nicht als Fehler behandeln.
- Reihenfolge von `stages`, `domains`, `competencies` und `variants` erhalten.
- IDs innerhalb desselben Plans niemals ohne Planbezug global als eindeutig annehmen.
- Domain-Titel niemals als technische Enum verwenden.
- `variant.level = null` als normale, nicht differenzierte Form behandeln.
- Bei `metadata.conversion.complete = false` den Datensatz nicht stillschweigend als vollständigen Bildungsplan freigeben.
- `source_url`, `source_raw` und Rohverweise nach Möglichkeit erhalten, damit die Konvertierung auditierbar bleibt.

## 13. Aktuelle Dateien dieses Pakets

Das Paket enthält fünf Plan-Dateien. Grundschule, Sekundarstufe I, die ältere Gymnasium-V1-Fassung und GMS-Oberstufe wurden aus den offiziellen PDF-Exporten extrahiert. Beim Gymnasium V3.0 konnte die offizielle Hauptseite im verwendeten Extraktionszugang nicht vollständig geladen werden; deshalb enthält diese Datei nur die verifizierte Struktur und ist ausdrücklich mit `metadata.conversion.complete = false` markiert. Die ältere V1-Fassung bleibt als eigene, klar gekennzeichnete Planfassung erhalten und wird nicht als Ersatz für V3.0 verwendet.
