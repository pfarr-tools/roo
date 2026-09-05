# Roo-Anwendung

Dieses Verzeichnis enthält die Laravel-Anwendung von Roo. Die fachliche
Produktbeschreibung und die lokalen Startbefehle stehen im
[Repository-README](../README.md).

## Aufbau

- `app/Domain/` enthält fachlich getrennte Bereiche des modularen Monolithen.
- `app/Http/` enthält Requests, Controller und Inertia-Antworten.
- `app/Jobs/` enthält lang laufende oder asynchrone Verarbeitung.
- `app/Policies/` schützt den Zugriff auf mandantenbezogene Daten.
- `database/` enthält Migrationen, Seed-Daten und Testgrundlagen.
- `resources/js/` enthält die Vue-/Inertia-Oberfläche und die zentrale
  Frontend-Lokalisierung.
- `resources/views/` enthält serverseitige Ansichten, insbesondere öffentliche
  und dokumentbezogene Ausgaben.
- `routes/` enthält die HTTP-Routen der Anwendung.

## Entwicklungsregeln

Die Anwendung wird ausschließlich über Docker Compose und den Wrapper `./roo`
aus dem Repository-Wurzelverzeichnis betrieben. PHP, Composer und Node.js
müssen nicht auf dem Host installiert werden.

Vor Änderungen sind `../AGENTS.md`, `../build/masterplan.md` und die jeweils
betroffenen Architekturentscheidungen zu lesen. Fachliche Änderungen erhalten
Tests; personenbezogene Daten dürfen weder in Logs noch in nicht ausdrücklich
geschützte externe Dienste gelangen.

## Verifikation

Der sichere Standard für Backend-Tests ist:

```bash
./roo test
```

Der Befehl verwendet eine isolierte Testdatenbank. Für Frontend-Tests und den
Produktions-Build stehen die im Root-README beschriebenen Docker-Befehle zur
Verfügung. Änderungen sollten zusätzlich mit `./roo pint` und
`git diff --check` geprüft werden.
