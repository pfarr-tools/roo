# ADR 0011: Zentrale Kompetenzpräsentation

## Status

Angenommen

## Entscheidung

Kompetenzen werden in der Oberfläche nicht mehr durch eigenes Raten des
jeweiligen Relationsfeldes dargestellt. `App\Services\CompetencyResolver`
ist die zentrale Auflösungsstelle für Kennung, Kompetenzart, Text und Label.

Die Auflösung verwendet ausschließlich die direkte offizielle
`EducationPlanCompetency`-Referenz:

1. Text des Bildungsplan-Datensatzes,
2. passende Varianten des Bildungsplans,
3. der zentrale Fallback `Kompetenz`, falls die importierte offizielle
   Kompetenz noch keinen Text besitzt.

Die Kompetenzart kommt aus dem Bildungsplanbereich; bei einer
Curriculumreferenz kann sie zusätzlich aus deren `competency_kind` stammen.
Fehlt beides, ist sie `content`. Die normalisierten Daten
werden als `competency_presentation` mit `kind`, `identifier`, `text` und
`label` an Inertia-Ansichten gegeben.

## Konsequenzen

Neue Ansichten laden die benötigten Relationen einmal im Controller und
verwenden anschließend `competency_presentation`. Frontend-Komponenten
verwenden die vom Backend präsentierten offiziellen Daten. Es gibt keine
Übergangskompatibilität zu einer lokalen Kompetenzformulierung oder zu
Curriculum-Textfeldern.
