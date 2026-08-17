# ADR 0003: Lokaler PHP-FPM-Healthcheck

- Status: Accepted
- Datum: 2026-08-17

## Kontext

Das ursprünglich verwendete externe `php-fpm-healthcheck`-Release ist nicht
mehr verfügbar. Ein Docker-Build darf nicht von einer veralteten Release-URL
abhängen.

## Entscheidung

Der PHP-Container verwendet einen versionierten lokalen Healthcheck, der die
PHP-FPM-Konfiguration mit `php-fpm --test` validiert. Der Healthcheck wird als
Teil des Roo-Images installiert und benötigt keinen zusätzlichen Dienst.

## Folgen

- Der Build bleibt reproduzierbar, solange das PHP-Basisimage verfügbar ist.
- Der Check validiert Konfiguration und Startfähigkeit, nicht die fachliche
  Erreichbarkeit der Anwendung. Diese wird separat über Web-/Feature-Tests
  geprüft.
