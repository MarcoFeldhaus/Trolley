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
```

### Architektur
````
Automatisation/
├── main.py
├── config.py
├── state.json
└── output.xls

````
### Voraussetzungen:
Python 3.10+ (empfohlen: 3.13)

### Installation der Abhängigkeiten
````
pip install requests openpyxl reportlab
````
### Installation
````
git clone <repo-url>
cd Automatisation
pip install -r requirements.txt

````

### Konfiguration
config.py:
````
shops = [
    {
        "name": "Shop A",
        "url": "https://shop-a.de",
        "ck": "consumer_key",
        "cs": "consumer_secret"
    }
]

OUTPUT_FILE = "output.xlsx"
STATE_FILE = "state.json"
````

### Nutzung
python main.py:
* Funktionsübersicht (Code-Logik)
* Helper Funktion	
* Beschreibung
* log()	Konsolen-Logging mit Zeit
* safe_str()	sichere String-Konvertierung
* to_int()	robuste Integer-Konvertierung
* format_date()	ISO → deutsches Datum
* normalize_price()	Preisformatierung
* extract_price_from_name()	Preis aus Produktnamen
* get_meta_value()	WooCommerce Meta lesen
* normalize_voucher_codes()	Gutscheinliste normalisieren
* map_zweck()	Privat/Firma Mapping
* State
* load_last_run() → lädt letzten Lauf
* save_last_run() → speichert neuen Lauf
* API
* fetch_orders()
* WooCommerce API (/orders)
* Pagination
* Fehlerhandling
* Logging
* Transformation
* transform_orders()


### Skript:
Erstellt strukturierte Datensätze:

* Gutschein-Code
* Order ID
* Produkt
* Datum
* Preis
* Zweck
* Auftraggeber
* Menge

🔥 Duplikatschutz:

(order_id, voucher_code)
Gutschein-Logik
_woo_vou_codes

Fallback:

_woo_vou_meta_order_details
Monatsreport
build_monthly_report()

Aggregiert:

Umsatz pro Monat
Bestellungen pro Monat
Dashboard
KPIs
Gesamtumsatz
Bestellungen
Ø Bestellwert
Charts
Umsatz pro Monat (LineChart)
Umsatz pro Shop (BarChart)
Extras
Wachstum (MoM)
Top 5 Produkte
Export
Excel (write_excel)
Alle Shops
Einzelblätter
Monatsreport
Dashboard
PDF (write_pdf)
Tabellarische Übersicht
Querformat (A4)
⏱️ Automatisierung (Windows Task Scheduler)
Python-Pfad
C:\Users\marco\AppData\Local\Programs\Python\Python313\python.exe
Script-Pfad
C:\Users\marco\OneDrive\Developer\Trolley\Controlling\Automatisation\main.py
Task erstellen
Win + S → Aufgabenplanung
„Aufgabe erstellen…“
Allgemein
Name: WooCommerce Export
✅ Mit höchsten Privilegien ausführen
Trigger
Täglich
07:00 Uhr
Aktion

Programm:

C:\Users\marco\AppData\Local\Programs\Python\Python313\python.exe

Argumente:

"C:\Users\marco\OneDrive\Developer\Trolley\Controlling\Automatisation\main.py"

Starten in:

C:\Users\marco\OneDrive\Developer\Trolley\Controlling\Automatisation
📊 Output & Reporting
Excel enthält
Alle Shops
je Shop ein Sheet
Monatsreport
Dashboard
Dashboard Inhalte
Umsatz gesamt
Bestellungen gesamt
Durchschnittlicher Bestellwert
Umsatz pro Monat
Umsatz pro Shop
Wachstum
Top Produkte
PDF
komplette Tabelle
automatisch generiert
gleiche Datenbasis wie Excel
🧪 Troubleshooting
API Fehler
401 / 403

👉 API Keys prüfen

Keine Daten

👉 state.json löschen

PDF fehlt
pip install reportlab
Excel leer
API prüfen
Daten prüfen
Meta-Felder prüfen

🧠 Best Practices
Sicherheit
API Keys nicht committen
.env nutzen
Logging verbessern
import logging
Backup
Excel versionieren
optional mit Datum speichern
Monitoring
E-Mail Alerts
Teams / Slack Integration
📌 Erweiterungsmöglichkeiten
Power BI Integration
Datenbank statt Excel
Docker Setup
Cloud Upload (OneDrive / SharePoint)
Mailversand
KPI Alerts

👨‍💻 Autor:
Marco Feldhaus
Team Entwicklung

📄 Lizenz:
Internes Tool – keine öffentliche Lizenz vorgesehen