import requests
import json
import os
import re
from datetime import datetime
from collections import defaultdict
from openpyxl import Workbook

from config import shops, OUTPUT_FILE, STATE_FILE

# =========================
# HELPERS
# =========================

def log(msg):
    print(f"[{datetime.now().strftime('%H:%M:%S')}] {msg}")


def safe_str(v):
    return "" if v is None else str(v).strip()


def to_int(v):
    try:
        return int(float(str(v).replace(",", ".")))
    except:
        return ""


def format_date(date_string):
    try:
        return datetime.fromisoformat(date_string.replace("Z", "+00:00")).strftime("%d.%m.%Y %H:%M")
    except:
        return ""


def normalize_price(v):
    try:
        return round(float(str(v).replace(",", ".")), 2)
    except:
        return ""


def extract_price_from_name(name):
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*€", name or "")
    return normalize_price(match.group(1)) if match else ""


def get_meta_value(meta_data, key):
    for m in meta_data:
        if m.get("key") == key:
            return m.get("value")
    return ""


def normalize_voucher_codes(raw):
    if not raw:
        return []
    if isinstance(raw, list):
        return [str(x).strip() for x in raw if str(x).strip()]
    raw = str(raw)
    for sep in ["\n", ",", ";", "|"]:
        raw = raw.replace(sep, ",")
    return [x.strip() for x in raw.split(",") if x.strip()]


def map_zweck(v):
    v = safe_str(v).lower()
    if v == "privatkauf":
        return "ja"
    if v == "firmenkauf":
        return "nein"
    return ""


# =========================
# STATE
# =========================

def load_last_run():
    if os.path.exists(STATE_FILE):
        return json.load(open(STATE_FILE)).get("last_run")
    return None


def save_last_run():
    json.dump({"last_run": datetime.utcnow().isoformat()}, open(STATE_FILE, "w"))


# =========================
# API
# =========================

def fetch_orders(shop, start_date):
    page = 1
    orders = []

    while True:
        try:
            r = requests.get(
                f"{shop['url']}/wp-json/wc/v3/orders",
                auth=(shop["ck"], shop["cs"]),
                params={"per_page": 100, "page": page, "after": start_date},
                timeout=30
            )
            r.raise_for_status()
            data = r.json()

            if not data:
                break

            orders.extend(data)
            page += 1

        except Exception as e:
            log(f"Fehler {shop['name']}: {e}")
            break

    return orders


# =========================
# CORE LOGIC
# =========================

def extract_voucher_codes(item_meta, order_meta, item_id):
    # 1. item_meta
    raw = get_meta_value(item_meta, "_woo_vou_codes")

    # 2. fallback: order_meta → vou_details
    if not raw:
        vou_details = get_meta_value(order_meta, "_woo_vou_meta_order_details")

        if isinstance(vou_details, str):
            try:
                vou_details = json.loads(vou_details)
            except:
                vou_details = {}

        if isinstance(vou_details, dict) and item_id in vou_details:
            raw = vou_details[item_id].get("codes")

    codes = normalize_voucher_codes(raw)
    return codes if codes else [""]


def transform_orders(orders, shop_name, existing_keys):
    rows = []

    for order in orders:
        order_id = to_int(order.get("id"))
        order_date = format_date(order.get("date_created"))
        order_meta = order.get("meta_data", [])

        raw_zweck = (
            get_meta_value(order_meta, "_billing_options") or
            get_meta_value(order_meta, "billing_options")
        )
        zweck = map_zweck(raw_zweck)

        for item in order.get("line_items", []):
            item_id = str(item.get("id"))
            name = safe_str(item.get("name"))
            qty = to_int(item.get("quantity"))
            item_meta = item.get("meta_data", [])

            price = normalize_price(get_meta_value(item_meta, "_woo_vou_voucher_price"))
            if not price:
                price = extract_price_from_name(name)

            codes = extract_voucher_codes(item_meta, order_meta, item_id)

            for code in codes:
                key = (order_id, code)

                # 🔥 Duplikat vermeiden
                if key in existing_keys:
                    continue

                row = {
                    "Shop": shop_name,
                    "Order ID": order_id,
                    "Order Date": order_date,
                    "Produktname": name,
                    "Menge": qty,
                    "Preis": price,
                    "Zweck": zweck,
                    "Gutschein Code": code
                }

                rows.append(row)
                existing_keys.add(key)

    return rows


# =========================
# EXCEL
# =========================

def write_excel(rows):
    wb = Workbook()
    ws = wb.active
    ws.title = "Alle Shops"

    headers = ["Shop", "Gutschein Code", "Zweck", "Order Date", "Order ID", "Produktname", "Menge", "Preis"]
    ws.append(headers)

    for r in rows:
        ws.append([r.get(h) for h in headers])

    wb.save(OUTPUT_FILE)


# =========================
# MAIN
# =========================

def main():
    log("Start")

    start_date = load_last_run() or datetime.now().replace(day=1).isoformat()
    existing_keys = set()
    all_rows = []

    for shop in shops:
        log(f"Lade {shop['name']}")

        orders = fetch_orders(shop, start_date)
        rows = transform_orders(orders, shop["name"], existing_keys)

        log(f"{shop['name']}: {len(rows)} neue Zeilen")
        all_rows.extend(rows)

    write_excel(all_rows)
    save_last_run()

    log(f"Fertig: {len(all_rows)} Zeilen")


if __name__ == "__main__":
    main()