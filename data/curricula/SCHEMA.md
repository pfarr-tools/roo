# Roo-Schema: konfessionell-kooperative Curricula

## Ziel

Dieses Format modelliert Curricula **getrennt von Bildungsplänen**. Ein Bildungsplan beschreibt normative Kompetenzen; ein Curriculum ordnet diese Kompetenzen zu Unterrichtseinheiten, gemeinsamen Inhalten, konfessionellen Perspektiven und Planungsfragen.

Das Schema ist bewusst offen: Weder Unterrichtseinheiten, Konfessionen, Spalten, Perspektiven noch Arten gemeinsamer Inhalte sind fest auf die hier vorliegenden Beispielcurricula begrenzt.

## Wurzelobjekt

- `schema_version`: Version dieses Importformats.
- `type`: `confessional_cooperative_curriculum`.
- `metadata`: Identität und Herkunft.
- `education_plan_bindings`: Bildungspläne, auf die das Curriculum Bezug nimmt.
- `units`: geordnete Unterrichtseinheiten.

## `metadata`

- `title`: Titel des Curriculums.
- `country`, `state`
- `school_type`: frei importierbarer Schlüssel, hier `GS`, `SEK1`, `GYM`.
- `grades`: konkrete Klassenstufen.
- `variant`: Herausgebervariante, hier `A` oder `B`; semantisch **kein Niveau**.
- `cooperation_model`: hier `confessional_cooperative`.
- `denominations`: beteiligte Traditionen als offene Liste.
- `source`: URL und Dateiformat.
- `conversion`: technische Importinformationen.

## `education_plan_bindings`

Ein Curriculum kann auf mehrere Bildungspläne verweisen. Bindings sind nicht auf evangelisch/katholisch beschränkt.

```json
{
  "role": "denominational_basis",
  "denomination": "evangelical",
  "subject": "Evangelische Religionslehre",
  "plan_code": "BP2016BW_ALLG_GS_REV"
}
```

`plan_code` darf beim Rohimport `null` sein und später durch einen Resolver gesetzt werden. Dadurch bleibt der Curriculum-Import unabhängig davon, ob der zugehörige Bildungsplan bereits in Roo vorhanden ist.

## `units[]`

Jede Unterrichtseinheit besitzt:

- `id`, `number`, `title`
- `year`: optionale konkrete Jahrgangszuordnung der UE. Sie wird von Roo beim
  Import übernommen und kann in der Anwendung korrigiert werden.
- `hours`: soweit aus der Vorlage eindeutig extrahierbar
- `preparation_questions[]`
- `shared_plan[]`
- `denominational_profiles`
- `process_competencies[]`
- `raw_rows`: verlustarme Repräsentation der Quelltabelle

### `shared_plan[]`

Eine offene, geordnete Liste. `type` ist **nicht enum-festgelegt**. Die vorliegenden Dokumente liefern insbesondere:

```json
{"type":"central_content","text":"..."}
```

Künftige Curricula können weitere Typen einführen, ohne das Schema zu ändern.

### `denominational_profiles`

Map statt fest verdrahteter Felder:

```json
{
  "evangelical": {
    "content_competencies": [],
    "perspective": []
  },
  "catholic": {
    "content_competencies": [],
    "perspective": []
  }
}
```

Der Schlüssel ist frei. Damit wären z. B. altkatholische, orthodoxe oder andere Kooperationsmodelle abbildbar.

`content_competencies[]` enthält `raw` und erkannte `references[]`. Die Originalformulierung bleibt erhalten, weil ein Curriculum Kompetenzen zitieren, verkürzen oder mit Zusatzhinweisen versehen kann.

### Kompetenzreferenzen

```json
{"id":"3.1.1.1","display":"3.1.1 (1)"}
```

Die Referenz-ID dient dem späteren Linking gegen einen importierten Bildungsplan. `display` bewahrt die Schreibweise der Quelle.

### `process_competencies[]`

Analog strukturierte Referenzen auf prozessbezogene Kompetenzen.

### `raw_rows`

Alle Tabellenzeilen der jeweiligen UE werden zusätzlich als Zellarrays erhalten. Das ist absichtlich Teil des Importformats: Die kirchlichen Vorlagen sind redaktionell nicht vollständig einheitlich. Roo kann dadurch später mit einem verbesserten Parser neu normalisieren, ohne die DOCX-Datei erneut zu benötigen.

## Abgrenzung zum Bildungsplan-Schema

`education_plan` und `confessional_cooperative_curriculum` sind zwei verschiedene Root-Typen. Das Curriculum **enthält den Bildungsplan nicht**, sondern referenziert ihn. Dadurch können:

1. mehrere Curricula denselben Bildungsplan verwenden,
2. A/B-Varianten parallel existieren,
3. Schulen eigene Curricula aus einem Beispielcurriculum ableiten,
4. Curriculum und Bildungsplan unabhängig revisioniert werden.

## Empfohlene Roo-Relationen

- `Curriculum belongsToMany EducationPlan`
- `Curriculum hasMany CurriculumUnit`
- `CurriculumUnit hasMany CompetencyReference`
- `CurriculumUnit hasMany DenominationalProfile`
- Schule ↔ Curriculum weiterhin many-to-many.

Für abgeleitete schulinterne Curricula empfiehlt sich zusätzlich ein optionales `derived_from` auf Curriculum-Ebene.

## Importprinzip

Importer sollten unbekannte `school_type`, `denominations`, `shared_plan.type` und zusätzliche Felder tolerieren. Insbesondere dürfen Domains aus Bildungsplänen und Perspektiven aus Curricula niemals durch eine fest codierte Liste validiert werden.
