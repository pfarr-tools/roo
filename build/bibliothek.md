# Bibliothek

Stand: 21. August 2026

Diese Übersicht beschreibt die Eintragstypen der Seite `/bibliothek` und die
Zuordnungslogik des `ResourceLibraryController`. Mit „in Phasen aktivieren“ ist
hier die Zuordnung zu einer konkreten `LessonPhase` gemeint. Es gibt keinen
zusätzlichen Aktivierungsstatus; ein zugeordnetes Element wird in der Phase
angezeigt bzw. bei der Stundenspeicherung berücksichtigt.

## Eintragstypen

| Typ in der Bibliothek | Datenstruktur | Zuordnung zu | In Phasen aktivieren? | Beschreibungslinie | Weitere Aktionsbuttons (ohne Bearbeiten/Löschen) |
|---|---|---|---|---|---|
| **Datei** (`file`) | `ResourceReference`: private gespeicherte Datei mit `original_name`, `storage_path`, MIME-Typ, Größe, Seitenzahl, Beschreibung und Copyrights. | Unterrichtseinheit, Unterrichtsstunde oder Phase. Die Zuordnung zu Einheit/Stunde wird über `teaching_unit_id`/`lesson_id` gehalten, die Phasenzuordnung über `lesson_phase_resources`. | **Ja.** Eine Datei kann direkt einer Phase zugeordnet werden. | Zeigt den gespeicherten Freitext `description`. Der Dateiname steht als Hauptname; MIME-Typ und Dateigröße erscheinen zusätzlich in „Details“. | **Vorschau**, nur für Bild-, Video- und Audiodateien; **Herunterladen**, immer. |
| **Ressource** (`resource`) | `ResourceLink`: Titel, validierte URL und optionaler Freitext `description`; keine Datei und kein eigener Storage. | Unterrichtseinheit, Unterrichtsstunde oder Phase. Einheit/Stunde über `teaching_unit_id`/`lesson_id`, Phase über `lesson_phase_resource_links`. | **Ja.** Eine URL-Ressource kann direkt einer Phase zugeordnet werden. | Zeigt `description`, falls vorhanden. Darunter wird die URL separat als anklickbarer Link in einem neuen Tab ausgegeben. | Kein zusätzlicher Button. Die URL selbst ist die Aktion und öffnet sich in einem neuen Tab. |
| **Material** (`material`) | `MaterialItem`: Name, optionale Materialnummer, Lagerort, Beschreibung und optionales Bild im privaten Storage. Zuordnungen liegen in der polymorphen Pivot-Tabelle `material_itemables`; Phasen haben zusätzlich `lesson_phase_material_items`. | Unterrichtseinheit, Unterrichtsstunde oder Phase. Das Modell kann außerdem mit `PhaseTemplate` verbunden werden. | **Ja.** Ein Materialbestandteil kann direkt einer Phase zugeordnet werden. | Zeigt den Freitext `description`. Materialnummer und Lagerort erscheinen nicht in der Beschreibungslinie, sondern in „Details“. | **Vorschau**, nur wenn ein Bild hinterlegt ist; sonst kein zusätzlicher Button. |
| **Lied** (`song`) | `SongVersion` als Bibliothekseintrag, verbunden mit `Song`; optional mit `SongSheet` und generierten A5-, A4- und Instrumenten-Liedblättern. | Unterrichtseinheit, Unterrichtsstunde oder Phase über `unit_songs`, `lesson_songs` bzw. `phase_songs`. Beim Zuordnen zu einer Gruppe wird das Lied außerdem in deren Gruppenliederbuch aufgenommen. | **Ja.** Eine Liedfassung kann direkt in einer Phase ausgewählt werden. | Die Beschreibung wird dynamisch aus den Credits des übergeordneten Liedes gebildet: `Text: … / Musik: …`, bei identischen Angaben `Text & Musik: …`. Fehlen beide Credits, bleibt die Beschreibungslinie aktuell leer; `copyright_notice` wird in diesem Fall durch die aktuelle Fallback-Logik nicht erreicht. Der Name ist der Liedtitel; der Fassungsname wird in der globalen Tabellenzeile nicht separat angezeigt. | Bedingt vorhandene Downloads: **A5**, **A4** und je vorhandener Instrumentenfassung ein **Akkordblatt**. Der Editor-Button ist bewusst nicht aufgeführt. |
| **Prüfungsaufgabe** (`assessment-task`) | `AssessmentTask`: organisationsgebundene, wiederverwendbare Aufgabe mit Titel, Lösung/Erwartungshorizont, Maximalpunkten und einer verpflichtenden `TeachingUnitCompetency`. G/M/E-Niveaus liegen als Mehrfachbeziehung in `assessment_task_levels`. | Einer oder mehreren Unterrichtsstunden über `lesson_assessment_tasks`. Zusätzlich kann sie in beliebig vielen Lernstandserhebungen einer Gruppe über `assessment_task_assessment` enthalten sein. | **Nein.** Die aktuelle Zuordnung ist auf Unterrichtsstunden begrenzt; eine Phasenbeziehung gibt es nicht. | Der Aufgabentitel ist die Hauptzeile. Die zugeordnete Kompetenz erscheint darunter; die gewählten G/M/E-Niveaus stehen in „Details“. Lösung/Erwartungshorizont und Maximalpunkte werden im Editor bzw. in der Lernstandserhebung verwendet. | Kein weiterer spezieller Button. |

## Kontextabhängiger Eintragstyp

| Typ | Datenstruktur | Zuordnung zu | In Phasen aktivieren? | Beschreibungslinie | Weitere Aktionsbuttons (ohne Bearbeiten/Löschen) |
|---|---|---|---|---|---|
| **Gruppenliederbuch** (`songbook`) | `GroupSongbook`: ein Gruppenbestand mit Einträgen (`GroupSongbookEntry`), Druckständen und optionalem Titelblatt. Es ist kein einzelnes Lied und keine Datei. | Nur im Kontext einer Unterrichtsgruppe; Unterrichtseinheit, Unterrichtsstunde oder Phase über die jeweiligen `*_songbooks`-Pivot-Tabellen. | **Ja.** Das Gruppenliederbuch kann einer konkreten Phase zugeordnet werden. | Keine eigene Beschreibung; in der Darstellung wird nur „Gruppenliederbuch“ als Name verwendet. | Auf der globalen Seite `/bibliothek` nicht vorhanden und dort daher ohne Aktionsbutton. Im gruppenbezogenen Bibliothekskontext sind keine weiteren Zeilenaktionen implementiert. |

Das Gruppenliederbuch wird vom Controller nur bei einer gruppenbezogenen Route
(`/jahresplanung/{teachingGroup}/ressourcen`) ergänzt. Der globale Filter auf
`/bibliothek` kennt deshalb die fünf Typen Datei, Ressource, Material, Lied und
Prüfungsaufgabe.

## Prüfungsaufgaben und Lernstandserhebungen

Eine Lernstandserhebung ist eine Zusammenstellung für eine konkrete Gruppe und
optional einen `ReportPeriod` sowie einen konkreten Termin. Beim Speichern werden Bibliotheksaufgaben
nicht kopiert, sondern über `assessment_task_assessment` aufgenommen. Dadurch
kann dieselbe Prüfungsaufgabe in mehreren Erhebungen verwendet werden und ohne
Erhebungszuordnung in der Bibliothek bleiben.

Für Gruppen, deren Jahrgangsangabe G/M/E ausweist, muss jede in eine Erhebung
aufgenommene Aufgabe mindestens einem Niveau zugeordnet sein. Mehrere Niveaus
(z. B. G und M) sind gleichzeitig zulässig. Bei nicht differenzierten Gruppen
ist die Niveauauswahl optional.

## Gemeinsame Zuordnungsregeln

- Zuordnungen können aus der Bibliothek für die Zieltypen **Einheit**,
  **Stunde** und **Phase** angelegt werden.
- Bei einer Phasenzuordnung wird die Auswahl in einer eigenen Pivot-Beziehung
  gespeichert; sie ist damit unabhängig von der bloßen Zuordnung zur Einheit
  oder Stunde.
- Einträge bleiben nach dem Entfernen einer Zuordnung in der Bibliothek
  erhalten. Eine dauerhafte Löschung ist nur möglich, wenn keine Zuordnung mehr
  besteht.
- Die globalen Zeilenaktionen Bearbeiten und Löschen sind in dieser Übersicht
  absichtlich nicht noch einmal je Typ beschrieben.
