import requests
import json

shop = {
        "name": "ECHT FREIBURG CARD",
        "url": "https://echtfreiburgcard.de",
        "ck": "ck_2a019184c32669536d6471c4b1d6f4c89e7e1f05",
        "cs": "cs_fe34bbdf7e14d1eba59d4bad5d56f1adabc7976d"
    }


response = requests.get(
    f"{shop['url']}/wp-json/wc/v3/orders",
    auth=(shop["ck"], shop["cs"]),
    params={
        "per_page": 1,
        "page": 1
    },
    timeout=30
)

response.raise_for_status()
orders = response.json()

order = orders[0]

print("ORDER ID:", order.get("id"))
print("\n=== BILLING ===")
print(json.dumps(order.get("billing", {}), indent=2, ensure_ascii=False))

print("\n=== ORDER META ===")
for meta in order.get("meta_data", []):
    key = str(meta.get("key", ""))
    value = meta.get("value", "")
    print(f"{key} = {json.dumps(value, ensure_ascii=False)}")