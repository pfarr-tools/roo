# ADR 0006 – Relationaler Curriculumimport und abgeleitete Curricula

Status: Accepted

## Kontext

Die Curriculumquellen unter `data/curricula` enthalten Unterrichtseinheiten,
Kompetenzreferenzen, konfessionelle Profile und verlustarme Rohzeilen. Ein
Curriculum kann außerdem auf mehrere Bildungspläne verweisen und soll als
Ausgangspunkt für ein schulisches Curriculum dienen.

## Entscheidung

Curricula werden wie Bildungspläne relational gespeichert. Eine
`CurriculumVersion` hält zusätzlich den unveränderten Quell-Payload. Eine
Unterrichtseinheit wird als `CurriculumTopic` modelliert; Kompetenzreferenzen
und konfessionelle Perspektiven werden in eigenen Tabellen geführt. Nicht
aufgelöste Bildungsplanbindungen bleiben mit `plan_code` erhalten und werden
später verknüpft, sobald der Bildungsplan importiert ist.

Eigene Curricula erhalten eine neue, bearbeitbare Fassung und ein optionales
`derived_from_id`. Beim Erstellen aus einer oder mehreren Vorlagen werden die
Einheiten kopiert (Copy-on-use). Die Jahrgangszuordnung wird als `year` an der
konkreten Curriculum-Einheit gespeichert. Das verhindert, dass spätere
Änderungen an einer Vorlage eigene Curricula unbemerkt verändern.

## Konsequenzen

- Die 16 vorhandenen JSON-Dateien sind mit einem idempotenten Artisan-Import
  importierbar.
- Offene Werte wie Schulart, Konfession und `shared_plan.type` werden nicht in
  Datenbank-Enums eingeschränkt.
- Die UI kann Einheiten aus mehreren Quellen in ein eigenes Curriculum
  übernehmen und pro Jahrgang sortieren.
- Eine spätere Versionierung innerhalb eines eigenen Curriculums muss bei
  größeren Bearbeitungsfunktionen ergänzt werden.
