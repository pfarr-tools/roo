# Vorläufiges Domänenmodell

Dieses Dokument ist eine fachliche Landkarte, kein fertiges Datenbankschema.

## Beziehungen

```text
Organization
  └── User

School
  ├── SchoolClass
  ├── TeachingGroup
  └── Curriculum (n:m, zeitlich gültig)

SchoolYear
  ├── HolidayPeriod
  ├── SchoolYearDay
  └── TeachingGroup

EducationPlan
  └── EducationPlanVersion
       ├── GradeLevel
       └── Competence

Curriculum
  └── CurriculumVersion
       └── CurriculumTopic
            └── Competence (n:m)

TeachingGroup
  ├── Student (n:m über zeitliche Mitgliedschaft)
  ├── TimetableSlot
  ├── Curriculum (n:m, mit Rolle)
  ├── GroupYearPlan
  └── GroupSongbook

GroupYearPlan
  └── PlannedUnit
       └── PlannedLesson
            └── LessonOccurrence

UnitTemplate
  └── LessonTemplate
       └── PhaseTemplate / konkrete Phase

Song
  └── SongVersion
       ├── SongRight
       └── SongSheet

GroupSongbook
  └── GroupSongbookEntry
       └── SongVersion

LessonOccurrence
  ├── AttendanceRecord
  ├── Observation
  └── CompetenceEvidence
```

## Wichtige Unterscheidungen

### Schuljahr vs. Gruppenjahr

Ein Schuljahr beschreibt die Kalendergrenzen und Ausnahmen. Eine
Unterrichtsgruppe ist immer eine konkrete Gruppe in genau diesem Schuljahr.

### Vorlage vs. Planung vs. Durchführung

```text
UnitTemplate
    ↓ kopieren/referenzieren
PlannedUnit
    ↓ terminiert
PlannedLesson
    ↓ tatsächlich
LessonOccurrence
```

Die tatsächlich durchgeführte Stunde kann vom Plan abweichen.

### Curriculum vs. Bildungsplan

Der Bildungsplan definiert Kompetenzen. Das Curriculum ordnet Themen,
Jahrgänge, Kompetenzen, Zeitbudgets und konfessionelle Hinweise.

### Beobachtung vs. Bewertung

Eine Beobachtung ist ein einzelner Nachweis. Eine Bewertung ist eine
verantwortete Zusammenfassung mehrerer Nachweise.

### Lied vs. Liedfassung

Das Lied ist das abstrakte Werk. Die Liedfassung enthält konkreten Text,
Satz, Sprache, Noten, Akkorde und Rechte.

## Offene Modellfragen

Diese Entscheidungen werden nicht ohne eigenen Arbeitsschritt festgelegt:

1. Unterstützt eine Unterrichtsgruppe mehrere aktive Lehrkräfte?
2. Werden Schüler:innen schuljahresübergreifend als dieselbe Person geführt?
3. Welche Ferien-/Feiertags-API wird zuerst integriert?
4. Welche formalen Regeln gelten für Viertelnoten und Plus/Minus-Anzeige?
5. Welche Liedrechte dürfen aufgrund schulischer Lizenzmodelle abgebildet
   werden?
6. Welche KoKo-Strukturen müssen neben evangelisch/katholisch möglich sein?
7. Werden Vorlagen privat, schulweit oder organisationsweit geteilt?
