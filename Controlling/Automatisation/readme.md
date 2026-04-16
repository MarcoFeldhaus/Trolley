# Dokumentation: export_woocommerce_cumulative.py

## 📋 Übersicht

Das Skript **`export_woocommerce_cumulative.py`** dient dem **kumulativen Export von WooCommerce-Bestellungen** (speziell Gutscheincodes) aus mehreren Online-Shops in eine formatierte Excel-Datei mit erweiterten Auswertungen, Dashboards und optionalem PDF-Report.

---

## 🎯 Hauptfunktionalität

- 🔄 **Kumulativer Export**: Duplikate werden automatisch vermieden
- 📊 **Multi-Shop-Support**: Gleichzeitiger Export von mehreren WooCommerce-Shops
- 🔍 **State-Based Updates**: Inkrementelle Updates basierend auf Timestamp
- 📅 **Flexible Zeiträume**: Manual definierbare Export-Zeiträume
- 📈 **Dashboard & Charts**: Automatisch generierte KPIs und Visualisierungen
- 📄 **PDF-Report**: Übersichtsseite + detaillierte Tabellen pro Region
- ✅ **Normalisierung**: Automatische Bereinigung und Formatierung aller Daten

---

## 📦 Abhängigkeiten

### Erforderliche Python-Pakete

```bash
pip install requests openpyxl reportlab
```

| Paket | Version | Zweck |
|-------|---------|-------|
| `requests` | beliebig | HTTP-Requests zur WooCommerce REST-API |
| `openpyxl` | ≥2.6 | Excel-Datei-Erstellung und -Formatierung |
| `reportlab` | ≥3.5 | PDF-Generierung (optional) |

### Abhängige Dateien

```
📁 Controlling/Automatisation/
├── export_woocommerce_cumulative.py  ← Dieses Skript
├── config.py                          ← Konfiguration (erforderlich)
└── state.json                         ← State-Datei (wird bei Start erstellt)
```

---

## ⚙️ Konfiguration

### config.py - Erforderliche Variablen

Die Datei `config.py` must im selben Verzeichnis liegen und folgende Variablen enthalten:

```python
# Liste aller Shops mit API-Credentials
shops = [
    {
        "name": "Shop1 Freiburg",
        "url": "https://shop1.example.com",
        "ck": "ck_xxxxxxxxxxxxxxxxxxxxx",  # Consumer Key
        "cs": "cs_xxxxxxxxxxxxxxxxxxxxx"   # Consumer Secret
    },
    {
        "name": "Shop2 Schwarzwald",
        "url": "https://shop2.example.com",
        "ck": "ck_yyyyyyyyyyyyyyyyyyyyy",
        "cs": "cs_yyyyyyyyyyyyyyyyyyyyy"
    }
]

# Ausgabedatei für Excel
OUTPUT_FILE = "export.xlsx"

# Datei für State-Management (Timestamp des letzten Laufs)
STATE_FILE = "state.json"
```

### WooCommerce REST-API Setup

1. **API-Credentials generieren**:
   - Im WooCommerce Admin: *Settings* → *Advanced* → *REST API*
   - Consumer Key und Secret kopieren
   - In `config.py` eintragen

2. **Erforderliche Berechtigungen**:
   - Mindestens "Read" Zugriff auf "Orders"

---

## 🚀 Verwendung

### Schritt 1: Skript ausführen

```bash
python export_woocommerce_cumulative.py
```

### Schritt 2: Modus auswählen

Das Skript bietet **zwei Export-Modi** mit automatischem Timeout (5 Sekunden):

#### **Modus 1: State-basierter Export** (EMPFOHLEN - Standard)

```
[...] Wähle Modus (1 oder 2): [ENTER]
```

✅ **Verhalten**:
- Exportiert nur Daten **seit dem letzten erfolgreichen Lauf**
- State wird in `state.json` gespeichert
- **Ideal für**: Regelmäßige Läufe (täglich, stündlich, etc.)
- **Vorteil**: Minimale API-Last, schnelle Ausführung

#### **Modus 2: Zeitraum-basierter Export** (MANUELL)

```
[...] Wähle Modus (1 oder 2): 2
[...] Start-Datum (DD.MM.YYYY): 01.01.2026
[...] End-Datum (DD.MM.YYYY): 31.03.2026
```

✅ **Verhalten**:
- Exportiert Daten aus dem definierten Zeitraum
- Output-Datei: `export_01012026_31032026.xlsx`
- State wird **nicht** aktualisiert
- **Ideal für**: Nachimporte, Korrektionen, historische Abfragen

---

## 📁 Output-Dateien

### Excel-Datei (z.B. `export.xlsx`)

```
📊 export.xlsx
├─ 📄 Alle Shops
│  └─ Konsolidierte Daten aller Shops (Duplikate vermieden)
│
├─ 📊 Dashboard
│  ├─ KPI-Metriken
│  │  ├─ Gesamtumsatz
│  │  └─ Gesamte Gutscheine
│  ├─ Monatliche Übersicht (mit Regionen)
│  ├─ Shop-Übersicht (Gesamtansicht)
│  └─ Visualisierungen
│     ├─ 📈 LineChart: Wachstum pro Monat
│     └─ 📊 BarChart: Umsatz pro Shop
│
├─ 📄 Freiburg
├─ 📄 Schwarzwald
└─ 📄 [weitere Shops...]
   └─ Detaillierte Daten pro Region
```

### PDF-Datei (z.B. `export.pdf`)

| Seite | Inhalt |
|-------|--------|
| 1 | Dashboard mit KPIs + regionale Zusammenfassung |
| 2+ | Eine Seite pro Region mit Detailtabelle aller Gutscheine |

### State-Datei (`state.json`)

```json
{
  "last_run": "2026-04-13T10:30:45.123456"
}
```

> ℹ️ Wird nur im **State-Modus** (Modus 1) automatisch aktualisiert

---

## 📊 Datenstruktur

### Excel-Spaltenformat

| # | Spalte | Datentyp | Format | Beispiel |
|---|--------|----------|--------|----------|
| 1 | **Shop** | Text | - | "Freiburg" |
| 2 | **Gutschein Code** | Integer | Format: 15-stellig | `123456789012345` |
| 3 | **Produktname** | Text | - | "Gutschein 50€ - PDF" |
| 4 | **Preis** | Dezimal | #,##0.00 | 50,00€ |
| 5 | **Auftraggeber** | Text | "nein, Auftraggeber" / "PayPal" | "PayPal" |
| 6 | **Privatkauf** | Text | "ja" / "nein" | "ja" |
| 7 | **Order ID** | Integer | Format: 0 | `10582` |
| 8 | **Datum** | Text | DD.MM.YYYY | "13.04.2026" |

---

## 🔧 Normalisierungslogik

### 1. Gutscheincodes

✅ **Akzeptiert**:
- Exakt **15-stellige** Integer-Codes
- Mehrere Codes getrennt durch: `\n`, `,`, `|`, `;`

❌ **Ignoriert**:
- Codes mit <15 oder >15 Stellen
- Nicht-numerische Codes
- Zeilen ohne gültigen Code (werden übersprungen)

### 2. Preise

| Eingabe | Verarbeitung | Ausgabe |
|---------|--------------|---------|
| `50` | OK | `50.00` |
| `50,99` | Komma → Punkt | `50.99` |
| `5099` | >999 → geteilt durch 100 | `50.99` |
| `50.00€` | Aus Produktname extrahiert | `50.00` |
| Leer/NULL | - | `` (leer) |

### 3. Daten

- **Eingabe**: ISO-Format (z.B. `2026-04-13T10:30:45Z`)
- **Ausgabe**: Deutsche Formatierung (z.B. `13.04.2026`)
- **Fehler**: Werden geloggt, Datum bleibt leer

### 4. Zweck / Privatkauf-Flag

| Eingabe | Ausgabe |
|---------|---------|
| `privatkauf` | ✅ `ja` |
| `t` (kurz für true) | ✅ `ja` |
| (leer) | ✅ `ja` |
| `firmenkauf` | ❌ `nein` |
| andere Werte | (leer) |

---

## 🛠️ Hilfsfunktionen (API)

### Wichtige Funktionen

| Funktion | Beschreibung |
|----------|-------------|
| `log(msg)` | Gibt Nachricht mit Zeitstempel aus |
| `input_with_timeout(prompt, timeout, default)` | Benutzereingabe mit Timeout |
| `normalize_price(value)` | Normalisiert Preis zu 2 Dezimalstellen |
| `normalize_voucher_codes(raw_codes)` | Extrahiert 15-stellige Codes |
| `load_existing_data(file)` | Lädt bereits exportierte Daten |
| `merge_rows(existing, new)` | Merged ohne Duplikate |
| `format_date(date_string)` | Konvertiert ISO → DD.MM.YYYY |
| `add_dashboard(wb, rows)` | Erstellt Dashboard mit Charts |
| `add_pdf_export(all_rows, shop_rows, file)` | Generates PDF |

---

## ⚠️ Fehlerbehandlung

| Fehler | Verhalten |
|--------|-----------|
| API-Verbindungsfehler | Wird geloggt, Export stoppt bei diesem Shop |
| Ungültiger Gutscheincode | Zeile wird ignoriert (nicht exportiert) |
| Fehlende reportlab | PDF-Export übersprungen, Warnung ausgegeben |
| Ungültiges Datumsformat | Geloggt, Datum bleibt leer |
| Leere API-Response | Shop-Export beendet, nächster Shop wird verarbeitet |

### Log-Beispiel

```
[10:30:45] Export-Modus: State | Start: 2026-04-12T10:30:45
[10:30:46] API-Aufruf: https://shop1.com/wp-json/wc/v3/orders | page=1
[10:30:47] Freiburg: 25 neue Zeilen (gesamt: 142)
[10:30:48] Schwarzwald: 18 neue Zeilen (gesamt: 89)
[10:30:49] Daten gemergt: 131 existierend + 43 neu = 174 total (Duplikate vermieden)
[10:30:50] Excel erstellt: export.xlsx
[10:30:51] PDF mit Regionen exportiert: export.pdf
```

---

## 📈 Performance & Skalierbarkeit

| Aspekt | Details |
|--------|---------|
| **API-Pagination** | 100 Einträge pro Seite (WooCommerce-Standard) |
| **State-Based Modus** | ⚡ Minimale API-Last durch nur neue Daten |
| **Datenspeicher** | Alle Daten im Memory (watch bei >100k Einträgen) |
| **Timeout** | 5 Sekunden auf Benutzereingabe (verhindert Blockierung) |
| **Duplikat-Vermeidung** | Dictionary-basiert (O(1) Lookup) |

---

## 🔄 Automatisierung

### Windows Task Scheduler

```powershell
# Task erstellen
$action = New-ScheduledTaskAction -Execute "python" -Argument "C:\path\export_woocommerce_cumulative.py"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "WooCommerce Export" -Description "Täglicher Gutschein-Export"
```

### Cron (Linux/Mac)

```bash
# Täglich um 02:00 Uhr ausführen
0 2 * * * cd /path/to/automation && python export_woocommerce_cumulative.py
```

### Mit Eingabe-Automation

Da das Skript Timeouts hat, erfolgt die Input-Verarbeitung automatisch:
- Kein Input = Standard-Wert wird nach 5s verwendet
- Ideal für Automation ohne Änderungen

---

## 🐛 Troubleshooting

### ❌ "API-Aufruf fehlgeschlagen" oder "Connection refused"

**Ursachen**:
- Shop-URL falsch in `config.py`
- API-Credentials ungültig
- WooCommerce REST-API nicht aktiviert

**Lösung**:
```python
# config.py überprüfen
shops = [{
    "name": "Shop1",
    "url": "https://beispiel.de",  # ✅ HTTPS, keine trailing slash
    "ck": "ck_...",
    "cs": "cs_..."
}]
```

### ❌ "Keine Gutscheincodes gefunden"

**Ursachen**:
- Metafeld `_woo_vou_codes` nicht gesetzt
- Gutscheincodes haben nicht exakt 15 Stellen
- Ungültiges Format (mit Buchstaben, etc.)

**Lösung**:
1. WooCommerce Plugin "WooCommerce Vouchers" überprüfen
2. Manuelle Überprüfung einer Bestellung in WooCommerce Admin
3. Logs nach `Gutschein Code` durchsuchen

### ❌ "PDF-Export funktioniert nicht"

**Fehler**: `ModuleNotFoundError: No module named 'reportlab'`

**Lösung**:
```bash
pip install reportlab
```

### ❌ "Duplicate Rows trotz State"

**Ursachen**:
- `state.json` wurde nicht aktualisiert
- Excel-Datei wurde manuell bearbeitet
- State-Modus wurde auf Modus 2 gesetzt

**Lösung**:
```bash
# state.json zurücksetzen (auf Startup neu erstellt)
rm state.json
```

### ❌ "Timeout bei Input / Keine Reaktion"

**Ursache**: Skript wartet auf Eingabe (z.B. in Automation)

**Lösung**: Skript mit Standard-Wert startet automat. nach 5s

---

## 📝 Beispiel-Konfiguration

### Vollständige config.py

```python
# config.py
shops = [
    {
        "name": "ECHT Freiburg Card",
        "url": "https://echtfreiburg.de",
        "ck": "ck_abc123def456ghi789",
        "cs": "cs_xyz789abc456def123"
    },
    {
        "name": "Schwarzwälder City Card",
        "url": "https://schwarzwaldcard.de",
        "ck": "ck_123abc456def789ghi",
        "cs": "cs_789xyz456abc123def"
    },
    {
        "name": "Bad Waldsee City Card",
        "url": "https://badwaldsee.de",
        "ck": "ck_456ghi789abc123def",
        "cs": "cs_456def789xyz123abc"
    }
]

OUTPUT_FILE = "export.xlsx"
STATE_FILE = "state.json"
```

---

## 📚 Weitere Ressourcen

- [WooCommerce REST-API Dokumentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)
- [openpyxl Documentation](https://openpyxl.readthedocs.io/)
- [reportlab Documentation](https://www.reportlab.com/docs/reportlab-userguide.pdf)

---

## 📋 Versionshistorie

| Version | Datum | Änderungen |
|---------|-------|-----------|
| 1.0 | 2026-04-13 | ✨ Initiale Version |
| | | • State-basiertes Update-Management |
| | | • Multi-Shop-Support |
| | | • Excel + PDF-Export |
| | | • Dashboard mit Charts |

**Python-Versionen**: 3.7+

---

## 👤 Support & Kontakt

Bei Fragen oder Fehlern:
- Log-Ausgaben überprüfen
- Siehe **Troubleshooting** Sektion
- config.py validieren
- API-Credentials testen

---

*Dokumentation zuletzt aktualisiert: 13.04.2026*
