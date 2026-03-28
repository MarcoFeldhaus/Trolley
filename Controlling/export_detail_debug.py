import requests
import json
from datetime import datetime

shops = [
    {
        "name": "ECHT FREIBURG CARD",
        "url": "https://echtfreiburgcard.de",
        "ck": "ck_2a019184c32669536d6471c4b1d6f4c89e7e1f05",
        "cs": "cs_fe34bbdf7e14d1eba59d4bad5d56f1adabc7976d"
    }
]

USE_DAY_15 = False

today = datetime.now()
start_date = today.replace(day=15 if USE_DAY_15 else 1).strftime("%Y-%m-%dT00:00:00")

interesting_words = [
    "zweck",
    "purpose",
    "voucher",
    "vou",
    "gift",
    "code",
    "recipient",
    "email",
    "message",
    "expire",
    "expiry",
    "paypal",
    "payer"
]

def is_interesting(key):
    key_l = str(key).lower()
    return any(word in key_l for word in interesting_words)

response = requests.get(
    f"{shops[0]['url']}/wp-json/wc/v3/orders",
    auth=(shops[0]["ck"], shops[0]["cs"]),
    params={
        "per_page": 5,
        "page": 1,
        "after": start_date
    },
    timeout=30
)

response.raise_for_status()
orders = response.json()

for order in orders:
    print("\n==============================")
    print(f"ORDER ID: {order.get('id')}")
    print("==============================")

    for meta in order.get("meta_data", []):
        key = meta.get("key", "")
        if is_interesting(key):
            print(f"[ORDER META] {key} = {json.dumps(meta.get('value'), ensure_ascii=False)}")

    for item in order.get("line_items", []):
        print(f"\nLINE ITEM: {item.get('name')}")
        for meta in item.get("meta_data", []):
            key = meta.get("key", "")
            if is_interesting(key):
                print(f"[ITEM META] {key} = {json.dumps(meta.get('value'), ensure_ascii=False)}")