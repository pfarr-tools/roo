# Freitext mit Bildern und optionalem Lesetext

## Ziel

Das AssessmentTask-Format `free_text` wird um Bilder und einen optionalen Lesetext erweitert. Die bisher getrennten Formate `free_text_images` und `reading_text` werden entfernt, da es noch keine gespeicherten Aufgaben dieser Typen gibt.

## Fachliches Verhalten

- `free_text` bleibt der einzige Freitext-Typ.
- Bilder können im Editor per Upload oder aus der Bildbibliothek hinzugefügt, sortiert und entfernt werden.
- Die vorhandene Bildauswahl-UI wird wiederverwendet.
- Die Bildbreite wird über eine Einstellung in Zentimetern festgelegt und für alle Bilder verwendet.
- `Optionaler Lesetext` ist ein optionales Textfeld. Er ist unabhängig von Erwartungen und Fragen.
- Die bisherigen Zeilen- und Lineatur-Einstellungen des Freitexts bleiben erhalten.
- `free_text`-Bilder und der Lesetext erzeugen keine automatischen Erwartungen.
- `free_text_images` und `reading_text` werden aus Enum, UI-Auswahl, Validierung, Übersetzungen und Exportverzweigungen entfernt.

## Datenfluss

Die Bilddaten werden über die bestehende `AssessmentTaskImage`-Beziehung gespeichert. Die Referenzen bleiben in `images` im Request; Bildpfade werden ausschließlich über den bestehenden Filesystem-/Resource-Referenz-Pfad aufgelöst. Der Lesetext wird als `content.optional_reading_text` gespeichert.

## Export

Der Freitext-Export wird in dieser Reihenfolge ausgegeben:

1. Aufgabentext
2. optional die Bilder, in maximal drei gleichartigen Spalten pro Zeile
3. optional der Lesetext
4. die Antwortzeilen mit optionaler Lineatur

Der Lesetext verwendet Atkinson Hyperlegible Next, 14 pt und normale Schrift. Die Bilder verwenden die im Task gespeicherte Bildbreite; die Tabellenbreite wird bei bis zu drei Bildern pro Zeile entsprechend verteilt, ohne die maximale Inhaltsbreite zu überschreiten.

## Evaluation

Die Freitext-Evaluation bleibt unverändert: Bilder und Lesetext werden nur als Aufgabeninhalt dargestellt. Es entstehen weder zusätzliche Bewertungspunkte noch neue Checkboxen.

## Fehlerbehandlung und Grenzen

- Leere Bilder werden nicht persistiert.
- Ein leerer optionaler Lesetext wird wie nicht vorhanden behandelt.
- Nicht auflösbare Bildreferenzen werden beim Export übersprungen; der restliche Task wird weiterhin gerendert.
- Es wird keine Datenmigration für die entfernten Typen benötigt, da laut Anforderung noch keine solchen Aufgaben existieren.

## Tests

- Task-Editor: `free_text` zeigt Bilder/Lesetext, sendet sie korrekt und bietet die entfernten Typen nicht mehr an.
- Backend: Freitext-Bilder und optionaler Lesetext werden validiert und gespeichert.
- Export: Lesetext-Stil, Reihenfolge und maximal drei Bildspalten werden im erzeugten ODT geprüft.
- Regression: Die bestehenden Freitext-, Bild- und Assessment-Exporttests bleiben grün.
