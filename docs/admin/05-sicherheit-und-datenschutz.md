# Sicherheit und Datenschutz

Roo verarbeitet Schülerdaten, Beobachtungen und Bewertungen. Sie sind
besonders schützenswert und dürfen nicht wie gewöhnliche Webinhalte behandelt
werden.

## Mindestmaßnahmen

- `APP_ENV=production` und `APP_DEBUG=false`.
- Nur HTTPS; HTTP ausschließlich zur TLS-Weiterleitung.
- Datenbank, Redis, Meilisearch und S3 nicht öffentlich veröffentlichen.
- Produktionsgeheimnisse nur im Secret-Manager oder in einer auf `0600`
  beschränkten `.env` speichern.
- S3-Buckets privat halten; keine erratbaren öffentlichen Dateipfade nutzen.
- SSH mit Schlüsseln, eingeschränkten Accounts und Firewall absichern.
- Admin- und Horizon-Zugänge mit starken individuellen Konten schützen.
- Logs, Backups und Monitoringdaten auf Schülerdaten und unnötige
  personenbezogene Inhalte prüfen.
- Schülerdaten nicht in Meilisearch, KI-Prompts oder Fehlermeldungen senden.
- Aufbewahrungs- und Löschfristen für Daten, Dateien, Logs und Backups
  schriftlich festlegen.

## Zugriffsprüfung

Nach Erstinstallation und jedem relevanten Update testen:

1. Benutzer A sieht die erlaubten Daten seiner Organisation.
2. Benutzer A kann keine Schule, Gruppe, Schülerdaten oder Dateien einer
   fremden Organisation aufrufen.
3. Abgemeldete Personen erhalten keine privaten Dateien.
4. Ein abgelaufener oder widerrufener Download-Link funktioniert nicht mehr.
5. Suchergebnisse enthalten keine Schüler:innen, Beobachtungen oder
   Bewertungen.

## Vorfälle

Bei Verdacht auf kompromittierte Zugangsdaten sofort betroffene Tokens,
Passwörter und S3-Schlüssel sperren bzw. rotieren, Zugriffe und Logs sichern,
keine Logs nachträglich überschreiben und die für Datenschutz und Sicherheit
verantwortlichen Personen informieren. `APP_KEY` nicht ohne abgestimmte
Schlüsselrotation ändern.

