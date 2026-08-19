# ADR 0012: Liedfassungen und Gruppenliederbücher

## Status

Akzeptiert

## Kontext

Ein Lied kann in mehreren Fassungen mit unterschiedlichen Rechten, Texten und
Liedblättern verwendet werden. Die konkrete Verwendung gehört zur jeweiligen
Unterrichtsgruppe, Einheit, Stunde oder Phase und darf nicht nur über freie
Textfelder abgebildet werden.

## Entscheidung

Roo speichert `Song` und `SongVersion` getrennt. Rechte und die Erlaubnis für
Textexporte liegen an der Fassung. Ein vorhandenes A5-PDF wird als privates
`SongSheet` mit stabilem Storage-Schlüssel gespeichert.

Zuordnungen verwenden eigene Tabellen für Einheiten, Stunden und Phasen. Die
gemeinsame Ressourcenbibliothek führt Liedfassungen als eigenen Typ und nutzt
dieselben mandantengeschützten Zuordnungsrouten wie Dateien, Webressourcen und
Materialbestandteile.

Jede Unterrichtsgruppe besitzt höchstens ein `GroupSongbook`. Beim ersten
Einsatz einer Fassung wird ein Eintrag mit der nächsten gruppenspezifischen
Nummer angelegt. Eine Titelseite gehört zum Gruppenliederbuch und wird privat
über Laravel Filesystem gespeichert.

## Konsequenzen

- Vorlagenänderungen verändern historische Zuordnungen nicht.
- Rechte können vor einem späteren Druck-/Exportjob geprüft werden.
- Globale Lieder und organisationsbezogene Lieder können gemeinsam sichtbar
  sein, ohne fremde organisationsbezogene Daten preiszugeben.
- PDF-Zusammenstellung bleibt eine nachgelagerte, queue-fähige Exportfunktion.
