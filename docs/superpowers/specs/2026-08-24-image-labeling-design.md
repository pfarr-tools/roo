# Bild beschriften – Design

## Ziel

Der Aufgabentyp `image_labeling` verwendet genau ein Bibliotheksbild und mehrere markierte Referenzpunkte. Jede Markierung besitzt einen Lösungstext und kann in der Evaluation einzeln als korrekt beschriftet bewertet werden.

## Entscheidungen

- Das Bild wird über `assessment_task_images` referenziert.
- Punkte werden relational in `assessment_task_image_labels` gespeichert; jedes Feld besitzt eine eigene Zeilenanzahl mit Default `1`.
- `x_percent` und `y_percent` sind relative Bildkoordinaten von 0 bis 100.
- `position` bestimmt die Reihenfolge; `solution` enthält den Lösungstext.
- Die Aufgaben-Konfiguration enthält `image_width_cm` (4–8), `points_per_correct_answer` und `show_solutions`.
- Der ODT-Export ist nicht Teil dieses Schnitts.
- Die Evaluation zeigt eine Checkbox pro gespeicherter Lösung.
