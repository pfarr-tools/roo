# Elternbrief und öffentliche Einheit – Design

**Status:** Entwurf zur Nutzerprüfung  
**Datum:** 5. September 2026

## Ziel

Jede konkrete Unterrichtseinheit einer konkreten Unterrichtsgruppe erhält
eine dauerhaft erreichbare, signierte öffentliche Seite. Die Lehrkraft kann
aus dem Einheiteneditor einen Elternbrief als DOCX oder ODT erzeugen. Beide
Darstellungen verwenden dieselben aktuellen Einheitsdaten und können
freigegebene Phasenmaterialien verlinken.

Die öffentliche Seite ist kein neuer Veröffentlichungsworkflow: Ihre URL ist
dauerhaft signiert und wird nicht im Adminmenü verlinkt. Sichtbarkeit einzelner
Materialien wird unabhängig davon pro konkreter Materialzuordnung gesteuert.

## Schrift- und Assetvorgaben

Die öffentliche Blade-Seite und der Elternbrief verwenden dieselbe
Schriftdefinition:

- Überschriften: `Comic Neue`, bold, `h1` mit 24 pt;
- alle übrigen Texte: `Atkinson Hyperlegible Next`, normal, 14 pt.

Die vorhandenen Projekt-Assets werden zentral wiederverwendet:

- `src/resources/fonts/ComicNeue-Bold.ttf`;
- `src/resources/fonts/AtkinsonHyperlegibleNext-Regular.otf`.

Die Assets werden für die öffentliche Blade-Seite über eine eigene öffentliche
CSS-/Font-Einbindung bereitgestellt und im Elternbrief-Template als
PhpWord-Schriftfamilien mit denselben Namen und Gewichten verwendet. Die
Webseite liefert die Fonts damit selbst aus; DOCX/ODT erhalten die korrekten
Schriftfamilien, Gewichte und Größen für kompatible Office-/PDF-Renderer. Eine
zusätzliche Font-Einbettung in die Office-Dateien ist nur umzusetzen, wenn sie
vom bestehenden PhpWord-Renderer sicher unterstützt wird. Die bereits
vorhandenen regulären/fetten Varianten werden nur dort eingebunden, wo ein
konkretes Dokumentelement dies benötigt; die fachliche Standarddarstellung
bleibt Comic Neue bold für Überschriften und Atkinson normal für Fließtext.

## Festgelegte fachliche Regeln

### Einheit und Kontext

Die öffentliche Seite bezieht sich immer auf genau eine `TeachingUnit` in genau
einer `TeachingGroup`. Sie zeigt:

- Einheitstitel;
- konkrete Gruppe;
- Schule;
- Namen der erstellenden Person;
- gespeicherten Einführungstext;
- Namen der Nutzerin bzw. des Nutzers, die/der die Einheit erstellt hat;
- der Einheit zugeordnete Kompetenzen;
- konkrete, terminierte Stunden-Slots mit Datum, Startzeit und Thema;
- freigegebene Dateien und URLs aus Phasen.

Nicht terminierte Stunden erscheinen nicht in der Terminübersicht. Materialien,
die nur direkt an der Einheit oder direkt an einer Stunde hängen, erscheinen
nicht öffentlich. Öffentlich werden ausschließlich Materialzuordnungen zu
konkreten Phasen.

### Einführungstext

Die Einheit erhält einen dauerhaft gespeicherten Einführungstext. Der
Elternbrief-Dialog lädt diesen Text vor. Änderungen werden an der Einheit
gespeichert und gelten danach sowohl für weitere Elternbrief-Exporte als auch
für die öffentliche Seite.

### Freigabestatus

Datei- und URL-Zuordnungen verwenden einen zentralen dreistufigen Status:

```text
not_shared
shared_immediately
shared_with_lesson
```

Die sichtbaren deutschen Bezeichnungen lauten:

```text
nicht freigegeben
sofort freigegeben
mit der Stunde freigegeben
```

Für direkt an einer Einheit zugeordnete Dateien und URLs sind nur `not_shared`
und `shared_immediately` zulässig. Der Status `shared_with_lesson` ist nur an
Phasen-Zuordnungen auswählbar.

Für `shared_immediately` erscheint eine Phasenmaterial-Zuordnung unmittelbar
auf der öffentlichen Seite. Für `shared_with_lesson` muss die zugehörige
konkrete Stunde einen konkreten, gültigen Planungs-Slot mit Startzeit besitzen.
Die Sichtbarkeit beginnt am Startzeitpunkt des frühesten solchen Slots der
zugehörigen Stunde. Ausgefallene Stunden lösen keine Sichtbarkeit aus; bei
mehreren Zuordnungen gilt die nächste konkrete, nicht ausgefallene Stunde.

Die Regeln werden bei jedem öffentlichen Seitenaufruf und jedem
Dateidownload serverseitig geprüft. Eine alte signierte URL darf den Zugriff
nicht wiederherstellen, wenn der Status inzwischen zurückgesetzt wurde.

### Öffentliche URLs und Sicherheit

- Die Einheitsseite verwendet eine dauerhaft gültige signierte URL.
- Die Einheitsseite wird nicht in der Navigation verlinkt.
- Öffentliche Dateidownloads erhalten separate dauerhaft gültige signierte
  URLs.
- Jeder Dateidownload prüft zusätzlich Einheit, Organisation, konkrete
  Zuordnung, aktuellen Freigabestatus und den aktuellen Stunden-Slot.
- Externe URLs werden nur angezeigt, wenn die konkrete Phasen-Zuordnung aktuell
  öffentlich sichtbar ist.
- Die öffentliche Route benötigt keine Anmeldung und verwendet kein
  Adminlayout.
- Es werden keine Schülerdaten, Beobachtungen oder Bewertungen in die
  öffentliche Darstellung übernommen.
- Die Resolver-Prüfung bleibt serverseitig, damit eine Einheit oder ein
  Material trotz dauerhaft signierter URL später über den aktuellen Daten- und
  Freigabestatus aus dem öffentlichen Zugriff genommen werden kann; ein
  zusätzlicher Veröffentlichungsdialog ist dafür nicht erforderlich.

Die signierte URL muss die konkrete Einheit eindeutig adressieren. Die
Autorisierung darf nicht ausschließlich auf einer erratbaren numerischen ID
beruhen; die Signaturprüfung und die Organisation-/Beziehungsprüfungen sind
verbindlich.

## Datenmodell

Die bestehenden Beziehungen bleiben die Quelle der Wahrheit. Es werden keine
öffentlichen Snapshots der Einheit eingeführt.

Geplante Änderungen:

- `teaching_units.introduction_text` für den Elternbrief-/Public-Text;
- `teaching_units.created_by_user_id` als nullable Fremdschlüssel auf `users`
  für den erstellenden Nutzer; neue Einheiten setzen diesen Wert beim Anlegen,
  bestehende Einheiten ohne historischen Wert bleiben darstellbar;
- `resource_references.publication_status` für direkt an der Einheit
  zugeordnete Dateien;
- `resource_links.publication_status` für direkt an der Einheit zugeordnete
  URLs;
- `lesson_phase_resources.publication_status` für Datei-Zuordnungen zu
  Phasen;
- `lesson_phase_resource_links.publication_status` für URL-Zuordnungen zu
  Phasen.

Die Statuswerte werden als stabile PHP-Enum modelliert und nicht als lose
Frontend-Strings fachlich ausgewertet. Datenbankseitig werden gültige Werte
über die im Projekt üblichen Constraints/Validierungsregeln abgesichert.

Die öffentliche Projektion wird durch eine zentrale Resolver-/Query-Schicht
aufgebaut. Dieselbe Schicht liefert die strukturierten Daten für Blade und
Elternbrief, damit Sichtbarkeitsregeln nicht in zwei Darstellungen dupliziert
werden.

## Öffentliche Darstellung

Die Route rendert eine Blade-Ansicht mit einem eigenständigen, reduzierten
öffentlichen Layout. Es gibt keine Inertia-Props, keine Admin-Topbar und keine
interaktiven Bearbeitungsfunktionen.

Der Seiten-Cache wird pro konkreter Einheit gebildet. Änderungen an folgenden
Daten invalidieren den betroffenen Cache gezielt:

- Einführungstext, Titel und Kompetenzen;
- Gruppen-/Schulbezug und verantwortliche Person;
- Stunden, Termine, Themen, Verschiebungen und Ausfälle;
- Phasen-Zuordnungen;
- Freigabestatus und Ressourcenmetadaten.

Zeitabhängige `shared_with_lesson`-Sichtbarkeit darf nicht bis nach dem
nächsten Startzeitpunkt aus einem veralteten Cache verborgen bleiben. Der
Cache muss deshalb entweder vor dem nächsten relevanten Startzeitpunkt
ablaufen oder anhand des nächsten Sichtbarkeitszeitpunkts segmentiert werden.

## Elternbrief

Im bestehenden Einheiteneditor wird eine Aktion „Elternbrief“ ergänzt. Sie
öffnet ein Modal mit ungefähr 80 Prozent Breite und 80vh Höhe. Das Modal
enthält:

- einen großen Textbereich mit dem gespeicherten Einführungstext;
- eine Auswahl `DOCX` oder `ODT`;
- eine OK-Aktion, die den Text speichert und den generierten Download startet.

Der Brief enthält mindestens Titel, Gruppe, Schule, erstellende Person,
Einführungstext, Kompetenzen sowie die Übersicht der konkreten terminierten
Stunden-Slots mit Datum und Thema. Wenn aktuell mindestens ein freigegebenes
Phasenmaterial vorhanden ist, enthält er zusätzlich die dauerhafte signierte
öffentliche URL und einen QR-Code, der auf diese Seite verweist. Ohne aktuell
freigegebenes Phasenmaterial werden QR-Code und öffentliche URL nicht in den
Brief aufgenommen.

Die Ausgabe verwendet die bestehende abstrakte Dokument-/Template-/Renderer-
Architektur aus ADR 0014. Ein neues Elternbrief-Template erzeugt denselben
strukturierten Inhalt für DOCX und ODT. Die Grundschrift und grundlegenden
Formatvorlagen orientieren sich an der bestehenden LSE-Ausgabe und setzen die
oben genannten Fontfamilien und Größen verbindlich um. Die
Dokumenterzeugung bleibt frei von fachlicher Datenmutation; das Speichern des
Einführungstexts erfolgt über den Unit-Update-/Export-Workflow.

## Bearbeitungsoberflächen

Im Phasenbereich des Stundeneditors erhalten Datei- und URL-Materialien neben
der bisherigen Auswahl einen zugänglichen Status-Selector oder eine
vergleichbare Mehrfachauswahl mit den drei deutschen Zuständen. Die Auswahl
wird an der Pivot-Zuordnung gespeichert.

Im Anhangs-/Materialbereich des Einheiteneditors erhalten direkt zugeordnete
Dateien und URLs eine Auswahl zwischen „nicht freigegeben“ und „sofort
freigegeben“. „Mit der Stunde freigegeben“ wird dort nicht angeboten.

Die serverseitigen Requests validieren Organisation, Einheit, Stunde, Phase,
Ressource und URL-Beziehung; eine Manipulation der UI darf keine fremde
Materialzuordnung veröffentlichen.

## Fehler- und Randfallverhalten

- Eine gültige signierte Einheits-URL für eine gelöschte oder nicht mehr
  zugängliche Einheit liefert nicht den Inhalt einer anderen Einheit.
- Ein Download einer nicht mehr freigegebenen Datei liefert `404` oder einen
  gleichwertigen öffentlichen Nichtzugriff, nicht die Datei.
- Ein Material ohne konkreten Stunden-Slot bleibt bei
  `shared_with_lesson` verborgen.
- Ein Material ohne Sicherheitsfreigabe des Uploads bleibt verborgen, auch
  wenn sein Veröffentlichungsstatus gesetzt ist.
- Externe URLs werden nicht serverseitig heruntergeladen; sie werden nur als
  geprüfte, aktuell freigegebene Links ausgegeben.
- Leerer Einführungstext ist zulässig; der Elternbrief und die Seite bleiben
  trotzdem strukturell gültig.
- Der QR-Code wird nur erzeugt, wenn eine öffentliche URL im Brief tatsächlich
  ausgegeben wird.

## Teststrategie

### Backend

- Unit-Tests für Enum und Sichtbarkeitsresolver;
- Feature-Tests für signierte öffentliche Einheitsseiten;
- Organisationstrennung und konkrete Gruppeneinheit-Prüfungen;
- Statusübergänge direkt an Einheit, Phase und Pivot;
- `shared_with_lesson` vor/nach Startzeitpunkt;
- frühester Slot bei zusammenhängenden Stunden;
- Ausfall, Verschiebung und fehlender Slot;
- separater Dateidownload mit alter Signatur nach Freigabeentzug;
- öffentliche Darstellung ohne Login;
- Elternbrief-DOCX-/ODT-Inhalt, Format und QR-/URL-Bedingung.

### Frontend

- Modalinitialisierung und Speichern des Einführungstexts;
- Formatwahl und Downloadaktion;
- korrekte Statusoptionen im Einheiten- und Phasenbereich;
- keine Option „mit der Stunde“ für direkte Einheitenmaterialien;
- Erhaltung der bestehenden Phasen- und Ressourcenbearbeitung.

### Artefakt-/Rendering-Prüfung

- strukturelle Prüfung der DOCX- und ODT-ZIP-Inhalte;
- Prüfung, dass CSS, DOCX und ODT die vorgegebenen Schriftfamilien und
  Grundgrößen verwenden;
- Prüfung der QR-Code-Einbettung und Ziel-URL;
- sofern LibreOffice verfügbar ist: Konvertierung beider Formate in PDF und
  visuelle Prüfung der grundlegenden LSE-ähnlichen Formatierung.

## Nicht Bestandteil dieses Schnitts

- Login- oder Passwortschutz für die Elternseite;
- öffentliche Schülerlisten, Bewertungen oder Beobachtungen;
- öffentliche Anzeige direkt an Stunden angehängter Materialien ohne
  Phasenfreigabe;
- Ablaufdatum für öffentliche URLs;
- unveränderliche Veröffentlichungs-Snapshots;
- öffentliche Menü-/Suchverknüpfung;
- interaktive Elternfunktionen wie Rückmeldungen oder Anmeldung.
