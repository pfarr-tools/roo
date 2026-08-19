# Entscheidung 0009: Status der konkreten Stunde und wiederverwendbarer Phaseneditor

**Status:** Angenommen – 19. August 2026

## Kontext

Eine geplante `Lesson` beschreibt den wiederverwendbaren Stundeninhalt. Der
Vorbereitungs- und Durchführungsstand gehört dagegen zur konkreten
Einplanung (`ScheduledLesson`). „Geplant“ soll erst gelten, wenn die Stunde
eine didaktische Binnenstruktur besitzt.

## Entscheidung

`ScheduledLesson.status` verwendet die Werte `assigned` (Inhalt zugeordnet),
`planned` (Phasen vorhanden), `ready` (Material vorbereitet), `conducted`,
`cancelled` und `postponed`. Das Hinzufügen der ersten Phase hebt eine noch
nicht weiter bearbeitete Einplanung automatisch auf `planned`. Die Statuswerte
`planned` und `ready` werden ohne Phase serverseitig abgewiesen.

Die Phasenbearbeitung besteht aus einem eigenständigen `LessonPhasesTab` und
`PhaseEditorOffcanvas`. Die Komponenten können dadurch später außerhalb des
Stundeneditors verwendet werden. Vorlagen werden beim Einfügen in eine
konkrete Phase kopiert; spätere Vorlagenänderungen verändern diese Phase nicht.

## Folgen

Die Statusanzeige kann je Einplanung unterschiedlich sein, auch wenn mehrere
Einplanungen auf dieselbe wiederverwendbare `Lesson` zeigen. Phasendauern
werden strukturiert gespeichert und gegen die vorgesehene Dauer der Stunde
geprüft. Die weiteren Durchführungsfunktionen bauen auf diesem Modell auf.
