# LSE-Kategorien, Erwartungslisten und manuelle Exemplare

## Ziel

Eine Lehrkraft kann eine Lernstandserhebung einem Zeugnisbereich der
Unterrichtsgruppe oder ausdrücklich keiner Kategorie zuordnen, Erwartungen als
nicht ausgedruckte Prozesskompetenz-Aufgabe verwenden und LSE-Exemplare ohne
Scan direkt einer Schülerin bzw. einem Schüler zuordnen und bewerten.

Das Beispiel „Ordnereinsicht“ wird dadurch als normale LSE mit der Kategorie
„Ordner“, einer Erwartungsliste und einem manuell angelegten Exemplar abbildbar.

## Entscheidungen

- Eine LSE hat höchstens eine Kategorie.
- „Keine Zuordnung“ ist für diagnostische LSEs zulässig.
- Ein manuelles Exemplar benötigt beim Anlegen eine direkte
  Schüler:innen-Zuordnung.
- Eine spätere Umzuordnung bleibt möglich und verwendet die bestehende
  Exemplar-Zuordnungslogik.
- Eine Erwartungsliste ist ausschließlich einer Prozesskompetenz zugeordnet.
- Erwartungslisten erscheinen niemals in einem Dokumentexport.
- Erwartungslisten verwenden in der Evaluation die vorhandenen
  Erwartungszeilen, Checkboxen, Punkte und Notizen.

## Fachliches Modell

### Kategorie einer LSE

Die Kategorieauswahl stammt aus den aktiven Notenmix-Bestandteilen der Gruppe.
Das Assessment speichert eine nullable Referenz auf den Bestandteil und einen
Bezeichnungs-Snapshot. Die Referenz wird beim Speichern auf dieselbe Gruppe
begrenzt; „Keine Zuordnung“ speichert `null`.

Notenmix-Bestandteile dürfen nicht hart gelöscht werden, solange sie von einer
LSE verwendet werden. Beim Entfernen werden sie als inaktiv markiert und
bleiben für historische LSEs und den Bezeichnungs-Snapshot erhalten. Neue
Bestandteile erhalten eine neue stabile Kennung.

Das Assessment-Formular zeigt die Auswahl beim Anlegen und Bearbeiten. Die
Gruppenansicht zeigt die Kategorie in der LSE-Liste.

### Erwartungsliste

`expectation_list` wird ein eigener stabiler Wert von `AssessmentTask.task_type`.
Der Aufgabeneditor bietet dafür nur die fachlich notwendigen Felder:

- Titel
- Bildungsplan-Kompetenz aus einem Prozessbereich
- Erwartungen einschließlich der vorhandenen Punkte-, Wiederholungs- und
  Bewertungsoptionen

Die Validierung prüft nicht nur die Existenz der Kompetenz, sondern auch, dass
`EducationPlanCompetency.area.kind === process`. Eine Inhaltskompetenz wird
serverseitig abgewiesen, auch wenn sie im Frontend manipuliert übermittelt
wird.

Der Dokumentrenderer filtert Erwartungslisten vor der Dokumenterzeugung aus.
Bei der Evaluation werden sie wie eine generische erwartungsbasierte Aufgabe
ohne Scanbild dargestellt. Für die Ergebnissynchronisierung zählt nur die
Bewertung der Erwartungen.

### Manuelles Exemplar

`AssessmentBooklet` erhält eine Herkunft (`scan` oder `manual`). Ein manuelles
Exemplar besitzt keine Scandatei und keine Fragmente. Das Anlegen erfolgt über
ein einfaches Modal im LSE-Evaluationsbereich und verlangt die Auswahl einer
Schülerin bzw. eines Schülers. Die laufende Exemplarnummer wird wie bei
gescannten Exemplaren innerhalb der LSE vergeben.

Die Evaluation erzeugt für jedes manuelle Exemplar und jede Aufgabe ein
Bewertungsziel ohne Bildfragment. Der bestehende `TaskEvaluation`-Baustein
kann dadurch die Erwartungsliste und andere generische Aufgaben bewerten. Der
Review-Endpunkt autorisiert bei manuellen Exemplaren die Kombination aus
Exemplar und LSE-Aufgabe auch ohne Fragment; bei Scan-Exemplaren bleibt die
Fragmentprüfung bestehen.

Die vorhandene `AssignAssessmentBooklet`-Logik bleibt die zentrale Stelle für
Umzuordnung und Konfliktprüfung. `SyncStudentAssessmentResult` verwendet die
direkte Schüler:innen-Zuordnung des manuellen Exemplars.

## Datenfluss

```text
TeachingGroup grade components
          │
          └── Assessment.category (nullable + label snapshot)
                    │
                    ├── AssessmentTask (normal / expectation_list)
                    │          │
                    │          └── AssessmentBooklet (scan / manual)
                    │                         │
                    │                         └── AssessmentTaskReview
                    │                                      │
                    │                                      └── StudentAssessmentResult
                    └── Export: expectation_list filtered out
```

## Vertikaler Implementierungsschnitt

1. Migrationen und Models für nullable LSE-Kategorie, stabile/inaktive
   Notenmix-Bestandteile und Exemplar-Herkunft; bestehende Daten erhalten
   sichere Defaults.
2. Assessment-Formular, Request-Validierung und Gruppenbegrenzung der
   Kategorieauswahl einschließlich „Keine Zuordnung“.
3. Neuer `expectation_list`-Editor, Prozesskompetenzfilter und serverseitige
   Prozessbereichsvalidierung; Exportfilter.
4. Manuelles-Exemplar-Modal mit direkter Schüler:innen-Zuordnung, virtuelle
   Bewertungsziele und Review-Autorisierung ohne Scanfragment.
5. Deutsche Lokalisierung sowie Feature-, Unit- und Frontend-Tests für alle
   oben genannten Randfälle.

## Abnahmekriterien

- Eine LSE kann einer aktiven Gruppenkategorie oder „Keine Zuordnung“
  zugeordnet werden.
- Eine fremde oder inaktive Kategorie kann nicht per Request gespeichert
  werden.
- Eine verwendete Kategorie bleibt historisch lesbar, wenn sie aus dem
  aktuellen Notenmix entfernt wird.
- Eine Erwartungsliste ohne Prozesskompetenz oder mit Inhaltskompetenz wird
  abgewiesen.
- Eine Erwartungsliste wird nicht gedruckt, aber vollständig evaluierbar
  gespeichert.
- Ein manuelles Exemplar kann mit direkter Schüler:innen-Zuordnung angelegt,
  umzugeordnet und ohne Scan bewertet werden.
- Scan-Exemplare behalten ihre bisherige Fragment- und Sicherheitslogik.
- Keine Schüler:innendaten gelangen in Logs oder öffentliche URLs.
- Fokussierte Backend- und Frontend-Tests, Build und `git diff --check` sind
  erfolgreich.
