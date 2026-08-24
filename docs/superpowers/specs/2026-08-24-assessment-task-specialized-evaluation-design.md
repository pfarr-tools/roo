# AssessmentTask: spezialisierte Auswertung für Checkbox-Aufgaben

## Status

Entwurf zur Umsetzung nach Bestätigung durch den Nutzer.

## Ziel

AssessmentTasks sollen je nach `task_type` eine eigene Bewertungsoberfläche
und Bewertungslogik erhalten können. Der erste spezialisierte Typ ist
`checkbox`: Die Lehrkraft sieht bei der Auswertung alle Antwortoptionen und
markiert, welche Optionen die Schülerin bzw. der Schüler angekreuzt hat. Roo
berechnet daraus die Punkte.

Manuelle Erwartungen und zusätzliche Punkte bleiben für alle Task-Typen
Bestandteile der bestehenden generischen Auswertung.

## Entscheidungen

- `checkbox` ist der öffentliche Task-Typ für Multiple-Choice-Aufgaben.
- `multiple_choice` wird nicht als eigener Typ weitergeführt.
- Eine angekreuzte korrekte Option erhält die konfigurierte Punktzahl pro
  korrekter Antwort.
- Eine angekreuzte inkorrekte Option erhält null Punkte; es gibt keine
  Minuspunkte.
- Manuelle Erwartungen werden nicht automatisch aus den Optionen erzeugt.
- Zusätzliche Erwartungen werden unterhalb der spezialisierten Checkbox-
  Oberfläche mit der bestehenden Erwartungsbewertung angezeigt.
- Die Oberfläche für zusätzliche Punkte bleibt unverändert.

## Task-Daten

`AssessmentTask.content` enthält für neue Checkbox-Aufgaben strukturierte
Optionen und die einheitliche Punktzahl:

```json
{
  "options": [
    {"id": "a1", "text": "Option A", "correct": true},
    {"id": "a2", "text": "Option B", "correct": false}
  ],
  "points_per_correct_answer": 1
}
```

Optionen erhalten stabile IDs. Dadurch bleiben gespeicherte Auswertungen auch
bei einer späteren Umsortierung der Optionen korrekt zugeordnet. Die
Punktzahl wird nicht je Option dupliziert.

`assessment_task_expectations` enthält ausschließlich manuelle Erwartungen.
Ihre Wiederholungen und Punkte werden weiterhin über die bestehende
Erwartungslogik bewertet.

## Bewertungsarchitektur

Die generische Bewertungsseite orchestriert spezialisierte und gemeinsame
Bereiche:

```text
TaskEvaluation
├── CheckboxTaskEvaluation
├── ManualExpectationEvaluation
└── ExtraPointsEvaluation
```

Die Auswahl des spezialisierten Bereichs erfolgt anhand von `task_type`.
Weitere Task-Typen können später eigene Komponenten und serverseitige
Evaluatoren registrieren, ohne die generische Oberfläche mit allen
fachlichen Regeln zu belasten.

Der Checkbox-Evaluator liefert dem Server nur Option-ID und Auswahlstatus.
Der Server prüft, dass die Option zur Aufgabe gehört, und berechnet die
Optionspunkte aus der aktuellen Taskdefinition. Punkte aus dem Browser werden
nicht akzeptiert.

Die Auswahl wird strukturiert in einer separaten Tabelle gespeichert:

- `assessment_task_review_id`
- stabile Option-ID
- `selected` als Boolean

Die vorhandenen Review-Items bleiben für manuelle Erwartungen zuständig.
Damit bleiben Optionsauswertung und Erwartungsauswertung getrennt und können
jeweils ihre eigenen Integritätsregeln verwenden.

## Editor

Der Editor zeigt für `checkbox`:

- eine Liste von Antwortoptionen;
- Text und Korrektheitsmarkierung je Option;
- eine numerische Eingabe „Punkte je korrekte Antwort“;
- eine separate Liste optionaler manueller Erwartungen.

Die bisherige Checkbox-Funktion zur automatischen Erzeugung von Erwartungen
wird entfernt. Beim Speichern werden nur nicht-leere manuelle Erwartungen
übermittelt.

`multiple_choice` wird aus Enum, Lokalisierung, Auswahl und
Validierungswerten entfernt, nachdem vorhandene Datensätze konvertiert wurden.

## Migration und Kompatibilität

Vor der Migration wird der Datenbestand auf `task_type = 'multiple_choice'`
geprüft. Solche Datensätze werden auf `checkbox` konvertiert.

Alte Checkbox-Aufgaben mit automatisch erzeugten Erwartungen dürfen bei der
neuen Auswertung nicht doppelt zählen. Die Umsetzung muss deshalb entweder
die automatisch erzeugten Erwartungszeilen in die neue Optionspunktzahl
überführen und nur manuelle Zeilen behalten oder sie eindeutig als Legacy-
Zeilen markieren und aus dem neuen Optionsbereich ausschließen.

Die Migration wird mit Null-, Normal- und Legacy-Daten getestet. Sie darf
keine gespeicherten manuellen Erwartungen oder Review-Ergebnisse verlieren.

## HTTP- und Service-Vertrag

Der Review-Endpunkt erhält neben den bestehenden `items` und `extra_points`
eine optionale `options`-Liste. Für Checkbox-Aufgaben ist jede gültige
Option genau einmal enthalten:

```json
{
  "options": [
    {"id": "a1", "selected": true},
    {"id": "a2", "selected": false}
  ],
  "items": [],
  "extra_points": 0
}
```

Die serverseitige Speicherung und Ergebnis-Synchronisierung erfolgt in einer
Transaktion. Ungültige Option-IDs, doppelte IDs oder fehlende Optionen werden
abgelehnt. Die bestehende Autorisierung und Booklet-Zuordnung bleiben
unverändert.

## Tests

- Unit-Test für Checkbox-Punktberechnung mit korrekten, inkorrekten und nicht
  ausgewählten Optionen.
- Feature-Test für Speichern, Validierung, Wiederaufruf und
  StudentAssessmentResult-Synchronisierung.
- Feature-Test, dass manuelle Erwartungen und Checkbox-Punkte gemeinsam, aber
  nicht doppelt, gezählt werden.
- Frontend-Test für Editorfeld, Optionsliste und manuelle Erwartungen.
- Frontend-Test für spezialisierten Evaluator und unveränderte Extra-Punkte-
  Oberfläche.
- Migrationstest für `multiple_choice`-Konvertierung und alte Checkbox-Daten.
- Bestehende Bewertungs-, Dokumentexport- und Frontend-Tests bleiben grün.

## Nicht Bestandteil

- negative Punkte für falsche Auswahl;
- automatische Teilpunktlogik über mehrere Optionen;
- OCR oder automatische Erkennung der Schülerauswahl;
- neue Bewertungsworkflow-Zustände;
- Änderung der bestehenden UI für manuelle Erwartungen oder Zusatzpunkte.
