# Schulische eigene Prozesskompetenzen und Beobachtungsskalen

## Ziel

Eine Schule kann eigene Prozesskompetenzen definieren und eine gemeinsame
Anzahl von Beobachtungsstufen festlegen. Alle Unterrichtsgruppen dieser Schule
mit dem Bewertungsmodus „Beobachtungsskalen“ verwenden diese Kompetenzen und
die daraus erzeugte Skala in Beobachtungen und in der abschließenden
Schüler:innenbewertung.

Die schulischen Kompetenzen sind kein Ersatz für importierte
Bildungsplan-Prozesskompetenzen. Sie sind ein eigener, schulbezogener
Kompetenzbestand ohne Bildungsplan-Nummer.

## Entscheidungen

- Die Definition liegt an der Schule, nicht an der Unterrichtsgruppe.
- Jede Schule besitzt eine einheitliche Anzahl von Beobachtungsstufen.
- Die Stufen werden automatisch mit `+`, `++`, `+++` usw. beschriftet.
- `ne` ist kein zusätzliches Intervall, sondern ein separater Status für
  „nicht erkennbar“ bzw. „nicht erhoben“.
- Alle Gruppen der Schule mit Bewertungsmodus `observation_scales` erhalten
  die schulischen Kompetenzen automatisch; eine manuelle Gruppen-Zuordnung ist
  nicht erforderlich.
- Eigene Kompetenzen haben einen Text, eine Position und einen aktiven bzw.
  inaktiven Zustand, aber keine Bildungsplan-Nummer.
- Änderungen an Kompetenztext oder Stufenanzahl wirken sofort auf laufende und
  neue Bewertungen.
- Bestätigte Bewertungen bleiben unverändert und speichern einen Snapshot der
  verwendeten Kompetenz- und Skaleninformationen.
- Verwendete Kompetenzen werden deaktiviert statt gelöscht.

## Fachliches Modell

### Schule

Die Schule erhält eine Einstellung für die Anzahl der Beobachtungsstufen. Die
Skala wird nicht als Liste einzelner Stufen gespeichert, sondern aus dieser
Zahl erzeugt. Die Zahl muss mindestens zwei gültige Stufen erlauben und wird
serverseitig auf einen kleinen, fachlich sinnvollen Höchstwert begrenzt.

### Eigene Prozesskompetenz

Eine schulische eigene Prozesskompetenz enthält:

- Schule
- Kompetenztext
- Position innerhalb der schulischen Liste
- aktiv/inaktiv

Sie darf nicht mit einer importierten `EducationPlanCompetency` vermischt
werden. Dadurch bleiben Herkunft, Berechtigungen und spätere Importvorgänge
eindeutig.

### Beobachtung und Endbewertung

Beobachtungsnachweise verweisen auf die schulische eigene Prozesskompetenz und
speichern die gewählte Stufe bzw. den Sonderstatus `ne`. Die vorhandene
Unterstützung für Bildungsplan-Prozesskompetenzen bleibt erhalten; die beiden
Referenzarten werden getrennt und serverseitig gegenseitig ausgeschlossen.

Eine noch nicht bestätigte Endbewertung liest die aktuelle Schuldefinition.
Bei Bestätigung werden mindestens folgende Werte in der Bewertung oder einem
zugehörigen Bewertungsdatensatz festgehalten:

- Kompetenztext
- Position
- Anzahl der Stufen
- gewählte Stufe oder `ne`

Damit bleiben Zeugnis- und Bewertungsdaten auch nach späteren Schuländerungen
reproduzierbar.

## Änderungsverhalten

Eine Erhöhung der Stufenanzahl ist sofort verfügbar. Wird die Stufenanzahl
verringert und ein laufender Entwurf enthält eine nicht mehr gültige Stufe,
wird die betroffene Bewertung als zu korrigieren markiert. Roo deutet den Wert
nicht automatisch um.

Eine Änderung des Kompetenztexts aktualisiert laufende Entwürfe sofort. Eine
bereits bestätigte Bewertung zeigt weiterhin ihren gespeicherten Textsnapshot.
Das Deaktivieren blendet eine Kompetenz aus neuen Eingaben aus, erhält aber
bestehende Nachweise und bestätigte Bewertungen.

## Datenfluss

```text
School
  │
  ├── observation interval count
  └── CustomProcessCompetence
          │
          ├── lesson observation evidence
          │
          └── StudentEvaluation (draft: live definition)
                              │
                              └── confirmed: immutable snapshot
```

Die Sichtbarkeit wird über den Bewertungsmodus der Unterrichtsgruppe bestimmt:

```text
TeachingGroup.grading_model = observation_scales
        │
        └── school.custom_process_competences (active)
```

## Benutzeroberfläche

Unter der Schule gibt es einen Verwaltungsbereich für „Eigene
Prozesskompetenzen und Beobachtungsskala“:

- Anzahl der Beobachtungsstufen bearbeiten
- daraus resultierende `+`-Skala mit `ne` anzeigen
- Kompetenztexte anlegen, bearbeiten, sortieren und deaktivieren
- Warnung vor Auswirkungen auf laufende Entwürfe anzeigen
- bestätigte Bewertungen ausdrücklich als unverändert erklären

In Gruppen mit „Beobachtungsskalen“ erscheinen die aktiven Kompetenzen in der
Beobachtungserfassung und Endbewertung. Andere Bewertungsmodi erhalten dort
keine zusätzlichen schulischen Prozesskompetenzen.

## Validierung und Autorisierung

- Nur berechtigte Benutzer:innen der Schule dürfen Definitionen ändern.
- Kompetenztexte sind erforderlich und begrenzt.
- Die Stufenanzahl ist eine gültige Ganzzahl im festgelegten Bereich.
- Beobachtungs- und Bewertungswerte müssen zur aktuellen Stufenanzahl oder
  `ne` gehören.
- Eine Kompetenz fremder Schulen darf nicht verwendet werden.
- Alte bzw. bestätigte Datensätze bleiben lesbar, auch wenn die Definition
  deaktiviert wurde.
- Schüler:innendaten erscheinen nicht in Logs und nicht in öffentlichen URLs.

## Vertikaler Implementierungsschnitt

1. Schulische Einstellung und `CustomProcessCompetence` mit reversiblen
   Migrationen, Modelbeziehungen und Policy.
2. Schulverwaltungsoberfläche für Skalenanzahl und Kompetenztexte einschließlich
   Sortierung, Deaktivierung und deutscher Validierungsfehler.
3. Erweiterung der Beobachtungsnachweise um die getrennte Referenz auf eigene
   Prozesskompetenzen und serverseitige Bewertungsmodus-/Schulprüfung.
4. Anzeige und Bearbeitung in der Endbewertung; bei Bestätigung Snapshot der
   Definition und der gewählten Stufe.
5. Randfallbehandlung bei einer Verringerung der Stufenanzahl sowie fokussierte
   Backend-, Autorisierungs- und Frontend-Tests.

## Abnahmekriterien

- Eine Schule kann eine gemeinsame Stufenanzahl und eigene Prozesskompetenzen
  verwalten.
- Die Skala wird korrekt als `+` bis zur konfigurierten Stufenzahl plus `ne`
  dargestellt.
- Alle passenden Gruppen sehen die aktiven Kompetenzen ohne manuelle
  Gruppen-Zuordnung.
- Gruppen mit anderem Bewertungsmodus sehen sie nicht als Beobachtungsskalen.
- Bildungsplan-Prozesskompetenzen und schulische eigene Kompetenzen bleiben
  getrennte Referenzarten.
- Änderungen wirken sofort auf laufende Entwürfe.
- Eine ungültig gewordene laufende Stufe wird zur Korrektur markiert und nicht
  automatisch umgerechnet.
- Bestätigte Bewertungen behalten Kompetenztext, Stufenanzahl und Wert als
  unveränderlichen Snapshot.
- Deaktivierte Kompetenzen sind nicht neu auswählbar, bleiben aber historisch
  sichtbar.
- Tests prüfen Trennung der Benutzerkonten, Bewertungsmodus, Deaktivierung,
  Stufenänderungen, Snapshot-Verhalten und `ne`.
