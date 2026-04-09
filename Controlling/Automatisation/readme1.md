# WooCommerce Export & Reporting

Dieses Skript lädt Bestellungen aus mehreren WooCommerce-Shops, verarbeitet Gutschein- und Bestelldaten, erstellt einen Excel-Report mit Dashboard sowie optional zusätzlich eine PDF-Ausgabe.

## Funktionen

Das Skript übernimmt folgende Aufgaben:

- Abruf von Bestellungen über die WooCommerce REST API
- Verarbeitung von Bestell- und Gutscheininformationen
- Vermeidung von Duplikaten über gespeicherte Bestandsdaten
- Aufbau einer zentralen Excel-Datei mit:
  - Gesamtübersicht aller Shops
  - Einzeltabellen pro Shop
  - Monatsreport
  - Dashboard mit KPIs und Diagrammen
- Optionale PDF-Erstellung der exportierten Daten
- Speicherung des letzten erfolgreichen Ausführungszeitpunkts für inkrementelle Updates

---

## Voraussetzungen

Installierte Python-Version:
- Python 3.13 oder kompatibel

Benötigte Python-Pakete:

```bash
pip install requests openpyxl reportlab