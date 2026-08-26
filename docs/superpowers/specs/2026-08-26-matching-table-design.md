# Zuordnungstabelle – Design

## Ziel

Der AssessmentTask-Typ `matching_table` stellt eine Tabelle bereit, in der
Schüler:innen jedem Text eine oder mehrere Kategorien zuordnen, indem sie in
den passenden Kategorie-Spalten ein X setzen.

## Datenmodell

Die bestehende `AssessmentTask.content`-Struktur erhält für diesen Typ:

```php
[
    'prompt' => string,
    'points_per_correct_answer' => int,
    'matching_scoring_mode' => 'per_category'|'complete_row',
    'categories' => [
        ['id' => string, 'text' => string],
    ],
    'rows' => [
        [
            'id' => string,
            'text' => string,
            'category_ids' => list<string>,
        ],
    ],
]
```

Die Kategorien und Texte werden im bestehenden JSON-Inhalt der Aufgabe
gespeichert, da sie gemeinsam die konkrete Tabellenstruktur bilden. IDs sind
innerhalb der jeweiligen Liste eindeutig und werden beim Erstellen im
Frontend erzeugt.

## Editor

Der Editor bietet:

- eine ganzzahlige Punktzahl pro korrekter Zuordnung,
- die Auswahl zwischen „Punkte pro Kategorie“ und „Punkte pro vollständig
  gelöster Zeile“,
- eine erweiterbare Kategorienliste mit Kategorie-Titeln,
- eine erweiterbare Textliste mit Textfeld und Checkbox je Kategorie.

Eine Kategorie und ein Text werden initial angeboten. Beim Wechsel des
Aufgabentyps wird die Struktur normalisiert, ohne bestehende Werte unnötig zu
verlieren. Kategorien können entfernt werden; die zugehörigen IDs werden aus
den Textzeilen entfernt.

## Export

Der Export zeichnet eine Tabelle mit einer breiten, titellosen Textspalte und
schmalen Kategorie-Spalten. Die Kategorie-Spalten erhalten eine feste, kleine
Breite; die Textspalte nimmt den verbleibenden Inhaltsbereich ein. Jede
Datenzelle der Kategorie-Spalten bleibt leer und bietet Platz für ein X.

## Evaluation

Die Evaluation erzeugt aus den gespeicherten Zuordnungen temporäre
Erwartungszeilen:

- `per_category`: je korrekt zugeordneter Kategorie eine Zeile mit
  `Du hast <text> korrekt zur Kategorie <category> zugeordnet.`
- `complete_row`: je Text eine Zeile mit
  `Du hast <text> korrekt den Kategorien <cat1>, <cat2>, ... und <catN>
  zugeordnet.`

Diese Zeilen werden nicht als dauerhafte `AssessmentExpectation`-Datensätze
angelegt. Bewertungsentscheidungen und vergebene Punkte werden jedoch über
die bestehende Review-/Evaluation-Persistenz gespeichert. Falsche zusätzliche
Zuordnungen werden nicht positiv bewertet.

## Validierung und Tests

Die Controller validieren Kategorien, Textzeilen, eindeutige IDs,
`category_ids`, Punktzahl und Bewertungsmodus. Tests decken Erstellen und
Aktualisieren, Editor-Normalisierung, beide Erwartungstexte,
Punktberechnung sowie ODT-Struktur und Spaltenbreiten ab.
