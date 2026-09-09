# ADR 0016: Benutzerbezogenes Besitzmodell

## Status

Angenommen – 2026-09-09

## Kontext

Roo ist kein Schulverwaltungs- oder Organisationsportal. Mehrere einzelne
Lehrkräfte sollen die Anwendung parallel verwenden können, ohne Daten zu
teilen. Die bisherige Organisationstabelle stellte dafür einen unnötigen und
missverständlichen Zwischenschritt dar.

## Entscheidung

Veränderliche Roo-Daten gehören direkt über `user_id` zu einem Benutzerkonto.
Das gilt für Schulen, Schuljahre, Unterrichtsgruppen, Schüler:innen,
Planungen, Ressourcen, Lieder, Beobachtungen, Bewertungen und Vorlagen.
Beziehungen ohne eigenen Besitzschlüssel sind über ihre geschützte Eltern-
Beziehung einem Benutzerkonto zugeordnet.

Importierte EducationPlans und Curricula bleiben gemeinsame Referenzdaten mit
`user_id = null`. Sie sind unveränderlich und dürfen gelesen werden. Eine
benutzerbezogene Curriculum-Kopie und jede konkrete Zuordnung zu Schule oder
Unterrichtsgruppe sind private Daten.

Schüler:innen werden nicht in Meilisearch indexiert. Die Suche nach
Schüler:innen erfolgt innerhalb des Besitzer-Scope direkt in der Datenbank.

## Migration

Die zweistufige Migration ergänzt und befüllt `user_id`, prüft vor der
Übernahme Organisationen mit mehreren Benutzerkonten, entfernt anschließend
die alten Organisation-Fremdschlüssel und löscht die Organisationstabelle.
Nicht eindeutig zuordenbare Daten führen zum Abbruch statt zu einer stillen
Fehlzuordnung. Die Entfernungsmigration ist absichtlich nicht automatisch
rückrollbar; dafür ist eine Sicherung wiederherzustellen.

## Konsequenzen

- Policies und Controller prüfen direkte Benutzer-IDs.
- Globale Referenzdaten und private Daten sind in Abfragen ausdrücklich
  unterscheidbar.
- Es gibt keine Benutzerverwaltung innerhalb einer Organisation.
- Ein späteres echtes Teilen von privaten Inhalten wäre eine neue, bewusste
  Fachentscheidung und keine Nebenwirkung des Datenmodells.
