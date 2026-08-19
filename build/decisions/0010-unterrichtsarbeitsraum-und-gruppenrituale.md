# ADR 0010: Unterrichtsarbeitsraum und Gruppenrituale

## Status

Akzeptiert

## Kontext

Eine eingeplante Unterrichtsstunde braucht einen eigenen Arbeitsraum für
Planung und Durchführung. Wiederkehrende Einstiegs- oder Abschlussphasen sollen
für eine Unterrichtsgruppe gepflegt werden können, ohne spätere Änderungen an
der Vorlage rückwirkend in bereits geplante Stunden zu übertragen.

## Entscheidung

`/unterricht/{slot}` ist der konkrete Arbeitsraum einer Stunde. Er trennt die
Ansichten Planung, Durchführung und Beobachtung. Die Planungsansicht speichert
Phasen gemeinsam mit den übrigen Stundendaten; die Durchführung speichert
Status, tatsächliches Datum und Notizen.

Gruppenrituale werden als geordnete Zuordnung einer Unterrichtsgruppe zu
aktiven Phasenvorlagen gespeichert. Beim Einplanen werden sie einmalig als
konkrete `LessonPhase` kopiert. Eine spätere Vorlagenänderung verändert deshalb
keine bereits angelegte Stunde. Derselbe Ritualschlüssel wird innerhalb einer
Stunde nicht doppelt eingefügt.

## Konsequenzen

- Die Gruppenverwaltung ist die Pflegeoberfläche für Rituale.
- Phasen bleiben in der Stunde unabhängig bearbeitbar.
- Eine automatisch ergänzte Phase setzt die Stunde auf „Geplant“.
- Die Beobachtungserfassung bleibt dem späteren Beobachtungsmodul vorbehalten.
