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
       ├── SchoolType
       ├── GradeLevel
       ├── EducationPlanStage
       │    ├── GradeLevel (n:m)
       │    ├── Level (n:m)
       │    └── CompetenceArea
       │         └── Competence
       │              ├── CompetenceVariant
       │              └── CompetenceRelation
       ├── CompetenceArea (processbezogen)
       │    └── Competence
       └── GuidingPrinciple

EducationPlanImportRun
  └── EducationPlanVersion

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

### Bildungsplanimport

Die Dateien unter `data/bildungsplaene` werden als versionsgebundene,
strukturierte JSON-Quelle importiert. Die relationale Datenbank ist die
fachliche Quelle für die späteren Ansichten und Verknüpfungen; die vollständige
Import-Payload bleibt zusätzlich an `EducationPlanVersion.raw_payload` als
unveränderter Audit-Snapshot erhalten.

Ein `EducationPlan` identifiziert den fachlichen Plan über die externe
Plankennung, etwa `BP2016BW_ALLG_GS_REV`. Jede Fassung ist ein
`EducationPlanVersion` mit eigenem Versionslabel, Quell-URL, Fassungsdatum,
Schema-Version und dem Flag `is_complete`. Dadurch kann auch eine absichtlich
unvollständige Struktur-Fassung importiert und angezeigt werden, ohne sie als
vollständigen Bildungsplan auszugeben.

Die generische Austauschstruktur wird wie folgt relational abgebildet:

- `guiding_principles` werden zu `GuidingPrinciple`.
- Prozessbezogene Bereiche und stufenbezogene Inhaltsbereiche werden beide als
  `CompetenceArea` gespeichert. `kind` unterscheidet `process` und `content`;
  ein Bereichstitel ist kein Enum.
- Stufen werden als `EducationPlanStage` mit Position, Label und optionaler
  Kursart gespeichert. Jahrgänge (`GradeLevel`) und Differenzierungsniveaus
  (`Level`) sind jeweils n:m an eine Stufe gebunden und bleiben damit für
  nichtnumerische bzw. planabhängige Bezeichnungen offen.
- Eine `Competence` trägt externe Kennung und laufende Nummer. Der direkte
  Text ist optional; differenzierte Formulierungen werden als geordnete
  `CompetenceVariant` mit optionalem Niveau gespeichert.
- `references_raw` werden als `CompetenceRelation` gespeichert. Die
  Rohreferenz bleibt immer erhalten; eine spätere Normalisierung kann interne
  oder planübergreifende Zielkennungen ergänzen.
- Bereichsnotizen und Rohtexte werden strukturiert bzw. als Text übernommen.
  Unbekannte Provider-Metadaten und der gesamte Quellstand bleiben im
  Payload-Snapshot erhalten.

`EducationPlanImportRun` protokolliert Quelle, SHA-256-Prüfsumme, Status,
Zeitpunkte, Fehler und Importstatistik. Ein erneuter Import derselben
Plan-/Versionskennung aktualisiert den Versionssnapshot und ersetzt deren
abgeleitete relationale Inhalte innerhalb einer Transaktion.

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

## Phase-2-Datenbankentscheidungen

- Bildungspläne sind standardmäßig globale Referenzdaten
  (`organization_id` darf null sein); organisationsbezogene Importe können
  über denselben Owner-Scope getrennt werden.
- Externe Kennungen sind nur innerhalb des Plans bzw. der Fassung eindeutig.
  Die Datenbank erzwingt deshalb Eindeutigkeit immer mit dem jeweiligen
  Plan-/Versionsbezug.
- Die normalisierten Tabellen sind die Arbeitsdaten. JSON wird nur für den
  unveränderten Quell-Snapshot, flexible Notizen und stufenspezifische
  Provider-Daten verwendet.
- `SchoolType` wird zunächst fassungsbezogen als `education_plan_school_types`
  geführt; eine globale Referenztabelle wird erst eingeführt, wenn Schulen
  und Curricula tatsächlich gemeinsame Normalisierungsanforderungen haben.
