# Roo

Roo ist eine deutschsprachige Webanwendung für die Planung, Durchführung,
Dokumentation und Auswertung des Religionsunterrichts.

Der Name leitet sich von **RU** – Religionsunterricht – ab.

## Status

Dieses Repository ist zunächst ein **Docker-first-Basispaket** für die
Initialisierung mit Codex. Das Laravel-Projekt wird reproduzierbar durch das
Bootstrap-Skript erzeugt.

Verbindliche Vorgaben:

- [AGENTS.md](AGENTS.md)
- [Masterplan](build/masterplan.md)
- [Architektur](build/architecture.md)
- [Domänenmodell](build/domain-model.md)

## Stack

- Laravel 13 / PHP 8.4
- Vue 3 / Inertia.js 3
- Bootstrap 5.3 / Sass / Bootstrap Icons
- PostgreSQL 17
- Redis und Laravel Horizon
- Meilisearch
- S3-kompatibler lokaler Object Storage
- Mailpit
- Docker Compose

## Voraussetzungen

Auf dem Host werden nur benötigt:

- Docker Engine
- Docker Compose Plugin
- Git
- Bash

PHP, Composer, Node, PostgreSQL und Redis laufen ausschließlich in Containern.

## Schnellstart

```bash
cp .env.example .env
./roo bootstrap
./roo up
```

Danach:

- Roo: <http://localhost:8080>
- Vite: <http://localhost:5173>
- Mailpit: <http://localhost:8025>
- Meilisearch: <http://localhost:7700>
- Object-Storage-Konsole: <http://localhost:9001>

Beim ersten Bootstrap werden:

1. das Laravel-Projekt in `src/` erzeugt,
2. Inertia, Vue und Bootstrap installiert,
3. Basis-Konfigurationen angelegt,
4. der App-Key gesetzt,
5. Migrationen ausgeführt,
6. Storage-Buckets erzeugt.

## Häufige Befehle

```bash
./roo up
./roo down
./roo restart
./roo status
./roo logs
./roo shell
./roo artisan migrate
./roo artisan test
./roo composer require vendor/package
./roo npm install
./roo npm run dev
./roo pint
./roo test
```

## Projektstruktur

```text
.
├── AGENTS.md
├── README.md
├── compose.yaml
├── Dockerfile
├── .env.example
├── roo
├── build/
│   ├── masterplan.md
│   ├── architecture.md
│   ├── domain-model.md
│   └── decisions/
├── docker/
│   ├── caddy/
│   ├── php/
│   └── postgres/
├── scripts/
└── src/                 Laravel-Anwendung; wird beim Bootstrap erzeugt
```

## Arbeitsweise mit Codex

Codex soll zuerst `AGENTS.md` und `build/masterplan.md` lesen.

Geeigneter erster Auftrag:

```text
Lies AGENTS.md, build/masterplan.md und build/architecture.md.
Führe Phase 0 aus. Prüfe zuerst das vorhandene Docker-Setup.
Initialisiere anschließend Laravel 13 mit Inertia 3, Vue 3 und Bootstrap.
Arbeite in kleinen Schritten, führe Tests aus und dokumentiere Abweichungen
als ADR.
```

Danach:

```text
Implementiere aus Phase 1 den kleinsten vertikalen Schnitt:
Eine angemeldete Lehrkraft kann eine Schule anlegen, bearbeiten und auflisten.
Beachte Mandantenscope, Policies, deutsche UI, Tests und Bootstrap-Komponenten.
```

## Entwicklungsdaten

Die Werte in `.env.example` sind ausschließlich für lokale Entwicklung.
Produktionsgeheimnisse dürfen nicht eingecheckt werden.

## Lizenz

Noch festzulegen. Bis dahin ist keine Nutzungslizenz eingeräumt.
