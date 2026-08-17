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
  Organisationsgrenzen dies verlangen.

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

JSONB nur für:

- externe Rohdaten mit festgehaltener Herkunft,
- flexible, nicht kernfachliche Provider-Metadaten,
- versionierte Payload-Snapshots,
- nachvollziehbare Importprotokolle.

Keine Kerndomäne ausschließlich in JSONB speichern.

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
