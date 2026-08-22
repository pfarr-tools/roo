# ADR 0014: Strukturierte Dokumente und PhpOffice-Templates

## Status

Akzeptiert

## Kontext

Roo soll strukturierte Assessments später als DOCX und ODT ausgeben. Die
fachlichen Daten bleiben dabei die Quelle der Wahrheit; ein konkretes
Dokumentlayout darf nicht in Controllern oder einzelnen Exportpfaden verteilt
werden.

## Entscheidung

Dokumente werden als abstrakte `App\Documents\Document`-Objekte mit Titel,
Metadaten und einem Template-Schlüssel modelliert. Templates implementieren
`DocumentTemplate` und erzeugen ein `PhpWord`-Objekt. Die
`DocumentTemplateRegistry` erlaubt mehrere registrierte Templates, während
`PhpOfficeDocumentRenderer` die Ausgabe über PhpOffice/PhpWord als DOCX oder
ODT schreibt.

Konkrete Assessment-Dokumente und Layouts werden später als eigene
Dokument-/Template-Implementierungen ergänzt. Renderer und Templates dürfen
keine fachlichen Daten dauerhaft verändern.

## Konsequenzen

- DOCX und ODT verwenden denselben strukturierten Dokumentaufbau.
- Neue Varianten werden über Templates ergänzt, ohne den Renderer zu ändern.
- Dokumentgenerierung kann später in einen queue-fähigen Exportjob verschoben
  werden.
- Die Registry ist bewusst zentral und explizit; unbekannte Template-Schlüssel
  führen zu einem Fehler statt zu einem stillen Fallback.
