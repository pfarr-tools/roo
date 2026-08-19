# ADR 0011: Zentrale Kompetenzpräsentation

## Status

Angenommen

## Entscheidung

Kompetenzen werden in der Oberfläche nicht mehr durch eigenes Raten des
jeweiligen Relationsfeldes dargestellt. `App\Services\CompetencyResolver`
ist die zentrale Auflösungsstelle für Kennung, Kompetenzart, Text und Label.

Die Auflösung verwendet diese Reihenfolge:

1. `local_wording` der konkreten Zuordnung,
2. Text bzw. `raw_text` des Curriculum-Snapshots,
3. Text des Bildungsplan-Datensatzes,
4. Varianten des Bildungsplans,
5. lokale Fallback-Felder.

Die Kompetenzart kommt aus dem Bildungsplanbereich und danach aus dem
Curriculum-Snapshot. Fehlt beides, ist sie `content`. Die normalisierten Daten
werden als `competency_presentation` mit `kind`, `identifier`, `text` und
`label` an Inertia-Ansichten gegeben.

## Konsequenzen

Neue Ansichten laden die benötigten Relationen einmal im Controller und
verwenden anschließend `competency_presentation`. Frontend-Komponenten dürfen
für Übergangskompatibilität rohe Relationen als Fallback lesen, sollen aber
keine eigene Textpriorität mehr erfinden. Änderungen an Import- oder
Snapshot-Feldern werden dadurch an einer Stelle nachvollziehbar angepasst.
