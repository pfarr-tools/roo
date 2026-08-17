# Roo

<p align="center">
  <img src="src/resources/images/branding/roo-logo.png" alt="Roo – Känguru mit Buch und Kreuz" width="420">
</p>

Roo ist eine deutschsprachige Webanwendung für Lehrkräfte, die
Religionsunterricht über ein komplettes Schuljahr hinweg planen, vorbereiten,
durchführen, dokumentieren und auswerten möchten.

Der Name **Roo** leitet sich von **RU** – der gängigen Abkürzung für
Religionsunterricht – ab. Das Känguru im Logo steht sinngemäß für Roo und
begleitet die Anwendung als freundliches Markenzeichen.

## Ziel

Roo soll die tägliche Arbeit rund um den Religionsunterricht an einem Ort
verbinden: von Schule und Schuljahr über Bildungspläne, Curricula und
Unterrichtsgruppen bis zu Jahresplanung, Unterrichtseinheiten, Stunden,
Materialien, Liedern, Beobachtungen und Bewertungen.

Strukturierte Fachdaten sind dabei die Quelle der Wahrheit. Dateien wie PDF-,
DOCX- oder Präsentationsexporte entstehen aus diesen Daten und ersetzen sie
nicht. Wiederverwendbare Vorlagen und konkrete Verwendungen werden getrennt
modelliert, damit historische Planungen nachvollziehbar bleiben.

## Entwicklungsstand

Das Projekt befindet sich im Aufbau und wird als modularer Laravel-Monolith
entwickelt. Die technische Basis ist umgesetzt. Schulen, Schuljahre,
Kalenderdaten, Bildungspläne und erste Curriculum-Funktionen werden derzeit in
kleinen, testbaren Arbeitsschritten ausgebaut.

Die fachliche Roadmap steht im [Masterplan](build/masterplan.md). Verbindliche
Architektur- und Arbeitsregeln finden sich in:

- [AGENTS.md](AGENTS.md)
- [Architektur](build/architecture.md)
- [Domänenmodell](build/domain-model.md)
- [Architekturentscheidungen](build/decisions/)

## Technischer Stack

- Laravel 13 und PHP 8.4
- Vue 3 und Inertia.js 3
- Bootstrap 5.3, Sass und Bootstrap Icons
- PostgreSQL 17
- Redis und Laravel Horizon
- Laravel Scout mit Meilisearch
- S3-kompatibler Object Storage
- Mailpit für die lokale Mail-Entwicklung
- Docker Compose
- Pest und Vitest

## Voraussetzungen

Für die Entwicklungsumgebung werden auf dem Host nur benötigt:

- Docker Engine mit Docker Compose Plugin
- Git
- Bash

PHP, Composer, Node.js, PostgreSQL und Redis müssen nicht lokal installiert
werden; sie laufen in den Containern.

## Schnellstart

```bash
cp .env.example .env
./roo bootstrap
./roo up
```

Anschließend sind die wichtigsten Dienste erreichbar:

- Roo: <http://localhost:8080>
- Vite: <http://localhost:5173>
- Mailpit: <http://localhost:8025>
- Meilisearch: <http://localhost:7700>
- Object-Storage-Konsole: <http://localhost:9001>

Der erste Bootstrap erzeugt die Laravel-Anwendung in `src/`, installiert die
Frontend-Abhängigkeiten, richtet die Grundkonfiguration ein, setzt den
App-Key, führt Migrationen aus und legt die benötigten Storage-Buckets an.

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
./roo pint
./roo test
```

## Projektstruktur

```text
.
├── AGENTS.md
├── README.md
├── LICENSE
├── compose.yaml
├── Dockerfile
├── .env.example
├── roo                         Entwicklungsbefehle
├── build/                      Masterplan, Architektur und ADRs
├── data/                       Importdaten und Bildungspläne
├── docker/                     Containerkonfiguration
├── scripts/                    Hilfsskripte
└── src/                        Laravel-Anwendung
```

## Datenschutz

Schülerdaten, Beobachtungen und Bewertungen sind besonders schützenswert.
Roo berücksichtigt deshalb von Beginn an Mandantenscopes, Policies, private
Dateispeicher und den Verzicht auf Schülerdaten in Logs, Suchindizes und
KI-Anfragen.

## Mitentwicklung

Arbeite in kleinen, abgeschlossenen vertikalen Schnitten. Lies vor größeren
Änderungen `AGENTS.md`, den [Masterplan](build/masterplan.md) und die jeweils
relevanten Architekturunterlagen. Änderungen sollen durch Tests abgesichert
und fachliche Entscheidungen als ADR dokumentiert werden.

## Lizenz

Roo steht unter der [GNU General Public License Version 3](LICENSE).
