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
  └── CurriculumSchoolAssignment (mandantenbezogen, zeitlich gültig)

CurriculumTopic
  ├── CurriculumTopicCompetency (offene Referenzen, konfessionell oder prozessbezogen)
  ├── CurriculumTopicProfile (offene konfessionelle Perspektive)
  └── year (Jahrgangszuordnung einer konkreten Curriculumfassung)

CurriculumVersion
  ├── CurriculumEducationPlanBinding (optional aufgelöst oder nur mit plan_code)
  └── CurriculumImportRun

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

Die erzeugten `SchoolYearDay`-Datensätze bilden für jeden Kalendertag den
aktuellen Status, die Bezeichnung und optionale Notizen ab. Eine manuelle
Bearbeitung wird zusätzlich als `CalendarException` gespeichert; dadurch
bleiben Status, Bezeichnung und Notizen auch bei einer erneuten Generierung
aus Ferien- und Kalenderdaten erhalten. Das Datum ist in der Tagesbearbeitung
nicht veränderbar.

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
  `CompetenceVariant` mit optionalem Niveau gespeichert. Kompetenzen besitzen
  außerdem einen fachlichen Aktivstatus, damit importierte, aber für Roo nicht
  verwendete Einträge ausgeblendet bzw. gezielt wieder aktiviert werden können.
- Strukturierte oder rohe Kompetenzverweise werden als
  `CompetenceRelation` gespeichert. Die Rohreferenz sowie, sofern vorhanden,
  Typ, Zielplan und Zielkennung bleiben erhalten; eine spätere Normalisierung
  kann interne oder planübergreifende Zielbeziehungen ergänzen.
- Bereichsnotizen und Rohtexte werden strukturiert bzw. als Text übernommen.
  Unbekannte Provider-Metadaten und der gesamte Quellstand bleiben im
  Payload-Snapshot erhalten.

`EducationPlanImportRun` protokolliert Quelle, SHA-256-Prüfsumme, Status,
Zeitpunkte, Fehler und Importstatistik. Ein erneuter Import derselben
Plan-/Versionskennung aktualisiert den Versionssnapshot und ersetzt deren
abgeleitete relationale Inhalte innerhalb einer Transaktion.

Die Phase-2-Oberfläche unterstützt Suche über Plan-, Fassungs-, Bereichs- und
Kompetenztexte, hierarchische Detailansicht, Fassungsvergleich und die Anzeige
der Importläufe. Der Vergleich arbeitet mit den stabilen externen
Kompetenzkennungen und kennzeichnet hinzugekommene, entfernte, geänderte und
unveränderte Einträge.

### Curriculumimport und eigene Curricula (Phase 3)

Die Dateien unter `data/curricula/curricula` werden über
`curricula:import <Datei|Verzeichnis>` importiert. Jede Quelle wird als
`Curriculum` mit einer `CurriculumVersion` gespeichert. Unterrichtseinheiten
werden relational als `CurriculumTopic` geführt; die vollständige JSON-Quelle
bleibt in `raw_payload` erhalten. Dadurch können Parser später verbessert
werden, ohne die Originalquelle erneut zu beschaffen.

Die Felder `denominations`, `shared_plan.type`, Schularten und Rollen von
Bildungsplanbindungen bleiben offene Strings. Kompetenzreferenzen speichern
ihre erkannte Kennung, Anzeigeform und den unveränderten Referenztext. Eine
Binding darf zunächst nur `plan_code` besitzen; wird der zugehörige
Bildungsplan später importiert, kann `education_plan_id` ergänzt werden.

`CurriculumTopicCompetency.denomination` ist für importierte
prozessbezogene Kompetenzen verpflichtend, weil die offiziellen
Curriculum-Quellen jede Prozesskompetenz konfessionell kennzeichnen.
Die Datenbank lässt das Feld dennoch nullable, damit ein eigenes Curriculum
gemeinsame Prozesskompetenzen zunächst ohne Zuordnung erfassen und später
zuordnen kann. Inhaltsbezogene Kompetenzen übernehmen ihre Konfession aus dem
jeweiligen Profil.

Ein eigenes Curriculum wird aus einer oder mehreren importierten Fassungen
abgeleitet. Die Einheiten, Kompetenzreferenzen und Perspektiven werden beim
Anlegen kopiert (Copy-on-use), `derived_from_id` dokumentiert die Herkunft.
Die Jahrgangszuordnung liegt an der kopierten `CurriculumTopic` und kann in
der Curriculumansicht per Drag-and-drop oder per Auswahlfeld geändert werden.
Nicht zugeordnete Einheiten bleiben sichtbar. Das ist bewusst keine Änderung
an der Quellvorlage.

Eine vorhandene `units[].year`-Angabe im JSON-Quellformat wird beim Import als
Startzuordnung übernommen. Dadurch können redaktionell gepflegte
Jahrgangsverteilungen zwischen Quelle und Datenbank ausgetauscht werden.

Curriculumfassungen besitzen `CurriculumEducationPlanBinding`-Einträge je
Konfession und Rolle. Der `plan_code` kann in der Curriculumansicht gegen
einen importierten Bildungsplan aufgelöst werden. Die einzelnen
`CurriculumTopicCompetency`-Referenzen werden über ihre externe Kennung auf
`EducationPlanCompetency` verknüpft; nicht auflösbare Referenzen bleiben mit
Rohtext und Kennung erhalten. Beim Import muss jede Prozesskompetenz eine
Konfession tragen; bei eigenen Curricula sind gemeinsame Prozesskompetenzen
als noch nicht zugeordnete Entwürfe möglich.

Die Curriculumansicht stellt die Bildungsplanbindungen sowie getrennte
Bearbeitungsdialoge für inhaltsbezogene und prozessbezogene Kompetenzen bereit.
Bei eigenen Curricula werden Bindungen, Kompetenzreferenzen und optionale
Prozesskonfessionen aus den gewählten Vorlagen übernommen. Ein eigenes
Curriculum kann außerdem ohne Vorlage angelegt werden.

Die Bearbeitungsansicht verwendet `Curriculum.grades` als Metadaten für die
Jahrgangsspalten und blendet nicht relevante Jahrgänge aus. Beim Anlegen eines
eigenen Curriculums wird diese Liste, sofern nicht ausdrücklich angegeben, aus
der Vereinigungsmenge der ausgewählten Quellcurricula abgeleitet. Fehlen auch
dort Jahrgangsmetadaten, bleibt als Fallback die allgemeine Auswahl der Klassen
1 bis 10 sichtbar.

Eigene Curriculumfassungen sind direkt bearbeitbar. Die Metadaten Titel,
Schulart und Jahrgänge sowie je UE Titel, Zeitbedarf, Notizen und
Vorbereitungsfragen werden strukturiert gespeichert. Vorlagenfassungen bleiben
inhaltlich nicht editierbar; die Jahrgangszuordnung darf jedoch auch an einer
importierten Fassung korrigiert werden. Diese Korrektur bleibt bei einem
erneuten Import anhand der stabilen UE-Kennung erhalten.

Kopierte UEs behalten über `source_curriculum_version_id` ihre Herkunft. Die
Ansicht gruppiert nicht zugeordnete UEs dadurch nach Quellcurriculum; neu
angelegte UEs besitzen keine Quellreferenz und erscheinen unter „Eigene UE“.
Eine eigene UE kann direkt mit Titel, Klasse, Zeitbedarf und Notizen angelegt
und anschließend wie jede andere eigene UE bearbeitet werden.

Beim Ableiten aus einer Quelle wird eine automatische Jahrgangszuordnung nur
dann vorgenommen, wenn die Quelle genau einen Jahrgang in `metadata.grades`
angibt. Quellen mit einem Bereich wie 5/6 oder 7/8/9 enthalten in der
vorliegenden Austauschstruktur keine verlässliche UE-spezifische Zuordnung;
ihre kopierten UEs bleiben deshalb zunächst offen. Zahlenpräfixe in einzelnen
Titeln werden nicht als Jahrgang interpretiert, weil sie in den Quellen nicht
konsistent und teilweise außerhalb des Metadatenbereichs sind.

### Curriculum und Schule

`CurriculumSchoolAssignment` ordnet ein Curriculum einer Schule innerhalb der
Organisation der angemeldeten Lehrkraft zu. Die Zuordnung kann mit `valid_from`
und `valid_until`, Schulart, Jahrgängen und Notizen ergänzt werden. Ein global
importiertes Curriculum kann dadurch organisationsbezogen verwendet werden,
ohne die globale Vorlage zu verändern.

Die erste Phase-3-Datenbankmigration umfasst außerdem zeitlich erweiterbare
`curriculum_school_assignments` für die spätere Schule-zu-Curriculum-
Zuordnung. Die UI dafür folgt im nächsten vertikalen Schritt, sobald Schulen
und Unterrichtsgruppen das Curriculum fachlich benötigen.

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
