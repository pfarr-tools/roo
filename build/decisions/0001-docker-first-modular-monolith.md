# ADR 0001: Docker-first modularer Monolith

- Status: Accepted
- Datum: 2026-08-06

## Kontext

Roo umfasst eng miteinander verbundene Fachbereiche und wird zunächst von
einem kleinen Entwicklungsteam gepflegt. Entwicklung und Betrieb sollen
reproduzierbar sein.

## Entscheidung

- Laravel-Anwendung als modularer Monolith.
- Docker Compose ist auch lokal der verbindliche Ausführungsweg.
- PostgreSQL, Redis, Meilisearch, Object Storage und Mailpit laufen als
  Container.
- Keine Microservices in der ersten Produktphase.
- Keine lokale PHP-/Node-Installation als dokumentierter Standardweg.

## Folgen

Positiv:

- reproduzierbare Umgebung,
- einfacher Einstieg für Codex und weitere Mitwirkende,
- Transaktionen über Modulgrenzen,
- überschaubares Deployment.

Negativ:

- Compose benötigt mehr Ressourcen,
- Dateirechte und HMR brauchen besondere Aufmerksamkeit,
- Modulgrenzen müssen diszipliniert im Code eingehalten werden.
