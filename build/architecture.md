# Architektur

## Stil

Roo wird als modularer Monolith entwickelt.

```text
Browser
  │
  ▼
Caddy
  │
  ▼
Laravel + Inertia
  ├── PostgreSQL
  ├── Redis/Horizon
  ├── Meilisearch
  ├── S3-kompatibler Storage
  └── externe Dienste über Adapter
```

## Gründe

- Die fachlichen Module hängen eng zusammen.
- Transaktionen über Planung, Stunden und Bewertungen bleiben einfach.
- Deployment und lokale Entwicklung bleiben überschaubar.
- Module können später extrahiert werden, wenn reale Last oder
  Benutzerkontosgrenzen dies verlangen.

## Schichten

```text
HTTP / Inertia
    ↓
Application Actions
    ↓
Domain Rules
    ↓
Eloquent / Infrastructure
```

Controllers koordinieren nur Request, Autorisierung, Action und Response.

## Modulstruktur – Zielbild

Eine mögliche Struktur, die schrittweise eingeführt wird:

```text
app/
├── Domain/
│   ├── Schools/
│   ├── SchoolYears/
│   ├── EducationPlans/
│   ├── Curricula/
│   ├── TeachingGroups/
│   ├── Planning/
│   ├── Lessons/
│   ├── Songs/
│   ├── Assessment/
│   └── Documents/
├── Http/
├── Jobs/
├── Policies/
└── Providers/
```

Nicht vorschnell für jedes Objekt Repository-Interfaces einführen. Eloquent
darf innerhalb des modularen Monolithen verwendet werden. Externe Dienste
erhalten jedoch Interfaces und Adapter.

## Datenbank

PostgreSQL ist die Quelle der Wahrheit.

### Besitzmodell

Roo ist ein persönliches Lehrwerkzeug. Jedes veränderliche Fachobjekt gehört
direkt über `user_id` zu genau einem Benutzerkonto. Policies und Abfragen
verwenden diesen Besitzbezug; ein Benutzerkonto sieht niemals die Schulen,
Schuljahre, Gruppen, Schülerdaten, Planungen, Materialien, Lieder,
Beobachtungen oder Bewertungen eines anderen Kontos. Eine eigene Curriculum-
Kopie ist ebenfalls benutzerbezogen.

Importierte Bildungspläne und Curricula sind dagegen gemeinsame, unveränderliche
Referenzdaten. Sie haben `user_id = null` und dürfen von allen Konten gelesen
werden. Verwendungen dieser Referenzdaten, insbesondere Zuordnungen zu Schulen
und Gruppen, bleiben benutzerbezogene Daten. Schülerdaten werden aus
Datenschutzgründen nicht in Meilisearch indexiert; die Schüler:innen-Suche läuft
direkt über die geschützte Datenbankabfrage.

Die frühere Organisationstabelle ist vollständig entfernt. Die Migration in
`2026_09_09_120000_add_user_ownership_to_records.php` übernimmt bestehende
Datensätze nur dann automatisch, wenn jede frühere Organisation höchstens ein
Benutzerkonto hatte. Andernfalls bricht sie mit einer verständlichen Meldung
ab. Die nachfolgende Entfernungsmigration löscht die alte Struktur erst nach
einer Prüfung auf verwaiste private Datensätze; ihr Rollback erfolgt sicher über
eine Sicherungswiederherstellung.

JSONB nur für:

- externe Rohdaten mit festgehaltener Herkunft,
- flexible, nicht kernfachliche Provider-Metadaten,
- versionierte Payload-Snapshots,
- nachvollziehbare Importprotokolle.

Keine Kerndomäne ausschließlich in JSONB speichern.

Bildungsplanimporte folgen diesem Grundsatz: Planfassungen, Stufen,
Kompetenzbereiche, Kompetenzen, Varianten und Verweise werden relational
gespeichert. Die ursprüngliche JSON-Payload bleibt zusätzlich als
versionsgebundener Audit-Snapshot erhalten. Ein unvollständiger Import wird
über seinen Status bzw. `is_complete` sichtbar gemacht und nicht still als
vollständige Fassung behandelt.

## IDs

Standardentscheidung:

- interne Primärschlüssel: bigint,
- extern sichtbare Kennungen: ULID,
- Imports zusätzlich mit externer Quell-ID.

Eine spätere Abweichung benötigt eine ADR.

## Zeit

- Timestamps in UTC speichern.
- Fachliche Schultermine mit IANA-Zeitzone interpretieren.
- Standard: Europe/Berlin.
- Datum ohne Uhrzeit als `date`, nicht als Mitternachts-Timestamp speichern.

## Kompetenzreferenzen

`EducationPlanCompetency` ist die einzige offizielle Kompetenzentität.
Curriculumthemen und Unterrichtseinheiten speichern direkte Referenzen auf
Bildungsplandaten; der Unterrichtseinheit-Pivot
`teaching_unit_education_plan_competencies` bewahrt zusätzlich Curriculum- und
Herkunftskontext. Stunden und Kompetenznachweise verwenden direkte
`education_plan_competency_id`-Fremdschlüssel. Eigene
`CustomProcessCompetence`-Datensätze sind davon getrennt.

## Suche

Die globale Suche unter `/suche` bündelt die unterstützten, mandantengeschützten
Datentypen. Nicht-personenbezogene Inhalte werden über Meilisearch gefunden;
Schüler:innen werden für die Suche direkt in PostgreSQL abgefragt und niemals
in den Meilisearch-Index geschrieben. Beobachtungen und Bewertungen werden
nicht als globale Suchtreffer ausgegeben. Das Topbar-Feld sucht entprellt und
öffnet beim Drücken der Eingabetaste die vollständige Ergebnisseite.

## Dokumente

Dateiinhalte liegen im Object Storage. PostgreSQL speichert:

- Besitzer
- fachliche Zuordnung
- Storage-Key
- Originalname
- MIME-Type
- Größe
- Prüfsumme
- Sicherheitsstatus
- Erstellungsquelle
- Version

Downloads erfolgen über autorisierte Controller bzw. kurzlebige signierte URLs.

Gezielt freigegebene Unterrichtseinheiten können über signierte öffentliche
Ansichten geteilt werden. Die Ansicht wird aus dem strukturierten
Unterrichtsobjekt aufgebaut und berücksichtigt nur ausdrücklich veröffentlichte
Materialien, Links und Galeriebilder; Schülerdaten und Bewertungen gehören nie
zum öffentlichen Inhalt.

## Suche

Meilisearch indexiert zunächst nur nicht-personenbezogene Bibliotheks- und
Planungsinhalte. Schüler:innen, Beobachtungen und Bewertungen bleiben außerhalb
des Suchindexes.

## Queues

Vorgesehene Queues:

- default
- documents
- pdf
- search
- imports
- ai
- notifications

Jobs müssen nach Möglichkeit idempotent sein.

## KI

Kein Fachmodell kennt direkt OpenAI-Klassen.

```text
Application Action
    ↓
AiTextProvider interface
    ↓
OpenAiTextProvider
```

Jeder KI-Vorgang speichert mindestens:

- Zweck
- Provider
- Modell
- Prompt-Version
- Zeitpunkt
- anonymisierte Eingabereferenz
- Ergebnisstatus
- menschliche Freigabe

## ADRs

Architekturentscheidungen unter `build/decisions/`.

Dateiname:

```text
NNNN-kurzer-titel.md
```

Status:

- Proposed
- Accepted
- Superseded
- Rejected
