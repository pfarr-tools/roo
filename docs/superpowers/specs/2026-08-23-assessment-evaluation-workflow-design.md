# Assessment-Auswertung – Design

## Ziel

Die Aktion „Assessment auswerten“ öffnet direkt eine dauerhaft nutzbare
Arbeitsansicht. Dort können beliebig viele Scan-PDFs verarbeitet, die daraus
erkannten Booklets verwaltet, Schüler:innen manuell zugeordnet und Aufgaben
bewertet werden. Die Bereiche dürfen in beliebiger Reihenfolge geöffnet und
später erneut korrigiert werden.

## Fachmodell

Ein `AssessmentBooklet` gehört zu genau einem Assessment und repräsentiert ein
anonymes, durch einen Seitenmarker begonnenes Heft. Es besitzt eine Roo-seitig
vergebene laufende Nummer, den festen Bildausschnitt des Namensfelds, einen
Status `open` oder `discarded` und optional die zugeordnete Schüler:in. Die
Zuordnung ist nicht aus Markern ableitbar, darf geändert werden und pro
Assessment darf höchstens ein nicht verworfenes Booklet einer Schüler:in
zugeordnet sein. Ein verworfenes Booklet kann wiederhergestellt werden.

`AssessmentBookletFragment` speichert je Booklet und Aufgabe den serverseitig
erzeugten, festen Ausschnitt der Schülerarbeit sowie Seite und vertikale
Markerposition. Das Original-PDF bleibt temporär; die Fragmente und der
Namensausschnitt liegen dauerhaft auf dem privaten `documents`-Datenträger.
Der Namensausschnitt wird anhand des bekannten Templates auf Seite 1 erzeugt;
es findet keine OCR- oder Handschriftanalyse statt.

`AssessmentTaskReview` gehört zu einem Booklet und einer AssessmentTask und
speichert zusätzliche Punkte (unbeschränkt, auch negativ) samt optionaler
Begründung. `AssessmentTaskReviewItem` speichert die Punkte und optionale
Begründung jeder einzelnen Erwartungs-Ausprägung. Eine Erwartung mit
`repetitions = 3` wird dabei als drei getrennte Eingaben mit jeweils den
normalen Erwartungspunkten dargestellt.

Nach jeder Änderung wird das vorhandene `StudentAssessmentResult` der
zugeordneten Schüler:in für die Aufgabe aus den Erwartungspunkten und den
Zusatzpunkten synchronisiert. Nicht zugeordnete oder verworfene Booklets
erzeugen kein Schülerergebnis.

## Ablauf und Endpunkte

Die Editor-Aktion „Auswerten“ navigiert per GET auf die Assessment-
Auswertungsansicht. Diese liefert Assessment-Aufgaben, Schüler:innen der
Gruppe sowie alle Booklets und deren Bearbeitungsstand. Ein Upload öffnet das
bestehende PDF-Modal; die bestehende seitenweise Browser-zu-Server-Pipeline
wird wiederverwendet. Der Abschluss einer Scan-Session materialisiert die
Booklets, Namen- und Aufgabenfragmente in einer Datenbanktransaktion und
löscht danach die temporäre Session.

Die Auswertungsansicht hat drei frei wählbare Bereiche:

1. **Scans:** Booklets auflisten, weitere PDFs hochladen und Booklets als
   verworfen markieren oder wiederherstellen.
2. **Zuordnung:** pro offenem Booklet den festen Namensausschnitt anzeigen,
   eine Schüler:in aus der aktuellen Gruppe auswählen und die Auswahl später
   ändern.
3. **Aufgaben:** eine Aufgabe wählen und nur Booklets mit vorhandenem
   Fragment anzeigen. Die Reihenfolge wird bei jedem Öffnen der Aufgabe neu
   gemischt und nicht gespeichert, damit die Bewertung anonym bleibt.

Für jeden Aufgaben-/Booklet-Fall werden alle Erwartungs-Ausprägungen einzeln
angezeigt. Jede Zeile erlaubt „erfüllt“ (volle Punkte), eine geringere
Punktzahl und eine optionale Erklärung. Zusätzlich gibt es ein numerisches
Feld für freie positive oder negative Punkte und eine optionale Erklärung.
Die Speicherung erfolgt je Fall; offene Fälle dürfen übersprungen und später
fortgesetzt werden.

Alle Endpunkte prüfen die bestehende Gruppenberechtigung und die Zugehörigkeit
des Assessments. Schüler:innen werden ausschließlich über die Mitgliedschaft
in der aktuellen Unterrichtsgruppe angeboten. Fragmentbilder werden nur über
autorisierte Controller-Responses ausgeliefert.

## UI und Fehlerverhalten

Die Assessment-Seite nutzt eine frei navigierbare Tab-/Bereichsstruktur mit
Fortschrittsangaben für Booklets, Zuordnungen und Aufgabenbewertungen. Der
Upload zeigt weiterhin aktuelle Seite, erkannte Marker, laufende Fragmente und
Fehler an; bereits bestätigte Seiten müssen bei einem Fehler nicht erneut
hochgeladen werden. Nach erfolgreichem Abschluss erscheint die aktualisierte
Bookletliste.

Doppelte Marker oder inhaltlich gleiche Booklets werden nicht automatisch
zusammengeführt. Die Benutzerentscheidung bei der Zuordnung ist maßgeblich.
Unvollständige Markerpaare bleiben als Warnung sichtbar; ein daraus nicht
erzeugbares Fragment wird nicht als bewertbarer Fall angeboten.

## Tests und Abgrenzung

Feature-Tests decken Sessionabschluss, dauerhafte Materialisierung,
Autorisierung, Gruppenmitgliedschaft, eindeutige Schülerzuordnung,
Verwerfen/Wiederherstellen und Bewertungs-Synchronisierung ab. Unit-Tests
prüfen die feste Namens-/Aufgabencrop-Logik und die Expansion von
`repetitions`. Frontend-Tests prüfen freie Navigation, neue Mischreihenfolge,
Uploadstatus und die einzelnen Erwartungszeilen.

Nicht Bestandteil sind OCR, automatische Handschriftbewertung, automatische
Marker-Deduplizierung, Notenvorschläge sowie ein erzwungener Schritt-für-Schritt-
Abschluss.
