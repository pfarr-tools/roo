# Assessment-PDF-Auswertung – Design

## Ziel

Eine bestehende Lernstandserhebung kann über eine neue Aktion „Auswerten“ ein
gescanntes PDF hochladen. Roo erkennt die vorhandenen ROO-Datamatrix-Codes,
gruppiert Seiten anhand des Seiten-Startmarkers in anonyme Booklets und zeigt
die gefundenen Booklets und Aufgabenmarker in einer eigenen
`Assessment/Assess`-Ansicht an.

Die erste Version ordnet Booklets noch keinen Schüler:innen zu und speichert
keine Scanergebnisse dauerhaft. Die browserseitige Analyse lädt nur erkannte
temporäre Antwortfragmente hoch; das Original-PDF verlässt den Browser nicht.

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
ausschließlich PDF-Dateien als Auswahl. Die bevorzugte Browserpipeline liest
die lokale Datei und sendet danach nur temporäre Fragmente per FormData an
geschützte Assessment-Endpunkte. Für die serverseitige Fallback-Analyse wird
die PDF-Datei weiterhin direkt an den bisherigen Upload-Endpunkt gesendet.

Der Controller prüft die bestehende `update`-Autorisierung der
Unterrichtsgruppe und die Zugehörigkeit des Assessments zur Gruppe. Nach
erfolgreicher Analyse rendert er `Assessment/Assess` mit den Scanergebnissen.

Die Ergebnisansicht enthält eine Rücksprungaktion zum Assessment-Editor und
zeigt die Booklets sowie deren Marker in stabiler Seitenreihenfolge.

### PDF- und DataMatrix-Analyse

Die bevorzugte Analyse erfolgt im Browser, damit die Lehrkraft den Fortschritt
auch bei großen PDFs unmittelbar sieht. Die bestehende serverseitige Analyse
bleibt als Fallback für inkompatible Browser oder nicht zuverlässig erkannte
DataMatrix-Codes erhalten.

Die Browserpipeline arbeitet seitenweise und hält niemals das gesamte gerenderte
Dokument im Speicher:

1. `pdfjs-dist` öffnet das lokale PDF und rendert eine Seite mit einer
   festgelegten Auflösung in ein Canvas.
2. Ein Web Worker sucht mit einem browserfähigen DataMatrix-Decoder nach
   `ROO1`-Markern und meldet die gefundenen Payloads samt Bildkoordinaten an die
   Vue-Oberfläche.
3. Die bestehende Markerparser-Logik wird in eine äquivalente, gemeinsam
   getestete Frontendfunktion übertragen. Seitenmarker beginnen Booklets;
   START-/END-Marker begrenzen Antwortbereiche.
4. Aus jedem abgeschlossenen Aufgabenbereich wird ein PNG-Fragment erzeugt.
   Das Fragment wird zusammen mit Bookletnummer, Aufgaben-ID, Seiten- und
   Positionsdaten einzeln per `FormData` an den geschützten temporären
   Fragment-Endpunkt hochgeladen.
5. Erst nach bestätigtem Upload wird die nächste Seite beziehungsweise das
   nächste Fragment verarbeitet. Die Oberfläche zeigt Phase, aktuelle Seite,
   erkannte Booklets, Fragmentstatus und Fehler an.

Der Browserdecoder muss mindestens DataMatrix-Payload und Eck-/Positionsdaten
liefern. Falls die Erkennung mehrerer Codes auf realen Roo-Scans nicht stabil
genug ist, bleibt die Seitenanalyse serverseitig und nur die Fragment- und
Uploadpipeline wird wiederverwendet.

Die Antwortfragmente werden serverseitig nur temporär unter einer zufälligen
Scan-Session-ID gespeichert. Jeder Upload wird autorisiert, auf die Assessment-
Gruppe begrenzt und mit einer Prüfsumme bestätigt. Ein expliziter Abschluss
beendet die Session; unvollständige Sessions laufen automatisch ab.

Für die Verarbeitung im Worker werden große Bildpuffer als transferierbare
Objekte übergeben. Es gibt höchstens einen aktiven Seiten- und einen aktiven
Fragmentupload, damit Speicherbedarf und Serverlast begrenzt bleiben.

### Serverseitige Fallback-Analyse

Die serverseitige Fallback-Analyse bleibt durch einen zentralen
`AssessmentPdfScanner` gekapselt. Der Scanner arbeitet pro PDF-Seite:

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

Bei der Fallback-Analyse bleiben PDF und gerenderte Seiten ausschließlich
temporär und werden nach der Analyse in einem `finally`-Block entfernt. Die
Uploadvalidierung beschränkt sich auf PDF-MIME-Typ und maximal 50 MB.

Bei der Browserpipeline wird das lokale PDF nicht hochgeladen. Temporäre
Fragmentuploads erhalten eine kurze Ablaufzeit und werden nach Abschluss oder
Abbruch der Scan-Session gelöscht. Decoder-, Render- und Uploadfehler werden
pro Seite oder Fragment angezeigt und können erneut versucht werden, ohne
bereits bestätigte Uploads zu wiederholen. Interne Prozessausgaben und
Dateipfade werden nicht an die Oberfläche oder in Logs mit personenbezogenen
Daten übernommen.

## UI

Der Editor-Button „Auswerten“ erscheint neben dem bestehenden ODT-Download.
Das Modal enthält zunächst:

- Überschrift „Assessment auswerten“
- PDF-Dateifeld
- Hinweis, dass zunächst nur Booklets und ROO-Marker erkannt werden
- Abbrechen und „PDF auswerten“

Während der Browseranalyse zeigt ein Scanpanel:

- aktuelle Phase und Seite von Seiten insgesamt
- Anzahl erkannter Booklets und Marker
- Anzahl hochgeladener, ausstehender und fehlgeschlagener Fragmente
- einen laufenden, barrierefrei angekündigten Status
- „Erneut versuchen“ für einzelne fehlgeschlagene Fragmente
- „Abbrechen“ mit Aufräumen der temporären Scan-Session

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
- Frontend-Unit-Tests für Markerparsing, Bookletgruppierung und
  Fragmentgrenzen.
- Frontend-Worker-Test mit einer kleinen PNG-Testseite und kontrollierten
  Decoderergebnissen.
- Feature-Tests für autorisierte temporäre Fragmentuploads, Prüfsummen,
  Sessionablauf und fremde Assessments.

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
- serverseitige OCR- oder Handschriftanalyse der hochgeladenen Fragmente
