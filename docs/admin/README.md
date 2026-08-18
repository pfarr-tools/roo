# Roo – Administrationshandbuch

Dieses Handbuch beschreibt den Betrieb einer Roo-Installation auf einem
Produktionssystem. Es richtet sich an Personen, die den Server, die Container,
Backups und Updates verantworten.

## Dokumente

- [Voraussetzungen und Zielarchitektur](01-voraussetzungen-und-architektur.md)
- [Erstinstallation](02-erstinstallation.md)
- [Updates und Rollback](03-updates-und-rollback.md)
- [Regelbetrieb, Backups und Monitoring](04-betrieb-backups-monitoring.md)
- [Sicherheit und Datenschutz](05-sicherheit-und-datenschutz.md)
- [Störungsbehebung](06-stoerungsbehebung.md)
- [Produktionsskripte verwenden](07-produktionsskripte.md)
- [Reverse-Proxy mit Caddy](08-reverse-proxy.md)

## Beispiele

- [Caddyfile für einen Reverse-Proxy](examples/Caddyfile)

## Wichtiger Hinweis

Das im Repository enthaltene `compose.yaml` ist die lokale
Entwicklungsumgebung. Es verwendet unter anderem Quellcode-Bind-Mounts, einen
Vite-Entwicklungsserver, Mailpit und veröffentlichte Infrastrukturports. Diese
Datei darf nicht unverändert für das Internet eingesetzt werden.

Das Repository enthält dafür jetzt `compose.production.yaml` und einen
Produktions-Build im `Dockerfile`. Das Profil verwendet ein gebackenes Image mit
`public/build`, veröffentlicht nur den Webdienst auf `127.0.0.1:8080` und hält
PostgreSQL, Redis, Meilisearch sowie Object Storage im internen Compose-Netz.
Ein vorgeschalteter TLS-Reverse-Proxy muss den Webdienst erreichen können.
Alternativ können die internen Dienste durch verwaltete Dienste ersetzt werden;
dann ist das Compose-Profil entsprechend anzupassen.

## Grundregel

Produktionsbefehle werden auf dem Server aus dem Release-Verzeichnis über
`./roo prod` ausgeführt. Der Wrapper verwendet dafür zentral
`compose.production.yaml`; das lokale `compose.yaml` bleibt der
Entwicklungsumgebung vorbehalten.
