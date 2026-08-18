# Voraussetzungen und Zielarchitektur

## Unterstütztes Betriebsmodell

Roo ist ein Laravel-13-Modularmonolith. Der empfohlene Produktionsbetrieb ist
Docker Compose auf einem einzelnen Linux-Server oder auf einer VM mit
externem TLS-Reverse-Proxy. Die Anwendung besteht aus:

| Bestandteil | Aufgabe | Produktionsanforderung |
| --- | --- | --- |
| Web-Proxy | TLS, Weiterleitung zu PHP-FPM, statische Dateien | einziger öffentlich erreichbarer Dienst |
| App | Laravel und Inertia | unveränderliches Release-Image |
| Horizon | Redis-Queue und Hintergrundjobs | dauerhaft laufender Worker |
| Scheduler | `schedule:work` | genau eine Instanz |
| PostgreSQL 17 | fachliche Quelle der Wahrheit | persistenter, gesicherter Speicher |
| Redis | Cache, Sessions, Queue, Horizon | persistenter Betrieb und Zugriffsschutz |
| Meilisearch | Suche ohne Schülerdaten | privates Netz, Schlüssel gesetzt |
| S3-Storage | private Dokumente und Exporte | verschlüsselt, nicht öffentlich |

Mailpit ist ausschließlich für lokale Entwicklung. In Produktion ist ein
vertrauenswürdiger SMTP- oder Transaktionsmaildienst zu konfigurieren.

## Servervoraussetzungen

- aktuelle Linux-VM oder dedizierter Server mit Docker Engine und Compose-Plugin
- DNS-A- bzw. AAAA-Eintrag für den Roo-Hostnamen
- TLS-Zertifikat, Firewall und regelmäßige Sicherheitsupdates
- mindestens 2 vCPU, 4 GB RAM und ausreichend verschlüsselter Speicher für den
  Start; Kapazität nach realer Daten- und Dokumentmenge messen
- separate Backup-Zielumgebung außerhalb des Servers
- verwaltetes PostgreSQL/S3 ist gegenüber selbst betriebenen Diensten zu
  bevorzugen, wenn keine belastbare Betriebsroutine vorhanden ist

## Netzwerk und Volumes

Nur der Reverse-Proxy darf aus dem Internet erreichbar sein. PostgreSQL,
Redis, Meilisearch und S3 liegen in einem internen Compose-Netz. Keine Ports
wie `5432`, `6379`, `7700`, `9000`, `9001` oder Mail-Ports auf dem Host
veröffentlichen.

Persistente Daten werden unabhängig vom App-Release verwaltet:

- PostgreSQL-Datenbank und WAL/Backups
- Redis-Daten, sofern Queue-/Cache-Verlust nicht akzeptiert wird
- S3-Buckets `documents`, `generated`, `imports`, `exports` und `temporary`
- Meilisearch-Index als wiederaufbaubares Artefakt

Ein App-Release darf diese Daten nicht über ein neues anonymes Volume ersetzen.

## Produktionsumgebung prüfen

Vor einer Freigabe müssen folgende Eigenschaften des Produktionsprofils
nachgewiesen sein:

```bash
docker compose -f compose.production.yaml config
docker compose -f compose.production.yaml ps
```

Das Ergebnis darf keine Entwicklungskomponenten, keine Source-Bind-Mounts und
keine ungeschützten Infrastrukturports enthalten. Das App-Image muss alle
Composer- und npm-Abhängigkeiten sowie den mit `npm run build` erzeugten
`public/build`-Ordner enthalten.

