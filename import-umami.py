#!/usr/bin/env python3
"""
Minilytics Umami Import Tool
Imports an Umami export (.zip archive or folder containing website_event.csv and event_data.csv)
into Minilytics isolated SQLite databases.
"""

import argparse
import csv
import json
import os
import sqlite3
import sys
import tempfile
import zipfile
from datetime import datetime, timezone

COUNTRY_MAP = {
    "AF": "Afghanistan",
    "AL": "Albania",
    "DZ": "Algeria",
    "AD": "Andorra",
    "AO": "Angola",
    "AG": "Antigua and Barbuda",
    "AR": "Argentina",
    "AM": "Armenia",
    "AU": "Australia",
    "AT": "Austria",
    "AZ": "Azerbaijan",
    "BS": "Bahamas",
    "BH": "Bahrain",
    "BD": "Bangladesh",
    "BB": "Barbados",
    "BY": "Belarus",
    "BE": "Belgium",
    "BZ": "Belize",
    "BJ": "Benin",
    "BT": "Bhutan",
    "BO": "Bolivia",
    "BA": "Bosnia and Herzegovina",
    "BW": "Botswana",
    "BR": "Brazil",
    "BN": "Brunei",
    "BG": "Bulgaria",
    "BF": "Burkina Faso",
    "BI": "Burundi",
    "KH": "Cambodia",
    "CM": "Cameroon",
    "CA": "Canada",
    "CV": "Cape Verde",
    "CF": "Central African Republic",
    "TD": "Chad",
    "CL": "Chile",
    "CN": "China",
    "CO": "Colombia",
    "KM": "Comoros",
    "CG": "Congo",
    "CD": "Congo (DRC)",
    "CR": "Costa Rica",
    "CI": "Ivory Coast",
    "HR": "Croatia",
    "CU": "Cuba",
    "CY": "Cyprus",
    "CZ": "Czech Republic",
    "DK": "Denmark",
    "DJ": "Djibouti",
    "DO": "Dominican Republic",
    "EC": "Ecuador",
    "EG": "Egypt",
    "SV": "El Salvador",
    "EE": "Estonia",
    "ET": "Ethiopia",
    "FI": "Finland",
    "FR": "France",
    "GA": "Gabon",
    "GM": "Gambia",
    "GE": "Georgia",
    "DE": "Germany",
    "GH": "Ghana",
    "GR": "Greece",
    "GT": "Guatemala",
    "GN": "Guinea",
    "HT": "Haiti",
    "HN": "Honduras",
    "HU": "Hungary",
    "IS": "Iceland",
    "IN": "India",
    "ID": "Indonesia",
    "IR": "Iran",
    "IQ": "Iraq",
    "IE": "Ireland",
    "IL": "Israel",
    "IT": "Italy",
    "JM": "Jamaica",
    "JP": "Japan",
    "JO": "Jordan",
    "KZ": "Kazakhstan",
    "KE": "Kenya",
    "KR": "South Korea",
    "KW": "Kuwait",
    "LV": "Latvia",
    "LB": "Lebanon",
    "LY": "Libya",
    "LT": "Lithuania",
    "LU": "Luxembourg",
    "MG": "Madagascar",
    "MY": "Malaysia",
    "ML": "Mali",
    "MT": "Malta",
    "MR": "Mauritania",
    "MU": "Mauritius",
    "MX": "Mexico",
    "MD": "Moldova",
    "MC": "Monaco",
    "MN": "Mongolia",
    "ME": "Montenegro",
    "MA": "Morocco",
    "MZ": "Mozambique",
    "NA": "Namibia",
    "NP": "Nepal",
    "NL": "Netherlands",
    "NZ": "New Zealand",
    "NI": "Nicaragua",
    "NE": "Niger",
    "NG": "Nigeria",
    "NO": "Norway",
    "OM": "Oman",
    "PK": "Pakistan",
    "PA": "Panama",
    "PY": "Paraguay",
    "PE": "Peru",
    "PH": "Philippines",
    "PL": "Poland",
    "PT": "Portugal",
    "QA": "Qatar",
    "RO": "Romania",
    "RU": "Russia",
    "RW": "Rwanda",
    "SA": "Saudi Arabia",
    "SN": "Senegal",
    "RS": "Serbia",
    "SG": "Singapore",
    "SK": "Slovakia",
    "SI": "Slovenia",
    "ZA": "South Africa",
    "ES": "Spain",
    "LK": "Sri Lanka",
    "SD": "Sudan",
    "SE": "Sweden",
    "CH": "Switzerland",
    "SY": "Syria",
    "TW": "Taiwan",
    "TZ": "Tanzania",
    "TH": "Thailand",
    "TG": "Togo",
    "TN": "Tunisia",
    "TR": "Turkey",
    "UG": "Uganda",
    "UA": "Ukraine",
    "AE": "United Arab Emirates",
    "GB": "United Kingdom",
    "US": "United States",
    "UY": "Uruguay",
    "UZ": "Uzbekistan",
    "VE": "Venezuela",
    "VN": "Vietnam",
    "YE": "Yemen",
    "ZM": "Zambia",
    "ZW": "Zimbabwe",
    "MQ": "Martinique",
    "GP": "Guadeloupe",
    "RE": "Reunion",
    "GF": "French Guiana",
}


def normalize_browser(b):
    if not b or b == r"\N":
        return "Unknown"
    b_low = b.strip().lower()
    if b_low in ("ios", "safari"):
        return "Safari"
    if b_low == "crios":
        return "Chrome (iOS)"
    if b_low == "fxios":
        return "Firefox (iOS)"
    if b_low == "edge-ios":
        return "Edge (iOS)"
    if "edge" in b_low:
        return "Edge"
    if "chrome" in b_low:
        return "Chrome"
    if "firefox" in b_low:
        return "Firefox"
    if "opera" in b_low or "opr" in b_low:
        return "Opera"
    if "samsung" in b_low:
        return "Samsung Internet"
    if "webview" in b_low:
        return "WebView"
    if b_low == "facebook":
        return "Facebook In-App"
    if b_low == "instagram":
        return "Instagram In-App"
    if b_low == "android":
        return "Android Browser"
    return b.capitalize()


def normalize_os(o):
    if not o or o == r"\N":
        return "Unknown"
    o_str = o.strip()
    low = o_str.lower()
    if low in ("mac os", "macos", "os x"):
        return "macOS"
    if low.startswith("windows"):
        return o_str
    if low in ("android os", "android"):
        return "Android"
    if low == "ios":
        return "iOS"
    if low == "linux":
        return "Linux"
    if "chrome os" in low:
        return "Chrome OS"
    return o_str


def normalize_device(d):
    if not d or d == r"\N":
        return "Desktop"
    d_low = d.strip().lower()
    if d_low == "mobile":
        return "Mobile"
    if d_low == "tablet":
        return "Tablet"
    if d_low in ("laptop", "desktop"):
        return "Desktop"
    return d.capitalize()


def find_files(dir_path):
    files = {"website_event": None, "event_data": None, "session_data": None}
    for root, _, filenames in os.walk(dir_path):
        for f in filenames:
            fl = f.lower()
            if "website_event" in fl and fl.endswith(".csv"):
                files["website_event"] = os.path.join(root, f)
            elif "event_data" in fl and fl.endswith(".csv"):
                files["event_data"] = os.path.join(root, f)
            elif "session_data" in fl and fl.endswith(".csv"):
                files["session_data"] = os.path.join(root, f)
    return files


def import_umami(source_path, site_id=None, site_name=None, domain=None):
    temp_dir = None
    work_dir = source_path

    if os.path.isfile(source_path) and source_path.lower().endswith(".zip"):
        print(f"[1/5] Extracting ZIP export: {source_path}")
        temp_dir = tempfile.mkdtemp(prefix="minilytics_umami_")
        with zipfile.ZipFile(source_path, "r") as z:
            z.extractall(temp_dir)
        work_dir = temp_dir
    elif os.path.isfile(source_path) and source_path.lower().endswith(".csv"):
        work_dir = os.path.dirname(source_path)

    try:
        csv_files = find_files(work_dir)
        if not csv_files["website_event"]:
            print("Error: Could not find website_event.csv in source!")
            sys.exit(1)

        # 1. Load event_data.csv if available
        event_data_map = {}
        if csv_files["event_data"] and os.path.exists(csv_files["event_data"]):
            print(
                f"[2/5] Parsing event parameters from {os.path.basename(csv_files['event_data'])}..."
            )
            with open(
                csv_files["event_data"], "r", encoding="utf-8", errors="replace"
            ) as f:
                reader = csv.DictReader(f)
                for row in reader:
                    eid = row.get("event_id")
                    key = row.get("data_key")
                    if not eid or not key:
                        continue

                    num_val = row.get("number_value")
                    str_val = row.get("string_value")
                    dtype = row.get("data_type", "1")

                    val = str_val if str_val != r"\N" else None
                    if dtype == "2" and num_val and num_val != r"\N":
                        try:
                            val = float(num_val) if "." in num_val else int(num_val)
                        except ValueError:
                            val = num_val
                    elif dtype == "3":
                        val = str_val == "true" or str_val == "1"

                    if eid not in event_data_map:
                        event_data_map[eid] = {}
                    event_data_map[eid][key] = val
            print(f"      Loaded {len(event_data_map):,} event parameter sets.")

        # 2. Inspect first rows to auto-detect domain and site id
        detected_hostnames = {}
        with open(
            csv_files["website_event"], "r", encoding="utf-8", errors="replace"
        ) as f:
            reader = csv.DictReader(f)
            for i, r in enumerate(reader):
                h = (r.get("hostname") or "").strip()
                if h and h != r"\N":
                    detected_hostnames[h] = detected_hostnames.get(h, 0) + 1
                if i >= 1000:
                    break

        primary_host = (
            sorted(detected_hostnames.items(), key=lambda x: x[1], reverse=True)[0][0]
            if detected_hostnames
            else "my_site"
        )

        if not site_id:
            site_id = (
                "".join(
                    c
                    for c in primary_host.split(".")[0].lower()
                    if c.isalnum() or c in ("_", "-")
                )
                or "umami_site"
            )
        if not site_name:
            site_name = primary_host.replace(".", " ").title()
        if not domain:
            domain = primary_host

        print(
            f"[3/5] Target Website: ID='{site_id}', Name='{site_name}', Domain='{domain}'"
        )

        # 3. Setup data directory and target SQLite DB
        base_dir = os.path.dirname(os.path.abspath(__file__))
        data_dir = os.path.join(base_dir, "data")
        os.makedirs(data_dir, exist_ok=True)

        db_path = os.path.join(data_dir, f"{site_id}.db")
        sites_path = os.path.join(data_dir, "sites.json")

        # Update sites.json
        sites = []
        if os.path.exists(sites_path):
            try:
                with open(sites_path, "r", encoding="utf-8") as f:
                    sites = json.load(f)
            except Exception:
                sites = []

        site_exists = any(s.get("id") == site_id for s in sites)
        if not site_exists:
            sites.append(
                {
                    "id": site_id,
                    "name": site_name,
                    "domain": domain,
                    "created_at": datetime.now(timezone.utc).strftime(
                        "%Y-%m-%d %H:%M:%S"
                    ),
                }
            )
            with open(sites_path, "w", encoding="utf-8") as f:
                json.dump(sites, f, indent=2)

        # 4. Connect to SQLite DB
        conn = sqlite3.connect(db_path)
        cur = conn.cursor()
        cur.execute("PRAGMA journal_mode = WAL;")
        cur.execute("PRAGMA synchronous = OFF;")
        cur.execute("""CREATE TABLE IF NOT EXISTS user_activity (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            session_id TEXT NOT NULL, 
            visitor_id TEXT,
            action TEXT NOT NULL, 
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )""")
        cur.execute(
            "CREATE INDEX IF NOT EXISTS idx_ua_timestamp ON user_activity(timestamp)"
        )
        cur.execute(
            "CREATE INDEX IF NOT EXISTS idx_ua_session ON user_activity(session_id)"
        )
        cur.execute(
            "CREATE INDEX IF NOT EXISTS idx_ua_visitor ON user_activity(visitor_id)"
        )

        # 5. Stream and insert events in batch
        print(f"[4/5] Importing events into {os.path.basename(db_path)}...")
        total_rows = 0
        pageviews = 0
        custom_events = 0
        sessions = set()
        min_date = "9999-99-99"
        max_date = "0000-00-00"

        insert_batch = []
        batch_size = 2000

        with open(
            csv_files["website_event"], "r", encoding="utf-8", errors="replace"
        ) as f:
            reader = csv.DictReader(f)
            for row in reader:
                session_id = row.get("session_id") or "unknown_sess"
                distinct_id = row.get("distinct_id")
                visitor_id = (
                    distinct_id if distinct_id and distinct_id != r"\N" else session_id
                )
                event_id = row.get("event_id")
                event_type = str(row.get("event_type", "1"))
                raw_event_name = row.get("event_name") or ""
                hostname = row.get("hostname") if row.get("hostname") != r"\N" else ""
                url_path = row.get("url_path") if row.get("url_path") != r"\N" else "/"
                url_query = (
                    row.get("url_query") if row.get("url_query") != r"\N" else ""
                )
                created_at = row.get("created_at") or datetime.utcnow().strftime(
                    "%Y-%m-%d %H:%M:%S"
                )

                sessions.add(session_id)
                if created_at < min_date:
                    min_date = created_at
                if created_at > max_date:
                    max_date = created_at

                is_pageview = event_type == "1" or not raw_event_name
                action_name = "pageview" if is_pageview else raw_event_name

                if is_pageview:
                    pageviews += 1
                else:
                    custom_events += 1

                # Reconstruct referrer
                ref_dom = row.get("referrer_domain")
                ref_path = row.get("referrer_path") or ""
                referrer = None
                if ref_dom and ref_dom != r"\N":
                    referrer = (
                        ref_dom
                        if ref_dom.startswith("http")
                        else f"https://{ref_dom}{'/' + ref_path.lstrip('/') if ref_path else ''}"
                    )

                # Reconstruct URL
                full_url = None
                if hostname:
                    full_url = f"https://{hostname}{url_path}{'?' + url_query if url_query else ''}"

                # Country mapping
                country_code = (row.get("country") or "").upper()
                if country_code == r"\N":
                    country_code = ""
                country_name = (
                    COUNTRY_MAP.get(country_code, country_code)
                    if country_code
                    else None
                )

                # Build data payload
                data = {
                    "path": url_path,
                    "title": row.get("page_title")
                    if row.get("page_title") != r"\N"
                    else None,
                    "url": full_url,
                    "referrer": referrer,
                    "hostname": hostname,
                    "search": url_query if url_query else None,
                    "hash": None,
                    "screen": row.get("screen") if row.get("screen") != r"\N" else None,
                    "viewport": row.get("screen")
                    if row.get("screen") != r"\N"
                    else None,
                    "device": normalize_device(row.get("device")),
                    "language": row.get("language").split("-")[0]
                    if row.get("language") and row.get("language") != r"\N"
                    else None,
                    "browser": normalize_browser(row.get("browser")),
                    "os": normalize_os(row.get("os")),
                    "country_code": country_code or None,
                    "country": country_name,
                    "region": row.get("region") if row.get("region") != r"\N" else None,
                    "city": row.get("city") if row.get("city") != r"\N" else None,
                }

                # UTM tags
                for utm in (
                    "utm_source",
                    "utm_medium",
                    "utm_campaign",
                    "utm_content",
                    "utm_term",
                ):
                    val = row.get(utm)
                    if val and val != r"\N":
                        data[utm] = val

                # Merge extra parameters from event_data
                if event_id and event_id in event_data_map:
                    data.update(event_data_map[event_id])

                action = {
                    "site_id": site_id,
                    "session_id": session_id,
                    "visitor_id": visitor_id,
                    "name": action_name,
                    "data": data,
                }

                insert_batch.append(
                    (
                        session_id,
                        visitor_id,
                        json.dumps(action, ensure_ascii=False),
                        created_at,
                    )
                )
                total_rows += 1

                if len(insert_batch) >= batch_size:
                    cur.executemany(
                        "INSERT INTO user_activity (session_id, visitor_id, action, timestamp) VALUES (?, ?, ?, ?)",
                        insert_batch,
                    )
                    conn.commit()
                    insert_batch = []
                    print(f"      Imported {total_rows:,} events...", end="\r")

        if insert_batch:
            cur.executemany(
                "INSERT INTO user_activity (session_id, visitor_id, action, timestamp) VALUES (?, ?, ?, ?)",
                insert_batch,
            )
            conn.commit()

        cur.execute("PRAGMA synchronous = NORMAL;")
        conn.close()

        print(f"\n[5/5] Import successfully completed!")
        print("=" * 60)
        print(f"  Target Website:      {site_name} (ID: {site_id})")
        print(f"  Database:            data/{site_id}.db")
        print(f"  Total Events:        {total_rows:,}")
        print(f"  Pageviews:           {pageviews:,}")
        print(f"  Custom Events:       {custom_events:,}")
        print(f"  Distinct Sessions:   {len(sessions):,}")
        print(f"  Date Range:          {min_date} to {max_date}")
        print("=" * 60)
        print(f"Open dashboard: http://localhost:8080/dashboard/?site={site_id}")

    finally:
        if temp_dir and os.path.exists(temp_dir):
            import shutil

            shutil.rmtree(temp_dir, ignore_errors=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Import Umami Analytics export (.zip or CSV folder) into Minilytics."
    )
    parser.add_argument("--zip", dest="zip_path", help="Path to Umami .zip export file")
    parser.add_argument(
        "--folder", dest="folder_path", help="Path to folder containing Umami CSVs"
    )
    parser.add_argument(
        "--site-id",
        dest="site_id",
        help="Target Minilytics Site ID (e.g. ecrismalettre)",
    )
    parser.add_argument(
        "--site-name", dest="site_name", help="Target Site display name"
    )
    parser.add_argument(
        "--domain", dest="domain", help="Site domain (e.g. ecrismalettre.fr)"
    )

    args = parser.parse_args()

    target_source = args.zip_path or args.folder_path
    if not target_source:
        if os.path.exists("umami-export-sample.zip"):
            target_source = "umami-export-sample.zip"
        elif os.path.exists("umami-import"):
            target_source = "umami-import"
        else:
            parser.print_help()
            sys.exit(1)

    import_umami(
        target_source,
        site_id=args.site_id,
        site_name=args.site_name,
        domain=args.domain,
    )
