# Entscheidung 0015: Direkte Bildungsplan-Kompetenzreferenzen

## Status

Angenommen

## Kontext

Der frühere Unterrichtseinheits-Zuordnungsdatensatz beschrieb keine eigene
Kompetenz. Jede vorhandene Zeile verwies auf eine offizielle
`EducationPlanCompetency`, wurde aber als Identität für
Unterrichtseinheits-, Stunden- und Nachweiszuordnungen verwendet und enthielt
zusätzlich Curriculum-Kontext.

## Entscheidung

`EducationPlanCompetency` ist die einzige Kompetenzdefinition. Unterrichts- und
Stundenbezüge verwenden direkte Bildungsplan-Fremdschlüssel. Kontextdaten wie
Curriculum-Referenz und sekundäre Zuordnung werden in relationalen
Zuordnungstabellen/Pivots gehalten. Eigene Prozesskompetenzen bleiben ein
separates Modell und dürfen nicht in offizielle Kompetenzreferenzen gemischt
werden.

Die Umstellung erfolgt additiv mit Backfill, Integritätsprüfungen und einer
abschließenden Migration zum Entfernen der Legacy-Tabelle.

Die Migration besteht aus zwei Schritten. Zuerst werden direkte
Fremdschlüssel und der neue Unterrichtseinheits-Pivot angelegt und aus den
alten Zuordnungen befüllt. Erst danach prüft eine zweite Migration alle
Zuordnungen und entfernt die Legacy-Fremdschlüssel, Spalten und Tabelle.

Die Rückwärtsmigration des letzten Schritts ist absichtlich gesperrt: Aus den
direkten Referenzen lässt sich die frühere Zuordnungsidentität nicht in allen
Fällen verlustfrei rekonstruieren. Für einen Rollback nach diesem Schritt ist
ein Datenbank-Backup wiederherzustellen. Vorwärtsmigrationen brechen bei
fehlenden offiziellen Referenzen oder nicht übertragenen Zuordnungen ab.

## Folgen

- Keine konkurrierenden Kompetenztexte oder uneindeutigen Kompetenz-IDs mehr.
- Curriculum- und Denominationskontext bleibt erhalten.
- Die Datenstruktur wird vorübergehend komplexer, weil eine sichere
  Übergangsmigration und ein Backfill erforderlich sind.
- Alte Legacy-Fremdschlüssel dürfen erst nach erfolgreicher Datenprüfung
  entfernt werden.
