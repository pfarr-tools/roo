# ADR 0005: Relationales Importmodell für Bildungspläne

- Status: Accepted
- Datum: 2026-08-17

## Kontext

Die Bildungsplanquellen unter `data/bildungsplaene` enthalten eine variable,
mehrstufige Struktur mit Planfassungen, Stufen, Jahrgängen, Kursarten,
Niveaus, Kompetenzbereichen, Varianten und Rohverweisen. Einzelne Fassungen
können absichtlich unvollständig sein.

## Entscheidung

- Bildungspläne und Fassungen werden relational gespeichert.
- Kompetenzbereiche bleiben generisch; ihre Titel werden nicht als Enum
  modelliert. Prozess- und Inhaltsbereiche werden über `kind` unterschieden.
- Differenzierte Kompetenztexte werden als Varianten derselben Kompetenz
  gespeichert.
- Rohverweise werden als eigene Relationen mit unverändertem Quelltext
  übernommen. Eine spätere Normalisierung darf Zielkennungen ergänzen.
- Die vollständige importierte JSON-Payload bleibt pro Fassung als
  Audit-Snapshot erhalten.
- Jeder Import wird mit Quelle, Prüfsumme, Status, Statistik und Fehlern als
  `EducationPlanImportRun` protokolliert.
- `is_complete` wird aus der Quelle übernommen; unvollständige Fassungen sind
  importierbar, aber nicht automatisch vollständig freigegeben.

## Konsequenzen

- Bildungspläne können gesucht, verglichen und mit Curricula verknüpft werden,
  ohne die Kerndomäne in JSONB zu verstecken.
- Neue Schularten, Stufen, Bereiche und Niveaubezeichnungen erfordern keine
  Schemaänderung.
- Der Import bleibt nachvollziehbar und bei verbesserter Normalisierung erneut
  ausführbar.
- Eine UI für Importfreigabe, Versionsvergleich und Suche folgt in weiteren
  Phase-2-Arbeitsschritten.
