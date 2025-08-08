import csv
import uuid
import random
import argparse
import urllib.parse
from datetime import datetime
from faker import Faker
from tqdm import tqdm

fake = Faker()

# === 🔧 Column Toggle Config ===
ENABLED_COLUMNS = {
    "id": True,
    "parent_id": True,
    "active": True,
    "type": True,
    "visible": True,
    "name": True,
    "external_link": True,
    "description": True,
    "meta_title": True,
    "meta_description": True,
}

# === 🧭 Top-Level Parent ID (for all mid categories)
TOP_PARENT_ID = "0196156e3ecf729ea8b737e4911e99b9"

# === Storage
MID_CATEGORIES = {}      # mid_id -> dict
SUB_CATEGORIES = {}      # mid_id -> list[dict]


def maybe(prob, generator):
    """Randomly return empty string or value from generator."""
    return generator() if random.random() > prob else ""


def generate_slug_from_name(name: str) -> str:
    return name.lower().replace(" ", "-")


def generate_url_from_slug(slug: str) -> str:
    return f"https://shop.example.com/collections/{urllib.parse.quote(slug)}"


def generate_category_tree(mid_count, sub_per_mid):
    MID_CATEGORIES.clear()
    SUB_CATEGORIES.clear()

    for _ in range(mid_count):
        mid_id = uuid.uuid4().hex
        mid_name = fake.bs().title()
        MID_CATEGORIES[mid_id] = {
            "id": mid_id,
            "name": mid_name,
            "parent_id": TOP_PARENT_ID
        }

        SUB_CATEGORIES[mid_id] = []
        for _ in range(sub_per_mid):
            sub_id = uuid.uuid4().hex
            sub_name = fake.bs().title()
            SUB_CATEGORIES[mid_id].append({
                "id": sub_id,
                "name": sub_name,
                "parent_id": mid_id
            })


def build_row(cat_id, parent_id, name_prefix, name, null_prob):
    full_name = f"{name_prefix}: {name}"
    slug = generate_slug_from_name(name)
    url = generate_url_from_slug(slug)

    row = {
        "id": cat_id,
        "parent_id": parent_id,
        "active": 1,  # or random.choice([0, 1])
        "type": "page",
        "visible": 1,
        "name": full_name,
        "external_link": maybe(null_prob, lambda: fake.url()),
        "description": fake.paragraph(nb_sentences=3),
        "meta_title": maybe(null_prob, lambda: fake.sentence(nb_words=6)),
        "meta_description": maybe(null_prob, lambda: fake.text(max_nb_chars=100)),
    }

    return [row[col] for col in ENABLED_COLUMNS if ENABLED_COLUMNS[col]]


def write_csv(path, rows, null_prob, delimiter, include_header, label):
    headers = [col for col, enabled in ENABLED_COLUMNS.items() if enabled]

    with open(path, 'w', newline='', encoding='utf-8') as csvfile:
        writer = csv.writer(csvfile, delimiter=delimiter, quotechar='"', quoting=csv.QUOTE_MINIMAL)

        if include_header:
            writer.writerow(headers)

        with tqdm(total=len(rows), desc=f"Writing {label}", unit="row") as pbar:
            for row in rows:
                writer.writerow(row)
                pbar.update(1)


def generate_csv_files(mid_out, sub_out, delimiter, null_prob, include_header):
    mid_rows = []
    sub_rows = []

    for mid_id, mid in MID_CATEGORIES.items():
        mid_rows.append(build_row(
            cat_id=mid["id"],
            parent_id=mid["parent_id"],
            name_prefix="Mid",
            name=mid["name"],
            null_prob=null_prob
        ))

    for mid_id, sub_list in SUB_CATEGORIES.items():
        for sub in sub_list:
            sub_rows.append(build_row(
                cat_id=sub["id"],
                parent_id=sub["parent_id"],
                name_prefix="Sub",
                name=sub["name"],
                null_prob=null_prob
            ))

    write_csv(mid_out, mid_rows, null_prob, delimiter, include_header, "Mid Categories")
    write_csv(sub_out, sub_rows, null_prob, delimiter, include_header, "Sub Categories")

    print(f"\n✅ Mid categories: {len(mid_rows)} → {mid_out}")
    print(f"✅ Sub categories: {len(sub_rows)} → {sub_out}")


def parse_args():
    # Generate timestamp for default filenames
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    parser = argparse.ArgumentParser(description="Generate Shopify-style category hierarchy into separate CSVs.")
    parser.add_argument("--mid-count", type=int, default=50, help="Number of mid-level categories")
    parser.add_argument("--sub-per-mid", type=int, default=100, help="Number of subcategories per mid")
    parser.add_argument("--mid-out", type=str, default=f"mid_categories_{timestamp}.csv", help="Output file for mid categories")
    parser.add_argument("--sub-out", type=str, default=f"sub_categories_{timestamp}.csv", help="Output file for sub categories")
    parser.add_argument("--delimiter", type=str, default=",", help="CSV delimiter")
    parser.add_argument("--null-prob", type=float, default=0.0, help="Probability that optional fields are empty")
    parser.add_argument("--no-header", action="store_true", help="Omit CSV header row")
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()

    generate_category_tree(mid_count=args.mid_count, sub_per_mid=args.sub_per_mid)

    generate_csv_files(
        mid_out=args.mid_out,
        sub_out=args.sub_out,
        delimiter=args.delimiter,
        null_prob=args.null_prob,
        include_header=not args.no_header
    )
