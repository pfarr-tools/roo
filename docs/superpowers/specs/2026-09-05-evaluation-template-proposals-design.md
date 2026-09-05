# Bewertungsvorlagen je Zeitraum

## Ziel

Im Tab „Bewertungen“ einer Unterrichtsgruppe mit dem Bewertungsmodell
„Kompetenztexte und Noten“ können Bewertungszeiträume angelegt und die daraus
erzeugten vorgeschlagenen Bewertungstexte je Bildungsplan-Niveau bearbeitet
werden.

## Fachliche Regeln

- „+ Zeitraum“ steht in der Topbar des Bewertungs-Tabs in
  `TeachingGroups/Show.vue` und führt zur bestehenden Zeitraum-Anlage.
- Für jeden Zeitraum gibt es eine eigene Vorlagenbearbeitung.
- Die Vorlagen gelten nur für `competency_texts_and_grades`; bei
  `observation_scales` und `grades_only` werden keine Vorlagen erzeugt oder
  angezeigt.
- Behandelte Kompetenzen sind Inhaltskompetenzen, die über eine
  `TeachingUnitCompetency` einer Lesson zugeordnet sind und deren
  `ScheduledLesson` innerhalb der Grenzen des Zeitraums liegt.
- Der Vorschlag wird aus `data/bildungsplaene/Kompetenzsaetze.json` gebildet.
  Die Zuordnung erfolgt über die externe Kennung der
  `EducationPlanCompetency`.
- Sätze werden in der Reihenfolge der ersten Behandlung innerhalb des
  Zeitraums und anschließend nach Kompetenzposition dedupliziert.
- Der erste Satz behält `[Vorname]`. In jedem weiteren Satz wird jedes
  `[Vorname]` durch `[Pronomen]` ersetzt. Die Sätze werden mit genau einem
  Leerzeichen verbunden.
- Wenn die verwendete Bildungsplan-Kompetenz Varianten für `G`, `M` und/oder
  `E` besitzt, wird je vorhandenem Niveau eine eigene Vorlage erzeugt. Ohne
  Differenzierung wird eine Vorlage ohne Niveau erzeugt.
- Jede Vorlage speichert `original_text` als unveränderlichen Vorschlag und
  `text` als bearbeitbaren aktuellen Text. „Zurücksetzen“ setzt `text` auf
  `original_text` zurück.
- Vorlagen sind Snapshots: Änderungen an Bildungsplan- oder Planungsdaten
  verändern bestehende Vorlagen nicht automatisch.

## Technischer Schnitt

Eine neue relationale Tabelle `report_period_evaluation_templates` gehört zu
`report_periods` und enthält `report_period_id`, nullable `level`,
`original_text`, `text` sowie Zeitstempel. Auf dem Zeitraum gilt eine Unique-
Constraint für `(report_period_id, level)`.

Der bestehende `EvaluationController` erzeugt die Vorlagen beim Anlegen eines
Zeitraums innerhalb derselben Transaktion wie Zeitraum und
Schülerbewertungen. Eine dedizierte Service-Klasse kapselt die Ermittlung der
behandelten Inhaltskompetenzen und die JSON-basierte Textgenerierung.

Die Gruppenansicht lädt Vorlagen und zeigt pro Zeitraum eine Aktion zur
Bearbeitung. Die Bearbeitungsseite enthält je Niveau eine Textarea, einen
Reset-Button und eine Speichern-Aktion. Alle Schreibzugriffe autorisieren die
Unterrichtsgruppe.

## Abnahme

- Zeitraum-Anlage bleibt für andere Bewertungsmodelle funktionsfähig.
- Bei einem differenzierten Plan werden die Niveau-Vorlagen korrekt getrennt.
- Platzhalter- und Leerzeichenregeln sind automatisiert getestet.
- Editieren, Zurücksetzen, Speichern und Mandant-/Gruppenscope sind getestet.
