# ADR 0002: Bootstrap mit Vue und Inertia

- Status: Accepted
- Datum: 2026-08-06

## Entscheidung

- Vue 3 und Inertia.js bilden die interaktive Oberfläche.
- Bootstrap 5.3 und Bootstrap Icons bilden die visuelle Grundlage.
- Roo entwickelt eine kleine eigene Vue-Komponentenbibliothek.
- Kein vollständiges zweites UI-Framework.
- Bootstrap-JavaScript wird nur gezielt genutzt; Komponentenstatus wird
  grundsätzlich durch Vue gesteuert.

## Gründe

- vorhandene Erfahrung mit Bootstrap,
- geringer Design-Lock-in,
- gute Eignung für Formulare, Tabellen und responsive Layouts,
- volle Kontrolle über komplexe fachliche Komponenten.
