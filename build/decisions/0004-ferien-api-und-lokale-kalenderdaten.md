# ADR 0004: Ferien-API mit lokalen Kalenderüberschreibungen

- Status: Accepted
- Datum: 2026-08-17

## Entscheidung

Roo importiert Ferienzeiträume standardmäßig aus der Ferien-API
(`https://ferien-api.de`) für Baden-Württemberg (`BW`). Der Import erfolgt
pro Schuljahr und berücksichtigt beide Kalenderjahre, wenn ein Schuljahr über
den Jahreswechsel reicht.

Importierte Zeiträume erhalten eine `DataSource` und den externen API-Slug als
stabile Kennung. Lokale Ferienzeiten und einzelne Kalenderausnahmen werden
relational gespeichert und können zusätzlich erfasst werden. Ein erneuter
Import identifiziert nur seine eigenen API-Datensätze und überschreibt keine
lokalen Einträge.

Die API liefert UTC-Zeitstempel. Roo wandelt sie in die Zeitzone des
Schuljahres (Standard `Europe/Berlin`) um und speichert Kalendertage.

## Konsequenzen

- Die API-Daten bleiben nachvollziehbar und erneut importierbar.
- Schulen können bewegliche Ferientage, Schulfeste und andere Abweichungen
  selbst ergänzen.
- API-Daten sind ohne Gewähr; die lokale Erfassung bleibt die fachliche
  Korrekturmöglichkeit.
