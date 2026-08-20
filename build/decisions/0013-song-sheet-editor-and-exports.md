# ADR 0013: Liedblatt-Editor und Gruppenliederbuch-Exporte

## Status

Akzeptiert

## Entscheidung

Liedfassungen bestehen aus geordneten Liedteilen. Jeder Teil speichert seinen
Text und kann als Kehrvers markiert werden. Die visuelle Bearbeitung wird als
versionseigenes `layout_data` gespeichert; Bilddateien bleiben separate,
private Storage-Objekte. Dadurch kann ein Liedblatt später erneut bearbeitet
werden, ohne ein erzeugtes PDF als Quelle zu verwenden.

Der Export nimmt den Ausgangsbestand und alle später über Phasen verwendeten
Lieder in ihrer gruppenspezifischen Nummerierung auf. Ein Datum begrenzt die
Auswahl. Druckstände werden gespeichert, sodass der Export „neu“ nur seit dem
letzten Druck hinzugekommene Einträge berücksichtigt. A5 ist das Primärformat;
A4 und Broschüre verwenden denselben Inhalt mit eigenem Ausgabeformat.
