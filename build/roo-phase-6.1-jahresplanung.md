# Roo – Phase 6.1: Jahresplanung als zentraler Arbeitsbereich

**Status:** Implementierungsplan  
**Ziel:** Die bestehende Phase 6 wird um eine zentrale, dreispaltige Jahresplanungsoberfläche ergänzt. Sie verbindet Lehrplan/Curriculum, die eigene Unterrichtsplanung und die konkrete zeitliche Jahresplanung, ohne diese Ebenen fachlich zu vermischen.

---

## 1. Leitidee

Die Jahresplanung ist der zentrale Arbeitsort in Roo.

Die Hauptansicht bildet drei fachlich unterschiedliche Ebenen als drei Spalten ab:

1. **Links: Jahresplan** – Wann findet Unterricht statt und was ist dort eingeplant?
2. **Mitte: Meine Unterrichtseinheiten** – Was möchte ich tatsächlich unterrichten?
3. **Rechts: Curricula** – Welche Unterrichtseinheiten schlagen die für die Gruppe verwendeten Curricula vor?

Die fachliche Bewegung verläuft von rechts nach links:

```text
EducationPlan
      ↓
Curriculum / CurriculumUnit
      ↓  übernehmen
eigene TeachingUnit / Lesson
      ↓  einplanen
ScheduleSlot / ScheduledLesson
```

Wichtig: Eine Curriculum-UE ist niemals direkt planbar. Sie muss zunächst zu einer eigenen UE werden. Eine eigene UE ist anschließend vollständig bearbeitbar und nicht mehr inhaltlich vom Curriculum abhängig.

---

## 2. Domänenmodell und Verantwortlichkeiten

### 2.1 EducationPlan

`EducationPlan` bildet den zugrunde liegenden offiziellen Bildungs-/Lehrplan ab.

Seine Kompetenzen sind die normative Referenzebene:

```text
EducationPlan
└── EducationPlanCompetency
```

Die Originalkompetenz muss als stabile Referenz erhalten bleiben. Nutzer:innen dürfen die offizielle Kompetenz nicht überschreiben.

Eigene Arbeitsformulierungen dürfen ergänzend gespeichert werden.

Ziel ist, jederzeit beantworten zu können:

> Welche Kompetenzen des zugrunde liegenden Lehrplans wurden geplant bzw. tatsächlich behandelt?

Die Abdeckung des `EducationPlan` ist gegenüber der Abdeckung eines Curriculums die wichtigere Kennzahl.

### 2.2 Curriculum

Ein `Curriculum` ist eine didaktische Konkretisierung bzw. Strukturierung eines `EducationPlan`.

```text
Curriculum
├── CurriculumUnit
│   ├── empfohlene Dauer in Schulstunden
│   ├── Kompetenzen
│   └── optionale Stunden-Vorschläge
└── ...
```

Eine `CurriculumUnit` ist ein Vorschlag, keine tatsächlich verwendete Unterrichtseinheit.

Curriculum-Kompetenzen müssen – direkt oder über eine eigene Curriculum-Kompetenz – auf die zugrunde liegenden `EducationPlanCompetency`-Objekte zurückführbar sein.

Wo ein Curriculum eigene Kompetenzformulierungen besitzt, soll die Herkunft erhalten bleiben:

```text
EducationPlanCompetency
        ↑
CurriculumCompetency
```

### 2.3 Eigene Unterrichtseinheit

Die tatsächlich von Nutzer:innen verwendete Unterrichtseinheit ist ein eigenes Objekt, im Folgenden `TeachingUnit`.

Sie kann entstehen durch:

- Übernahme einer `CurriculumUnit`,
- eigene Neuanlage,
- später ggf. Kopieren einer früheren eigenen UE.

Bei Übernahme einer Curriculum-UE wird eine neue `TeachingUnit` angelegt. Dabei werden standardmäßig übernommen:

- Titel,
- relevante Metadaten,
- Kompetenzen,
- vorhandene Stunden-Vorschläge.

Danach ist die `TeachingUnit` unabhängig bearbeitbar.

Beispiel:

```text
CurriculumUnit:
"Nach Gott fragen"
empfohlen: 6 Stunden

          ↓ übernehmen

TeachingUnit:
"Nach Gott fragen"
tatsächlich: 4, 6, 8 oder beliebig viele Stunden
```

Nutzer:innen dürfen Stunden löschen, ergänzen, umsortieren oder vollständig neu aufbauen.

Die Herkunft bleibt als Referenz erhalten (`source_curriculum_unit_id` o. ä.), bestimmt aber nicht mehr den Inhalt.

### 2.4 Kompetenzen einer eigenen UE

Beim Übernehmen einer Curriculum-UE werden deren Kompetenzen in die eigene UE übernommen.

Eine `TeachingUnitCompetency` muss ihre Herkunft weiterhin referenzieren können:

```text
TeachingUnitCompetency
├── education_plan_competency_id
├── curriculum_competency_id (nullable)
├── source_curriculum_unit_id (nullable)
└── local_wording (nullable)
```

Die konkrete Implementierung darf vom Schema abweichen, solange folgende Eigenschaften garantiert sind:

- offizielle Lehrplankompetenz bleibt identifizierbar;
- Curriculum-Herkunft bleibt nachvollziehbar;
- Nutzer:innen können eine eigene Arbeitsformulierung verwenden;
- eigene zusätzliche Kompetenzen sind möglich;
- Coverage darf nicht durch Textvergleich ermittelt werden.

### 2.5 Lesson

Eine `TeachingUnit` besitzt eine geordnete Liste eigener `Lesson`-Objekte.

```text
TeachingUnit
├── Lesson 1
├── Lesson 2
├── Lesson 3
└── ...
```

Jede `Lesson` hat eine Dauer von **x ganzen Schulstunden**.

Eine Schulstunde entspricht organisatorisch 45 Minuten. Für die Jahresplanung wird ausschließlich mit ganzen Schulstunden gerechnet.

Nicht benötigt werden:

- minutengenaue Planung der Jahresverteilung,
- Beginn einer neuen UE innerhalb einer angebrochenen Schulstunde,
- Überhang von z. B. 20 Minuten in die nächste Stunde.

Eine `Lesson` mit `duration = 2` belegt zwei Unterrichtsslots.

### 2.6 Lesson-Kompetenzen

Für jede `Lesson` muss definiert werden können, welche Kompetenzen darin behandelt werden.

Im Normalfall wählt eine Stunde Kompetenzen aus der zugehörigen `TeachingUnit`.

```text
Lesson
      ↓
TeachingUnitCompetency
      ↓
EducationPlanCompetency
```

Wird in einer Stunde eine Kompetenz ergänzt, die noch nicht zur UE gehört, soll Roo anbieten, sie auch der UE hinzuzufügen.

Roo soll außerdem erkennen können:

> Diese Kompetenz ist der UE zugeordnet, kommt aber in keiner Stunde vor.

### 2.7 ScheduleSlot und ScheduledLesson

Ein `ScheduleSlot` ist eine konkrete mögliche Schulstunde der Unterrichtsgruppe.

Er wird automatisch aus folgenden Informationen erzeugt bzw. berechnet:

- Schuljahr,
- Stundenplan der Unterrichtsgruppe,
- Ferien,
- Feiertage,
- schulfreie Tage,
- weitere vorhandene Kalender-/Schuldaten.

Ein `ScheduledLesson` verbindet eine eigene `Lesson` mit einem oder mehreren `ScheduleSlot`s.

Es darf keine direkte Planung einer `CurriculumUnit` oder eines Curriculum-Stundenvorschlags geben.

---

## 3. Hauptoberfläche: drei Spalten

Desktop-Grundlayout:

```text
┌──────────────────────────┬────────────────────────┬───────────────────┐
│ JAHRESPLAN               │ MEINE UEs             │ CURRICULA         │
│                          │                        │                   │
│ konkrete Termine         │ eigene Planung        │ Quellen           │
│                          │                        │                   │
│ ca. 45 %                 │ ca. 35 %              │ ca. 20 %          │
└──────────────────────────┴────────────────────────┴───────────────────┘
```

Die Breiten sind Richtwerte. Trennlinien dürfen optional verschiebbar sein.

Die rechte Curriculum-Spalte ist **standardmäßig offen, aber einklappbar**.

Bei eingeklappter Curriculum-Spalte erhalten Jahresplan und eigene UEs den frei werdenden Platz.

Die UI soll die fachlichen Fragen sichtbar machen:

```text
WANN?                 WAS UNTERRICHTE ICH?       WOHER KOMMT ES?
Jahresplan            Meine UEs                  Curricula
```

---

## 4. TopBar

Die TopBar erhält ein zentrales Select für die aktive Unterrichtsgruppe.

Beispiel:

```text
[Jahresplanung]   [ Religion 4a ▾ ]     [Schuljahr 2026/27]
```

Ein Wechsel der Gruppe aktualisiert alle drei Spalten:

- Jahresplan,
- eigene UEs bzw. für diese Gruppe verfügbare Planung,
- verwendete Curricula.

Die vorhandenen Roo-Navigationskonventionen bleiben bestehen.

---

## 5. Linke Spalte: Jahresplan

### 5.1 Darstellung

Die linke Spalte zeigt keine klassische Kalenderansicht, sondern eine chronologische Liste aller relevanten Unterrichtstermine der aktiven Gruppe.

Beispiel:

```text
September 2026

Di 15.09.  3. Std.   [ frei ]
Di 22.09.  3. Std.   [ Gott-Sucher-Koffer ]
Di 29.09.  3. Std.   [ Bilder von Gott ]

Oktober 2026

Di 06.10.  3. Std.   [ ... ]

──────── Herbstferien ────────

Di 03.11.  3. Std.   [ ... ]
```

Ferien und andere längere Unterbrechungen sollen als sichtbare Trenner erscheinen, aber keine verfügbaren Slots erzeugen.

Beim Öffnen soll sinnvollerweise zum heutigen bzw. nächsten Unterrichtstermin gescrollt werden.

### 5.2 Slot-Zustände

Mindestens folgende Zustände müssen konzeptionell unterschieden werden:

- frei,
- bewusst als Puffer reserviert,
- geplant,
- durchgeführt (für spätere Ausbaustufe bereits im Modell berücksichtigen),
- abwesend,
- Unterricht entfällt / anderweitig blockiert,
- Konflikt.

„Puffer“ ist ein verfügbarer Unterrichtsslot, der bewusst nicht verplant wird.

„Abwesend“ bzw. „entfällt“ bedeutet, dass der Slot für Unterricht nicht verfügbar ist.

### 5.3 Manuelle Markierungen

Nutzer:innen können einzelne Termine/Tage markieren, z. B.:

- abwesend,
- Unterricht entfällt,
- Schulveranstaltung,
- Klassenfahrt/Ausflug,
- anderweitig belegt,
- Puffer,
- Notiz.

Die konkrete Liste darf im weiteren UX-Feinschliff angepasst werden.

### 5.4 Reflow

Wenn ein bisher verfügbarer Slot nachträglich ausfällt, kann Roo nachfolgende geplante Stunden automatisch auf die nächsten verfügbaren Slots verschieben.

Beispiel:

```text
vorher:
15.09 A1
22.09 A2
29.09 A3
06.10 A4

29.09 wird "abwesend"

nachher:
15.09 A1
22.09 A2
29.09 ABWESEND
06.10 A3
13.10 A4
```

Das Verhalten soll vorhersehbar und rückgängig machbar sein.

Mindestens vorzusehen:

- automatische Verschiebung,
- Undo,
- optional spätere Einstellung „automatisch verschieben“ / „nachfragen“.

### 5.5 Overflow

Roo darf Stunden, die nach Verschiebungen nicht mehr ins Schuljahr passen, niemals still verwerfen.

Stattdessen muss eine Warnung erscheinen:

> 2 geplante Stunden passen nicht mehr in die verfügbaren Unterrichtstermine dieses Schuljahres.

Die betroffenen Stunden müssen auffindbar bleiben.

---

## 6. Mittlere Spalte: „Meine Unterrichtseinheiten“

Dies ist die eigentliche Bibliothek der planbaren Unterrichtsinhalte.

Sie enthält ausschließlich eigene `TeachingUnit`-Objekte, unabhängig davon, ob sie:

- aus einem Curriculum übernommen,
- selbst erstellt,
- später aus früheren Planungen kopiert wurden.

Beispiel:

```text
MEINE UNTERRICHTSEINHEITEN

[ + Neue UE ] [ Suche ]

▾ Nach Gott fragen
  aus Curriculum A
  5 Std. · 4 Kompetenzen

  ≡ Gott-Sucher-Koffer
  ≡ Wo suchen Menschen Gott?
  ≡ Gottesbilder in Psalmen
  ≡ Mein Bild von Gott
  ≡ Abschluss

▸ Martin Luther
  eigene UE
  4 Std. · 3 Kompetenzen
```

Die Herkunft wird dezent angezeigt, verändert aber nicht die Bearbeitbarkeit.

### 6.1 Bearbeitung

UEs können hier aufgeklappt und bearbeitet werden.

Insbesondere:

- Titel und Metadaten,
- Kompetenzen,
- Reihenfolge der Stunden,
- Stunden hinzufügen,
- Stunden entfernen,
- Stunden bearbeiten.

Die Stundenliste innerhalb einer UE ist Drag-and-drop-sortierbar.

### 6.2 Planungsstatus

Eine UE bleibt in der mittleren Spalte sichtbar, auch wenn Teile davon bereits eingeplant sind.

Beispiel:

```text
Nach Gott fragen
3/5 eingeplant

✓ Gott-Sucher-Koffer
✓ Wo suchen Menschen Gott?
✓ Gottesbilder in Psalmen
○ Mein Bild von Gott
○ Abschluss
```

Eine UE kann zeitlich unterbrochen werden. Ihre Stunden müssen im Jahresplan nicht unmittelbar aufeinander folgen.

Das Einplanen von nur einigen Stunden teilt die UE **nicht** fachlich in mehrere UEs.

Eine echte Funktion „UE teilen“ wäre eine separate, explizite Operation.

---

## 7. Rechte Spalte: Curricula

Die rechte Spalte ist eine schmale Quellen-/Referenzspalte.

Sie zeigt die UEs aller für die Unterrichtsgruppe verwendeten Curricula.

Beispiel:

```text
CURRICULA

Curriculum A

▾ Nach Gott fragen
  6 Std.
  4 Kompetenzen

  ▸ Kompetenzen
  ▸ Stunden-Vorschläge

▸ Schöpfung
  8 Std.
  5 Kompetenzen
```

Sie ist:

- standardmäßig geöffnet,
- einklappbar,
- keine vollwertige Bearbeitungsfläche für eigene Unterrichtsplanung.

Curriculum-Inhalte selbst werden hier nicht durch die normale Jahresplanung verändert.

---

## 8. Drag-and-drop-Interaktionen

### 8.1 Curriculum → Meine UEs

Eine `CurriculumUnit` kann von rechts in die mittlere Spalte gezogen werden.

Beim Drop wird automatisch eine eigene `TeachingUnit` erzeugt.

Standardmäßig werden übernommen:

- Titel/Metadaten,
- Kompetenzen,
- vorhandene Stunden-Vorschläge.

Danach erscheint eine Undo-/Bearbeiten-Meldung, z. B.:

> „Nach Gott fragen“ wurde als eigene UE angelegt: 6 Stunden, 4 Kompetenzen.  
> Bearbeiten · Rückgängig

Alternativ darf zusätzlich ein expliziter Button „Als eigene UE übernehmen“ angeboten werden. Drag-and-drop darf nicht die einzige Möglichkeit sein.

### 8.2 Eigene UE → Jahresplan

Eine ganze `TeachingUnit` kann auf einen freien Slot im Jahresplan gezogen werden.

Roo verteilt ihre Stunden ab diesem Punkt auf die nächsten verfügbaren Slots.

Eine mehrstündige `Lesson` belegt entsprechend mehrere aufeinanderfolgende verfügbare Unterrichtsslots.

### 8.3 Einzelne Lesson → Jahresplan

Auch eine einzelne Stunde aus einer aufgeklappten eigenen UE kann in den Jahresplan gezogen werden.

Nur diese Lesson wird eingeplant.

### 8.4 Jahresplan → Jahresplan

Geplante Stunden können innerhalb des Jahresplans umsortiert werden.

Dabei müssen Slot-Sperren, Puffer und mehrstündige Lessons berücksichtigt werden.

### 8.5 Zugängliche Alternative

Jede Drag-and-drop-Aktion benötigt eine alternative Bedienmöglichkeit über Buttons/Menüs.

Drag-and-drop ist Komfortfunktion, nicht einzige Funktion.

---

## 9. Stundeneditor

Ein Klick auf eine eigene `Lesson` öffnet einen Bootstrap-Modal zur Bearbeitung.

Mindestens sichtbar/bearbeitbar:

- Titel,
- Dauer in ganzen Schulstunden,
- Lernziele,
- Kompetenzen,
- Phasen,
- Materialien,
- Links/Dateien,
- Hausaufgabe,
- Leistungsnachweis,
- Bemerkungen, soweit im bestehenden Phase-6-Modell vorhanden.

Die vorhandenen fachlichen Datenstrukturen aus Phase 6 sollen wiederverwendet und nicht unnötig dupliziert werden.

---

## 10. Phasen

Die Phasen einer Stunde sind innerhalb des Stundeneditors Drag-and-drop-sortierbar.

Für die Jahresplanung benötigen Phasen keine minutengenaue Dauer.

Phasen bilden die didaktische Binnenstruktur einer Lesson; `Lesson.duration` bestimmt dagegen die Zahl benötigter Schulstunden.

### Phasenbearbeitung

Verschachtelte Bootstrap-Modals sollen vermieden werden.

Beim Bearbeiten einer Phase soll bevorzugt ein Bootstrap-Offcanvas bzw. ein vergleichbares seitliches Detailpanel geöffnet werden.

Damit bleibt die Hierarchie sichtbar:

```text
Jahresplan
  → Stundeneditor (Modal)
      → Phaseneditor (Offcanvas)
```

---

## 11. Kompetenzabdeckung

Die Kompetenzverknüpfung ist keine optionale Zusatzfunktion, sondern Teil der Kernlogik.

Roo muss mindestens unterscheiden können:

```text
○ noch nicht berücksichtigt
◐ einer eigenen UE zugeordnet
● konkreten Lessons zugeordnet / eingeplant
✓ tatsächlich behandelt
```

„Tatsächlich behandelt“ kann UI-seitig später vollständig umgesetzt werden, muss aber im Modell mitgedacht werden.

### 11.1 Curriculum-Abdeckung

Roo muss ermitteln können, welche Kompetenzen der verwendeten Curricula durch eigene UEs/Lessons berücksichtigt werden.

Eine Curriculum-UE gilt dabei nicht allein deshalb als abgedeckt, weil sie übernommen wurde.

Wenn eine völlig eigene UE dieselben Kompetenzen abdeckt, muss dies ebenfalls berücksichtigt werden.

### 11.2 EducationPlan-Abdeckung

Die maßgebliche Auswertung erfolgt gegen `EducationPlanCompetency`.

Mehrere Curriculum-Kompetenzen bzw. UEs dürfen dieselbe EducationPlan-Kompetenz referenzieren. Sie wird bei der Abdeckung trotzdem nur einmal gezählt.

Coverage darf niemals allein anhand identischer Kompetenztexte berechnet werden.

Roo muss langfristig Aussagen ermöglichen wie:

```text
Bildungsplan-Abdeckung
24 von 26 Kompetenzen behandelt

✓ 20 vollständig
◐  4 geplant/teilweise behandelt
○  2 noch nicht behandelt
```

Sowie eine Aufschlüsselung nach Kompetenzbereichen.

### 11.3 Hauptansicht

Die detaillierte EducationPlan-Coverage gehört nicht als vierte Spalte in die Jahresplanung.

In der Hauptansicht genügt ein kompakter Indikator bzw. Einstieg, z. B.:

```text
Bildungsplan 78 %   ·   Curriculum 83 %
```

Dieser führt später in eine eigene Kompetenz-/Coverage-Ansicht.

---

## 12. Curriculum-Spalte als Coverage-Hinweis

Die rechte Spalte darf zusätzlich kompakt anzeigen, wie weit die Kompetenzen einer Curriculum-UE bereits durch die eigene Planung abgedeckt sind:

```text
✓ Nach Gott fragen       4/4
◐ Schöpfung              3/5
○ Jesus Christus         0/4
```

Diese Anzeige bezieht sich auf Kompetenzabdeckung, nicht lediglich auf „UE wurde kopiert“.

---

## 13. Responsive Verhalten

### Desktop / großes Tablet

Drei Spalten wie beschrieben.

### Schmalere Ansichten

Die Curriculum-Spalte kann eingeklappt werden.

Bei weiter sinkender Breite dürfen Jahresplan und eigene UEs in umschaltbare Ansichten überführt werden.

Auf kleinen Touch-Geräten darf die Bedienung nicht von Drag-and-drop abhängig sein.

Responsive Details sind Bestandteil des UI-Feinschliffs, die Desktop-Jahresplanung hat in Phase 6.1 Priorität.

---

## 14. Technische/UI-Anforderungen

- Bestehenden Laravel-/Vue-/Inertia-/Bootstrap-Stack verwenden.
- Keine neue UI-Komponentenbibliothek nur für Phase 6.1 einführen.
- Drag-and-drop-Lösung muss Vue 3 unterstützen und verschachtelte sortierbare Listen zuverlässig handhaben.
- Server bleibt fachliche Autorität; komplexe Reflow-/Coverage-Regeln dürfen nicht ausschließlich im Browser existieren.
- Änderungen müssen nach Möglichkeit atomar und rückgängig machbar sein.
- Optimistische UI ist zulässig, wenn Fehler sauber zurückgerollt werden.
- IDs und Relationen sind für Coverage maßgeblich, nicht Texte.
- Curriculum-Objekte dürfen durch die Bearbeitung eigener UEs nicht mutiert werden.
- EducationPlan-Originaldaten dürfen durch normale Unterrichtsplanung nicht mutiert werden.

---

## 15. Umsetzungsschritte

### 6.1.1 Domänenmodell prüfen und ergänzen

Vor UI-Arbeit bestehende Phase-6-Modelle gegen die in diesem Dokument beschriebenen Ebenen prüfen.

Insbesondere sicherstellen:

- EducationPlan ↔ EducationPlanCompetency
- Curriculum ↔ CurriculumUnit
- Curriculum-Kompetenz ↔ EducationPlan-Kompetenz
- CurriculumUnit ↔ optionale Stunden-Vorschläge
- TeachingUnit
- TeachingUnitCompetency
- Lesson
- LessonCompetency
- ScheduleSlot
- ScheduledLesson
- Herkunftsrelationen ohne inhaltliche Kopplung

Bestehende Modelle bevorzugt migrieren/erweitern statt parallel neu erfinden.

### 6.1.2 Unterrichtsslots

Automatische Slot-Ermittlung aus Schuljahr, Gruppe, Stundenplan und schulfreien Zeiten implementieren.

Manuelle Slot-Zustände ergänzen.

### 6.1.3 Drei-Spalten-Shell

Jahresplan, eigene UEs und Curriculum-Spalte implementieren.

Curriculum-Spalte einklappbar, Default offen.

Gruppenauswahl in TopBar integrieren.

### 6.1.4 Curriculum-Browser

Verwendete Curricula und deren UEs anzeigen.

Aufklappen, Kompetenzen/Stundenvorschläge einsehbar machen.

### 6.1.5 CurriculumUnit → TeachingUnit

Übernahme per Drag-and-drop und expliziter Aktion implementieren.

Kompetenzen und Stunden-Vorschläge korrekt instanziieren.

Undo ermöglichen.

### 6.1.6 Eigene UE-Bibliothek

Eigene UEs anzeigen, aufklappen, bearbeiten und Stunden sortieren.

Planungsstatus pro Lesson anzeigen.

### 6.1.7 TeachingUnit/Lesson → Jahresplan

UEs und einzelne Lessons einplanbar machen.

Mehrstündige Lessons korrekt auf Slots verteilen.

### 6.1.8 Jahresplan-Reordering

Geplante Lessons umsortierbar machen.

Alternative Nicht-Drag-Bedienung vorsehen.

### 6.1.9 Puffer, Ausfall und Reflow

Slot-Zustände und automatische Verschiebung implementieren.

Undo und Overflow-Erkennung ergänzen.

### 6.1.10 Lesson-Editor

Bestehende Phase-6-Stundenbearbeitung als Modal in den Workflow integrieren.

Kompetenzzuordnung und sortierbare Phasen sicherstellen.

### 6.1.11 Phasen-Editor

Phasen über Offcanvas/Detailpanel bearbeiten; keine Modal-auf-Modal-UX.

### 6.1.12 Coverage-Grundlage

Berechnung mindestens für:

- Kompetenz → TeachingUnit,
- Kompetenz → Lesson,
- Curriculum-Coverage,
- EducationPlan-Coverage.

In Phase 6.1 genügt eine kompakte Darstellung; eine umfangreiche Analyseansicht kann eine spätere Phase sein.

### 6.1.13 Tests

Automatisierte Tests insbesondere für:

- Curriculum-UE wird zu unabhängiger eigener UE;
- Curriculum-/EducationPlan-Daten werden dabei nicht verändert;
- Kompetenz-Herkunft bleibt erhalten;
- eigene Änderungen beeinflussen Curriculum nicht;
- Lesson-Kompetenzen werden korrekt aggregiert;
- dieselbe EducationPlan-Kompetenz wird nicht doppelt gezählt;
- Slot-Generierung berücksichtigt Ferien/Feiertage;
- mehrstündige Lessons belegen korrekte Anzahl Slots;
- Ausfall verschiebt nachfolgende Planung korrekt;
- Puffer wird nicht automatisch belegt;
- Overflow wird erkannt und nichts verworfen;
- Drag-and-drop-Reordering persistiert;
- alternative Nicht-Drag-Aktionen liefern dasselbe Ergebnis.

---

## 16. Definition of Done

Phase 6.1 ist abgeschlossen, wenn folgende End-to-End-Abläufe funktionieren:

1. Nutzer:in wählt eine Unterrichtsgruppe in der TopBar.
2. Links erscheinen automatisch deren Unterrichtstermine für das Schuljahr.
3. Rechts erscheinen die UEs der für die Gruppe verwendeten Curricula.
4. Die Curriculum-Spalte ist standardmäßig offen und einklappbar.
5. Eine Curriculum-UE kann in „Meine Unterrichtseinheiten“ übernommen werden.
6. Dabei entsteht eine unabhängige eigene UE mit übernommenen Kompetenzen und optionalen Stunden-Vorschlägen.
7. Die eigene UE kann beliebig verändert werden, ohne das Curriculum zu verändern.
8. Kompetenzen können auf Ebene der UE und einzelner Lessons bearbeitet/zugeordnet werden, ohne die Referenz zur offiziellen EducationPlan-Kompetenz zu verlieren.
9. Eigene UEs oder einzelne Lessons können in den Jahresplan eingeplant werden.
10. Mehrstündige Lessons belegen ganze Unterrichtsslots.
11. Geplante Lessons können umsortiert werden.
12. Slots können als Puffer bzw. Ausfall/Abwesenheit markiert werden.
13. Ausfälle können nachfolgende Planung automatisch verschieben.
14. Nicht mehr ins Schuljahr passende Stunden werden sichtbar gewarnt und niemals verworfen.
15. Lessons lassen sich im Modal bearbeiten; Phasen sind sortierbar und ohne verschachtelte Modals bearbeitbar.
16. Roo kann aus den Relationen zuverlässig ermitteln, welche Curriculum- und EducationPlan-Kompetenzen durch die eigene Unterrichtsplanung berücksichtigt sind.
17. Die wesentlichen Drag-and-drop-Funktionen besitzen eine zugängliche alternative Bedienung.

---

## 17. Nicht Bestandteil von Phase 6.1

Nicht unnötig in diese Phase hineinziehen:

- minutengenaue Stoffverteilung,
- automatische KI-Erstellung kompletter UEs,
- vollständige grafische Kompetenzanalyse,
- komplexes Reporting,
- automatische Bewertung, ob eine Kompetenz pädagogisch „ausreichend“ behandelt wurde,
- Synchronisation eigener Änderungen zurück in Curricula,
- vierte Hauptspalte für den EducationPlan.

Phase 6.1 schafft zuerst die belastbare manuelle Planungs- und Kompetenzarchitektur, auf der diese Funktionen später aufbauen können.
