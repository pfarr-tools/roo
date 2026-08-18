# Updates und Rollback

Updates und Rollbacks werden ausschließlich über die Produktionsbefehle von
`./roo` ausgeführt. Vor jedem Update sind ein geprüftes Datenbank- und
Object-Storage-Backup, ein Wartungsfenster und ein bekannter vorheriger
Release-Stand erforderlich.

## Update ausführen

```bash
cd /opt/roo/current
./roo prod update \
  --backup-confirmed \
  --ref <freigegebener-tag-oder-commit>
```

`--backup-confirmed` ist verpflichtend. Ohne `--ref` verwendet der Befehl den
bereits ausgecheckten Release-Stand:

```bash
./roo prod update --backup-confirmed
```

Das Updateskript fetcht bei gesetztem `--ref` die Tags, wechselt auf den
angegebenen Stand, aktiviert den Wartungsmodus, baut das Produktionsimage,
führt Migrationen und Cache-Schritte aus, beendet alte Horizon-Worker,
aktualisiert Assets und startet die Produktionsdienste neu.

## Rollback

Ein Rollback wird mit demselben Updatebefehl auf den vorherigen, kompatiblen
Release-Stand ausgeführt:

```bash
./roo prod update \
  --backup-confirmed \
  --ref <vorheriger-kompatibler-tag-oder-commit>
```

Das ist nur sicher, wenn die Datenbankmigrationen die vorherige Anwendung noch
unterstützen. Eine bereits ausgeführte inkompatible oder destruktive Migration
nicht spontan zurückdrehen. In diesem Fall die Wiederherstellung des Backups
nach dem [Störungsbehebungsverfahren](06-stoerungsbehebung.md) organisieren.

## Nachprüfung

```bash
./roo prod status
./roo prod logs app horizon scheduler web
```

Danach Anmeldung, Mandantentrennung, private Dateien, Suche, Queue, Mail und
die zentralen Fachabläufe prüfen. Weitere Hinweise stehen unter
[Produktionsskripte verwenden](07-produktionsskripte.md).

