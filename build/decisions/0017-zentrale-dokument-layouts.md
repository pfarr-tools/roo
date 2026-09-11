# ADR 0017: Zentrale Dokument-Layouts

Status: Accepted

## Kontext

DOCX- und ODT-Exporte für LSE-Arbeitsblätter, LSE-Ergebnisberichte und
Elternbriefe benötigen mehrere fachliche Layoutvarianten. Die Auswahl darf
nicht als voneinander abweichende Template-Implementierung je Ausgabeformat
entstehen.

## Entscheidung

Dokumente, die eine Layoutwahl unterstützen, tragen ein `DocumentLayout` über
die gemeinsame Basisklasse `LayoutDocument`. Ein
`DocumentLayoutProfile` bündelt Schriftfamilien, Schriftgrößen,
Seitenrahmen, Kopfzeilen und weitere wiederkehrende Stilparameter.

Die Ausgabeformate DOCX und ODT verwenden dasselbe Profil. Aktuell sind
`primary-school-lower-secondary` (Grundschule, Unterstufe) und `secondary`
(Sekundarstufe) verfügbar. Die Sekundarstufenvariante verwendet Atkinson
Hyperlegible Next und erzeugt keine Aufgabenbox mit einer Kennzeichnung wie
„M 1“.

Die fachlichen Dokumentklassen bleiben getrennt; nur die Layoutparameter und
die Auswahl werden zentral geteilt. Bestehende Links ohne neue Auswahl bleiben
auf dem bisherigen Layout.

## Konsequenzen

- Neue Layouts werden einmal im Enum und Profil ergänzt.
- Alle DOCX/ODT-Dialoge übergeben denselben Layoutwert.
- Neue Profilparameter benötigen gemeinsame Rendering-Tests für DOCX und ODT.
- Visuelle Regressionen müssen durch Rendern der erzeugten Dokumente geprüft
  werden; XML- oder Archivprüfungen allein sind nicht ausreichend.
