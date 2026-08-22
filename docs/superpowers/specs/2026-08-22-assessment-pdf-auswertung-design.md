# Assessment-PDF-Auswertung – Design

## Ziel

Eine bestehende Lernstandserhebung kann über eine neue Aktion „Auswerten“ ein
gescanntes PDF hochladen. Roo erkennt die vorhandenen ROO-Datamatrix-Codes,
gruppiert Seiten anhand des Seiten-Startmarkers in anonyme Booklets und zeigt
die gefundenen Booklets und Aufgabenmarker in einer eigenen
`Assessment/Assess`-Ansicht an.

Die erste Version ordnet Booklets noch keinen Schüler:innen zu und speichert
keine Scanergebnisse dauerhaft.

## Bestehende Marker-Verträge

Die aktuelle Assessment-Ausgabe erzeugt folgende Payloads:

- Seitenmarker: `ROO1|A=<assessment-id>|L=<level>|K=PAGE`
- Aufgabenbeginn: `ROO1|T=<task-id>|K=START`
- Aufgabenende: `ROO1|T=<task-id>|K=END`

Der Seitenmarker steht nur auf der ersten Seite des erzeugten Dokuments und
beginnt deshalb ein neues anonymes Booklet. Aufgabenmarker werden der jeweils
zuletzt begonnenen Booklet-Gruppe zugeordnet.

## Architektur

### Upload und Navigation

Der bestehende Assessment-Editor erhält bei einer vorhandenen Assessment ein
Toolbar-Element „Auswerten“. Es öffnet ein vorhandenes Roo-Modal-Muster mit
ausschließlich PDF-Dateien als Auswahl. Der Upload wird mit Inertia/FormData an
einen geschützten Assessment-Endpunkt gesendet.

Der Controller prüft die bestehende `update`-Autorisierung der
Unterrichtsgruppe und die Zugehörigkeit des Assessments zur Gruppe. Nach
erfolgreicher Analyse rendert er `Assessment/Assess` mit den Scanergebnissen.

Die Ergebnisansicht enthält eine Rücksprungaktion zum Assessment-Editor und
zeigt die Booklets sowie deren Marker in stabiler Seitenreihenfolge.

### PDF- und DataMatrix-Analyse

Die Analyse wird durch einen zentralen `AssessmentPdfScanner` gekapselt. Der
Scanner arbeitet pro PDF-Seite:

1. `pdftoppm` rendert die Seite mit definierter Auflösung in ein temporäres
   PNG.
2. Ein `DataMatrixDecoder`-Adapter ruft den Docker-Systemdecoder
   (`dmtxread` aus libdmtx) auf.
3. Der Decoder liefert Payload und Bildkoordinaten der gefundenen Codes.
4. Der Scanner wandelt die Pixelkoordinate in eine vertikale Position in
   Zentimetern um und ordnet den Marker dem aktuellen Booklet zu.

Der Decoder ist eine eigene Schnittstelle, damit die Bildanalyse getestet und
später ohne Änderung der Domänenlogik gegen eine alternative libdmtx-
Implementierung ausgetauscht werden kann.

Die interne Ergebnisstruktur enthält mindestens:

```php
[
    'booklets' => [
        [
            'number' => 1,
            'start_page' => 1,
            'markers' => [
                [
                    'page' => 1,
                    'y_cm' => 2.4,
                    'kind' => 'PAGE',
                    'task_id' => null,
                    'payload' => 'ROO1|A=42|L=M|K=PAGE',
                ],
            ],
        ],
    ],
    'warnings' => [],
]
```

Nicht-Roo-Payloads werden ignoriert. Ein Aufgabenmarker vor dem ersten
Seitenmarker, unbekannte Markerformen und nicht geschlossene Aufgabenpaare
werden als Warnungen ausgegeben, ohne den gesamten Scan abzubrechen.

### Temporäre Dateien und Fehler

PDF und gerenderte Seiten bleiben ausschließlich temporär und werden nach der
Analyse in einem `finally`-Block entfernt. Die Uploadvalidierung beschränkt
sich auf PDF-MIME-Typ und maximal 50 MB. Decoder- oder
Renderfehler werden als deutsche Formularfehlermeldung zurückgegeben; interne
Prozessausgaben und Dateipfade werden nicht an die Oberfläche oder in Logs mit
personenbezogenen Daten übernommen.

## UI

Der Editor-Button „Auswerten“ erscheint neben dem bestehenden ODT-Download.
Das Modal enthält:

- Überschrift „Assessment auswerten“
- PDF-Dateifeld
- Hinweis, dass zunächst nur Booklets und ROO-Marker erkannt werden
- Abbrechen und „PDF auswerten“

`Assessment/Assess` zeigt:

- Anzahl erkannter Booklets
- je Booklet Startseite und anonymisierte Nummer
- je Marker Seite, `y` in cm, Typ und Aufgaben-ID
- Warnungen für unvollständige oder außerhalb eines Booklets liegende Marker

Alle sichtbaren Texte werden über die zentrale deutsche Frontend-Lokalisierung
geliefert.

## Tests

- Unit-Test für das Parsen gültiger und ungültiger ROO-Payloads.
- Unit-Test für die Gruppierung von Seiten- und Aufgabenmarkern inklusive
  Warnungen.
- Unit-Test für die Pixel-zu-Zentimeter-Umrechnung.
- Feature-Test für die geschützte Ergebnisroute und die Assessment-Prüfung.
- Feature-Test für PDF-Upload mit erfolgreicher Scannerantwort.
- Feature-Test für abgewiesene Nicht-PDF-Dateien und fremde Assessments.
- Frontend-Build und `git diff --check`.

Der reale Decoder wird über einen Fake-Adapter im Anwendungstest ersetzt; ein
kleiner separater Integrationsnachweis im Docker-Container prüft den
Systemdecoder mit einem erzeugten Testbild, ohne die Entwicklungsdatenbank zu
verändern.

## Nicht Bestandteil dieses Schnitts

- Schüler-ID oder Schülername im Marker
- dauerhafte Scan- und Booklet-Tabellen
- automatische Punkteerkennung oder Notenberechnung
- Handschrift-/OCR-Auswertung
- Bearbeitung oder Korrektur erkannter Marker in der Ergebnisansicht
