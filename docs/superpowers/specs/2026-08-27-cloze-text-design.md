# Lückentext – Design

## Ziel

Ein neuer AssessmentTask-Typ `cloze` ermöglicht Lückentexte, deren Lücken aus
dem Aufgabentext in eckigen Klammern abgeleitet werden. Die Lücken erhalten
jeweils Punkte und werden als automatisch erzeugte Erwartungen bewertet.

## Datenmodell

`content.prompt` enthält den vollständigen Text mit Lücken in der Form
`[...]`. Die Zeichen innerhalb der Klammern sind die erwartete Lösung und
werden nicht als separates Eingabefeld gespeichert. Zusätzlich werden in
`content` gespeichert:

- `show_solutions: bool`
- `lineated: bool`
- `split_blank_words: bool`
- `blanks: list<{id: string, solution: string, points: int}>`

Die IDs werden aus der Position im Prompt abgeleitet (`blank-1`, `blank-2`,
...). Beim Speichern wird die Liste serverseitig aus dem Prompt synchronisiert;
Punkte werden nach ID übernommen, neue Lücken erhalten einen Punkt. Die
automatischen Erwartungen werden aus dieser Liste erzeugt und als normale
Task-Erwartungen gespeichert, damit das bestehende Bewertungs- und
Review-Schema unverändert genutzt werden kann. Sie sind im Editor nicht als
manuelle Erwartungen editierbar.

Die Erwartung lautet exakt `Du hast korrekt ausgefüllt: <solution>` und erhält
die Punktzahl der zugehörigen Lücke. Zusätzliche manuelle Erwartungen werden
weiterhin separat vor Sonderpunkten angezeigt.

## Darstellung

Der bestehende Task-Titel/Aufgabentext und die optionale Lösungsliste bleiben
unverändert. Danach wird der Prompt als Folge von Text- und Lückenfragmenten
ausgegeben.

`HandwritingSpaceEstimator::estimateWidthMm()` bestimmt für jede Lücke die
Breite anhand der Lösung und der Jahrgangsstufe. Ohne Lineatur wird diese
Breite mit Unterstrichen angenähert. Mit `split_blank_words` wird eine
mehrwortige Lösung in einzelne Wortlücken mit einem Leerzeichen dazwischen
aufgeteilt; ohne die Option bleibt sie eine zusammenhängende Lücke.

Bei Lineatur erzeugt `PngRulingRenderer::render()` je Lücke ein transparentes
PNG mit der geschätzten Breite. Das PNG wird inline in das ODT eingebettet.
Die verwendete Ruling-Preset bestimmt die Bandhöhe; die inline-Lücke erhält
mindestens diese Höhe, damit die Lineatur nicht abgeschnitten wird. Die
Bilddateien werden deterministisch benannt und in der bestehenden ODT-Datei
mitgeführt.

## Validierung und Bewertung

Der Prompt muss mindestens eine gültige, nicht leere Lücke enthalten; nicht
geschlossene oder verschachtelte Klammern werden abgelehnt. Punkte sind
positive Integer. Die serverseitig synchronisierten Erwartungen bilden die
vollständige automatische Lückenbewertung; die vorhandene
`ExpectationEvaluationRow`-Oberfläche und `SaveAssessmentTaskReview` bleiben
zuständig für Anzeige und Speicherung.

## Abgrenzung

Es gibt keine automatische Textanalyse der Schülerantwort und keine neue
Bewertungslogik. Die Lehrkraft bewertet jede Lücke über genau eine Checkbox.
