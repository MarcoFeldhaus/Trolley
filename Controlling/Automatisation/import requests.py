import requests
import json
import os
from datetime import datetime
from collections import defaultdict
from openpyxl import Workbook, load_workbook
from openpyxl.chart import BarChart, Reference, LineChart
from openpyxl.styles import Font

from config import shops, OUTPUT_FILE, STATE_FILE

# =========================
# STATE HANDLING
# =========================

def load_last_run():
    if os.path.exists(STATE_FILE):
        with open(STATE_FILE, "r") as f:
            try:
                return json.load(f).get("last_run")
            except json.JSONDecodeError:
                return None
    return None

def save_last_run():
    with open(STATE_FILE, "w") as f:
        json.dump({"last_run": datetime.utcnow().isoformat()}, f)

# =========================
# API FETCH
# =========================

def fetch_orders(shop, start_date):
    page = 1
    all_orders = []

    while True:
        params = {"per_page": 100, "page": page, "after": start_date}
        try:
            response = requests.get(
                f"{shop['url']}/wp-json/wc/v3/orders",
                auth=(shop["ck"], shop["cs"]),
                params=params,
                timeout=30
            )
            response.raise_for_status()
            try:
                data = response.json()
            except json.JSONDecodeError:
                print(f"Achtung: Ungültige JSON-Antwort von {shop['name']} (Seite {page})")
                print(response.text[:200])
                break

            if not data:
                break

            all_orders.extend(data)
            page += 1
        except requests.RequestException as e:
            print(f"Fehler bei Shop {shop['name']}: {e}")
            break

    return all_orders

# =========================
# TRANSFORM ORDERS
# =========================

def transform_orders(orders, shop_name):
    rows = []
    for order in orders:
        order_id = order.get("id")
        date = order.get("date_created", "")[:10]
        for item in order.get("line_items", []):
            rows.append({
                "Shop": shop_name,
                "Order ID": order_id,
                "Datum": date,
                "Produkt": item.get("name"),
                "Menge": item.get("quantity"),
                "Preis": round(float(item.get("price", 0)), 2)
            })
    return rows

# =========================
# LOAD EXISTING EXCEL
# =========================

def load_existing():
    if not os.path.exists(OUTPUT_FILE):
        return [], set()
    wb = load_workbook(OUTPUT_FILE)
    if "Alle Shops" not in wb.sheetnames:
        return [], set()
    ws = wb["Alle Shops"]
    headers = [c.value for c in ws[1]]
    rows = []
    keys = set()
    for r in ws.iter_rows(min_row=2, values_only=True):
        d = dict(zip(headers, r))
        rows.append(d)
        keys.add((d["Order ID"], d["Produkt"]))
    return rows, keys

# =========================
# BUILD MONTHLY REPORT
# =========================

def build_monthly_report(rows):
    report = defaultdict(lambda: {"umsatz": 0, "bestellungen": set()})
    for r in rows:
        if not r.get("Datum"):
            continue
        month = r["Datum"][:7]
        key = (r["Shop"], month)
        report[key]["umsatz"] += float(r.get("Preis", 0))
        report[key]["bestellungen"].add(r["Order ID"])
    final = []
    for (shop, month), data in report.items():
        final.append({
            "Shop": shop,
            "Monat": month,
            "Umsatz": round(data["umsatz"], 2),
            "Bestellungen": len(data["bestellungen"])
        })
    return final

# =========================
# DASHBOARD + KPI
# =========================

def add_dashboard(wb, monthly_rows, all_rows):
    ws = wb.create_sheet("Dashboard")

    # Aggregationen
    revenue_per_month = defaultdict(float)
    revenue_per_shop = defaultdict(float)
    orders_per_month = defaultdict(int)
    for r in monthly_rows:
        revenue_per_month[r["Monat"]] += r["Umsatz"]
        orders_per_month[r["Monat"]] += r["Bestellungen"]
        revenue_per_shop[r["Shop"]] += r["Umsatz"]

    # KPI
    total_revenue = sum(revenue_per_month.values())
    total_orders = sum(orders_per_month.values())
    avg_order = total_revenue / total_orders if total_orders else 0

    ws["A1"] = "KPI"
    ws["A2"] = "Umsatz"
    ws["B2"] = total_revenue
    ws["A3"] = "Bestellungen"
    ws["B3"] = total_orders
    ws["A4"] = "Ø Bestellwert"
    ws["B4"] = round(avg_order, 2)
    ws["B2"].number_format = '#,##0.00'
    ws["B4"].number_format = '#,##0.00'

    # Umsatz pro Monat
    ws["A6"], ws["B6"] = "Monat", "Umsatz"
    row = 7
    for m in sorted(revenue_per_month):
        ws.cell(row=row, column=1, value=m)
        ws.cell(row=row, column=2, value=revenue_per_month[m])
        row += 1
    chart = LineChart()
    data = Reference(ws, min_col=2, min_row=6, max_row=row-1)
    cats = Reference(ws, min_col=1, min_row=7, max_row=row-1)
    chart.add_data(data, titles_from_data=True)
    chart.set_categories(cats)
    chart.title = "Umsatz pro Monat"
    chart.style = 10
    ws.add_chart(chart, "D6")

    # Wachstum (MoM)
    ws["D6"], ws["E6"] = "Wachstum Monat %", ""
    prev = None
    row_growth = 7
    for m in sorted(revenue_per_month):
        current = revenue_per_month[m]
        growth = ((current - prev) / prev * 100) if prev else 0
        ws.cell(row=row_growth, column=4, value=growth)
        ws.cell(row=row_growth, column=4).number_format = '0.00%'
        row_growth += 1
        prev = current

    # Top 5 Produkte
    product_sales = defaultdict(float)
    for r in all_rows:
        product_sales[r["Produkt"]] += r["Preis"] * r["Menge"]
    top5 = sorted(product_sales.items(), key=lambda x: x[1], reverse=True)[:5]
    ws["A20"], ws["B20"] = "Top Produkte", "Umsatz"
    r_top = 21
    for name, value in top5:
        ws.cell(row=r_top, column=1, value=name)
        ws.cell(row=r_top, column=2, value=round(value,2))
        ws.cell(row=r_top, column=2).number_format = '#,##0.00'
        r_top += 1

    # Umsatz pro Shop
    start_shop = r_top + 1
    ws.cell(row=start_shop, column=1, value="Shop")
    ws.cell(row=start_shop, column=2, value="Umsatz")
    r_shop = start_shop + 1
    for s, v in revenue_per_shop.items():
        ws.cell(row=r_shop, column=1, value=s)
        ws.cell(row=r_shop, column=2, value=v)
        ws.cell(row=r_shop, column=2).number_format = '#,##0.00'
        r_shop += 1
    chart2 = BarChart()
    data2 = Reference(ws, min_col=2, min_row=start_shop, max_row=r_shop-1)
    cats2 = Reference(ws, min_col=1, min_row=start_shop+1, max_row=r_shop-1)
    chart2.add_data(data2, titles_from_data=True)
    chart2.set_categories(cats2)
    chart2.title = "Umsatz pro Shop"
    chart2.style = 10
    ws.add_chart(chart2, "D20")

# =========================
# WRITE EXCEL
# =========================

def write_excel(all_rows, monthly_rows):
    wb = Workbook()
    ws_all = wb.active
    ws_all.title = "Alle Shops"
    headers = ["Shop", "Order ID", "Datum", "Produkt", "Menge", "Preis"]
    ws_all.append(headers)
    for r in all_rows:
        ws_all.append([r.get(h) for h in headers])
     # separates Blatt pro Shop
    shops_set = sorted({r["Shop"] for r in all_rows})
    for shop in shops_set:
        ws = wb.create_sheet(shop)
        ws.append(headers)
        for r in all_rows:
            if r["Shop"] == shop:
                ws.append([r.get(h) for h in headers])
    
    # Monatsreport
    ws_month = wb.create_sheet("Monatsreport")
    headers_m = ["Shop", "Monat", "Umsatz", "Bestellungen"]
    ws_month.append(headers_m)
    for r in sorted(monthly_rows, key=lambda x: (x["Monat"], x["Shop"])):
        ws_month.append([r.get(h) for h in headers_m])
    wb.save(OUTPUT_FILE)

# =========================
# MAIN
# =========================

def main():
    last_run = load_last_run()
    start_date = last_run if last_run else datetime.now().replace(day=1).isoformat()
    print("Startdatum:", start_date)

    existing_rows, existing_keys = load_existing()
    new_rows = []

    for shop in shops:
        orders = fetch_orders(shop, start_date)
        rows = transform_orders(orders, shop["name"])
        for r in rows:
            key = (r["Order ID"], r["Produkt"])
            if key not in existing_keys:
                new_rows.append(r)

    all_rows = existing_rows + new_rows
    monthly = build_monthly_report(all_rows)
    write_excel(all_rows, monthly)
    save_last_run()
    print(f"Neue Zeilen: {len(new_rows)}")
    print("Fertig!")

if __name__ == "__main__":
    main()