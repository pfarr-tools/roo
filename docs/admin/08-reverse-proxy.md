# Reverse-Proxy mit Caddy

Das Produktionsprofil veröffentlicht den Roo-Webdienst standardmäßig nur auf
`127.0.0.1:8080`. Für den öffentlichen Betrieb wird ein vorgeschalteter
Reverse-Proxy benötigt, der TLS terminiert und Anfragen an diesen lokalen Port
weiterleitet.

Ein minimales Beispiel für Caddy befindet sich unter
[examples/Caddyfile](examples/Caddyfile). Es setzt voraus, dass Caddy direkt
auf dem Linux-Host und nicht als zusätzlicher Compose-Dienst läuft.

## Voraussetzungen

- Ein DNS-A- bzw. AAAA-Eintrag zeigt auf den Server.
- Die Ports 80 und 443 sind für Caddy erreichbar.
- Roo läuft mit `APP_URL=https://roo.example.org`.
- Der Roo-Webdienst läuft auf dem Host nur auf `127.0.0.1:8080`.

Caddy stellt mit seiner Standardkonfiguration automatisch ein TLS-Zertifikat
aus und erneuert es. Dafür müssen der DNS-Eintrag sowie die HTTP- und
HTTPS-Erreichbarkeit vor dem ersten Start korrekt eingerichtet sein.

## Konfiguration installieren

Beispielhaft:

```bash
sudo install -m 0644 docs/admin/examples/Caddyfile /etc/caddy/Caddyfile
sudoedit /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

In der Datei muss `roo.example.org` durch den tatsächlichen Hostnamen ersetzt
werden. Nach dem Reload prüfen:

```bash
curl --fail --head https://roo.example.org
docker compose -f compose.production.yaml ps
```

Der direkte Port `127.0.0.1:8080` bleibt absichtlich lokal gebunden. Die
Infrastrukturports von PostgreSQL, Redis, Meilisearch und Object Storage
werden nicht durch den Reverse-Proxy veröffentlicht.

## Hinweise zu Weiterleitungen und Sicherheit

Caddy setzt bei `reverse_proxy` die üblichen `X-Forwarded-*`-Header. Diese
Header dürfen nicht von einem vorgeschalteten, nicht vertrauenswürdigen Proxy
ungeprüft übernommen werden. Wenn weitere Proxy-Schichten hinzukommen, muss
deren Vertrauensbereich in Caddy und Laravel ausdrücklich konfiguriert werden.

`APP_URL` muss das öffentliche HTTPS-Schema verwenden, damit generierte URLs,
Weiterleitungen und sichere Cookies korrekt funktionieren. HSTS sollte erst
aktiviert werden, wenn HTTPS dauerhaft und vollständig funktionsfähig ist.
