# ADR 0007 – Klassenbezeichnung an Schüler:innen und Jahrgangsstufen an Gruppen

Status: Accepted

## Entscheidung

Roo führt in Phase 4 keine separat zu pflegende `SchoolClass`-Stammdatenquelle
ein. Jede:r `Student` speichert mit `class_name` die tatsächliche Klasse, etwa
`2a`. `TeachingGroup` besitzt dagegen einen frei wählbaren Namen und darf ohne
Mitglieder angelegt werden.

Die Jahrgangsstufen einer Unterrichtsgruppe werden relational als beliebig
viele `TeachingGroupGradeLevel`-Einträge gespeichert. Beim Anlegen ist
mindestens ein Eintrag erforderlich. Die Werte bleiben zunächst offene Strings,
damit auch Bezeichnungen wie `5/6` oder schulartspezifische Stufen möglich
sind.

## Begründung

Eine Religionsgruppe kann Schüler:innen aus mehreren realen Klassen und auch
mehreren Jahrgangsstufen enthalten. Eine zusätzliche Klassenverwaltung würde
für den gewünschten Arbeitsablauf unnötige Stammdatenpflege erzwingen und die
tatsächliche Klassenzugehörigkeit nicht besser abbilden.
