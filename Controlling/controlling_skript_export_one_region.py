import requests
import csv
from datetime import datetime
import re

def extract_price_from_name(name):
    """
    Extrahiert z. B. '20€ Gutschein' -> 20
    """
    if not name:
        return ""

    match = re.search(r'(\d+)\s*€', name)
    if match:
        return match.group(1)

    return ""


def normalize_price(value):
    """
    Wandelt z. B. 5000 -> 50.00
    """
    if not value:
        return ""

    try:
        value = float(value)

        # Wenn Wert ungewöhnlich groß → vermutlich Cent
        if value > 1000:
            value = value / 100

        return round(value, 2)
    except:
        return value


def safe_str(value):
    if value is None:
        return ""
    return str(value).strip()


def format_date(date_string):
    if not date_string:
        return ""
    try:
        dt = datetime.fromisoformat(date_string.replace("Z", "+00:00"))
        return dt.strftime("%d.%m.%Y %H:%M")
    except Exception:
        return date_string


def format_expiry_date(date_string):
    if not date_string:
        return ""
    try:
        dt = datetime.strptime(date_string, "%Y-%m-%d %H:%M:%S")
        return dt.strftime("%d.%m.%Y %H:%M")
    except Exception:
        return date_string


def get_meta_value(meta_data, key_name):
    for meta in meta_data:
        if meta.get("key") == key_name:
            return meta.get("value")
    return ""


def normalize_voucher_codes(raw_codes):
    """
    Macht aus _woo_vou_codes immer eine Liste einzelner Gutscheincodes.
    Unterstützt String, Liste und verschiedene Trennzeichen.
    """
    if raw_codes is None:
        return []

    if isinstance(raw_codes, list):
        return [str(code).strip() for code in raw_codes if str(code).strip()]

    raw = str(raw_codes).strip()
    if not raw:
        return []

    separators = ["\n", ",", "|", ";"]
    codes = [raw]

    for sep in separators:
        new_codes = []
        for chunk in codes:
            parts = chunk.split(sep)
            new_codes.extend(parts)
        codes = new_codes

    return [code.strip() for code in codes if code.strip()]




shops = [
      {
        "name": "ECHT FREIBURG CARD",
        "url": "https://echtfreiburgcard.de",
        "ck": "ck_2a019184c32669536d6471c4b1d6f4c89e7e1f05",
        "cs": "cs_fe34bbdf7e14d1eba59d4bad5d56f1adabc7976d"
    }
]

# False = ab dem 1. des aktuellen Monats
# True  = ab dem 15. des aktuellen Monats
USE_DAY_15 = False

today = datetime.now()
start_date = today.replace(day=15 if USE_DAY_15 else 1).strftime("%Y-%m-%dT00:00:00")



all_rows = []

for shop in shops:
    page = 1

    while True:
        response = requests.get(
            f"{shop['url']}/wp-json/wc/v3/orders",
            auth=(shop["ck"], shop["cs"]),
            params={
                "per_page": 100,
                "page": page,
                "after": start_date
            },
            timeout=30
        )

        response.raise_for_status()
        orders = response.json()

        if not orders:
            break

        for order in orders:
            billing = order.get("billing", {})
            order_meta = order.get("meta_data", [])
            line_items = order.get("line_items", [])

            customer_name = f"{safe_str(billing.get('first_name'))} {safe_str(billing.get('last_name'))}".strip()
            email = safe_str(billing.get("email"))
            phone = safe_str(billing.get("phone"))

            address_parts = [
                safe_str(billing.get("address_1")),
                safe_str(billing.get("address_2")),
            ]
            address_line_1 = " ".join([p for p in address_parts if p]).strip()

            city_line_parts = [
                safe_str(billing.get("city")),
                safe_str(billing.get("state")),
                safe_str(billing.get("country")),
                safe_str(billing.get("postcode")),
            ]
            address_line_2 = " ".join([p for p in city_line_parts if p]).strip()

            order_id = safe_str(order.get("id"))
            order_date = format_date(order.get("date_created"))
            payment_method = safe_str(order.get("payment_method_title"))
            order_total = normalize_price(order.get("total"))
            order_discount = normalize_price(order.get("discount_total"))
            status = safe_str(order.get("status"))

            zweck = (
                safe_str(get_meta_value(order_meta, "_billing_options")) or
                safe_str(get_meta_value(order_meta, "billing_options"))
            )

            paypal_order_id = safe_str(get_meta_value(order_meta, "_ppcp_paypal_order_id"))
            fee_paypal = normalize_price(get_meta_value(order_meta, "_paypal_fee"))
            net_paypal = normalize_price(get_meta_value(order_meta, "_paypal_net"))

            vou_details = get_meta_value(order_meta, "_woo_vou_meta_order_details")
            if not isinstance(vou_details, dict):
                vou_details = {}

            for item in line_items:
                item_id = str(item.get("id", ""))
                item_name = safe_str(item.get("name"))
                if "gutschein pdf" in item_name.lower():
                        pauschale_or_price = "Pauschale Auftraggeber"
                else:
                    pauschale_or_price = "PayPal"

                item_quantity = safe_str(item.get("quantity"))
                item_meta = item.get("meta_data", [])
               
                recipient_email_obj = get_meta_value(item_meta, "_woo_vou_recipient_email")
                recipient_message_obj = get_meta_value(item_meta, "_woo_vou_recipient_message")

                recipient_email = ""
                if isinstance(recipient_email_obj, dict):
                    recipient_email = safe_str(recipient_email_obj.get("value"))
                else:
                    recipient_email = safe_str(recipient_email_obj)

                recipient_message = ""
                if isinstance(recipient_message_obj, dict):
                    recipient_message = safe_str(recipient_message_obj.get("value"))
                else:
                    recipient_message = safe_str(recipient_message_obj)


                voucher_price = safe_str(get_meta_value(item_meta, "_woo_vou_voucher_price"))
                # 👉 Wenn leer → aus Produktnamen ziehen
                if not voucher_price:
                    voucher_price = extract_price_from_name(item_name)

                raw_voucher_codes = get_meta_value(item_meta, "_woo_vou_codes")
                voucher_codes = normalize_voucher_codes(raw_voucher_codes)

                if not voucher_codes:
                    voucher_codes = [""]

                exp_date = ""
                pdf_template = ""

                if item_id in vou_details and isinstance(vou_details[item_id], dict):
                    exp_date = format_expiry_date(vou_details[item_id].get("exp_date", ""))
                    pdf_template = safe_str(vou_details[item_id].get("pdf_template", ""))

                for voucher_code in voucher_codes:
                    all_rows.append({
                        "Shop": shop["name"],
                        "Gutschein Code": voucher_code,
                        "Produktname": item_name,
                        "Pauschale oder PayPal-Preis": pauschale_or_price,
                        "Preis": voucher_price,
                        "Name": customer_name,
                        "Email": email,
                        "Address 1": address_line_1,
                        "Address 2": address_line_2,
                        "Phone": phone,
                        "Zweck": zweck,
                        "Order ID": order_id,
                        "Order Date": order_date,
                        "Payment Method": payment_method,
                        "PayPal Order ID": paypal_order_id,
                        "Order Total": order_total,
                        "Order Discount": order_discount,
                        "PayPal Fee": fee_paypal,
                        "PayPal Net": net_paypal,
                        "Expires": exp_date,
                        "PDF Template": pdf_template,
                        "E-Mail Adresse des Empfängers": recipient_email,
                        "Nachricht für den Empfänger": recipient_message,
                        "Status": status,
                        "Menge": item_quantity
                    })

        page += 1

if all_rows:
    fieldnames = [
        "Shop",
        "Gutschein Code",
        "Produktname",
        "Pauschale oder PayPal-Preis",
        "Preis",
        "Name",
        "Email",
        "Address 1",
        "Address 2",
        "Phone",
        "Zweck",
        "Order ID",
        "Order Date",
        "Payment Method",
        "PayPal Order ID",
        "Order Total",
        "Order Discount",
        "PayPal Fee",
        "PayPal Net",
        "Expires",
        "PDF Template",
        "E-Mail Adresse des Empfängers",
        "Nachricht für den Empfänger",
        "Status",
        "Menge"
    ]

    with open("controlling_export_one_region.csv", "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(all_rows)

    print(f"CSV erstellt: alle_bestellungen_controlling.csv ({len(all_rows)} Zeilen ab {start_date})")
else:
    print(f"Keine Bestellungen gefunden ab {start_date}")