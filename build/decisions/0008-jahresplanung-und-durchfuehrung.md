# ADR 0008 – Jahresplanung und tatsächliche Durchführung getrennt speichern

## Status

Akzeptiert

## Kontext

Eine Unterrichtseinheit wird über einen Zeitraum geplant, kann verschoben,
geteilt oder unterbrochen werden und erzeugt daraus einzelne Stunden. Eine
ausgefallene oder tatsächlich an einem anderen Tag gehaltene Stunde darf die
ursprüngliche Planung nicht überschreiben.

## Entscheidung

Die Jahresplanung besteht aus einem `GroupYearPlan` je Unterrichtsgruppe,
geordneten `PlannedUnit`-Einheiten und daraus erzeugten `PlannedLesson`-
Stunden. Jede geplante Stunde besitzt ein oder mehrere
`LessonOccurrence`-Vorkommnisse mit geplantem Datum, optionalem tatsächlichem
Datum und einem Status. Änderungen werden als `PlanRevision` mit Benutzer,
Aktion und Beschreibung protokolliert.

Ferien, schulfreie Tage und Kalenderausnahmen werden beim Erzeugen von
Vorkommnissen ausgeschlossen. Einheiten dürfen nur innerhalb des zugehörigen
Schuljahres liegen. Die UI bietet Drag-and-drop sowie Tastaturverschiebung;
beide Wege nutzen dieselbe serverseitige Validierung.

## Konsequenzen

- Historische Planung und tatsächliche Durchführung bleiben nachvollziehbar.
- Die Erzeugung ist durch eindeutige Datenbankschlüssel idempotent.
- Eine spätere Stundeneditor-Phase kann an `PlannedLesson` und
  `LessonOccurrence` anschließen, ohne die Jahresplanung umzubauen.
- Die Revisionsliste ist zunächst fachlich lesbar; ein vollständiger Audit Log
  folgt in Phase 13.
