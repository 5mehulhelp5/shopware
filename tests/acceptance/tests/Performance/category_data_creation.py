import csv
import uuid
import random
import argparse
import urllib.parse
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
TOP_PARENT_ID = "01987ec0def973a4809e72b191e2b5b2"

# === Mid/Sub category storage
MID_CATEGORIES = {}      # mid_id -> mid_dict
SUB_CATEGORIES = {}      # mid_id -> [sub_dicts]


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
        "active": 1, # random.choice([0, 1]),
        "type": "page",
        "visible": 1, # random.choice([0, 1]),
        "name": full_name,
        "external_link": fake.url(),
        "description": fake.paragraph(nb_sentences=3),
        "meta_title": fake.sentence(nb_words=6),
        "meta_description": fake.text(max_nb_chars=100),
    }

    return [row[col] for col in ENABLED_COLUMNS if ENABLED_COLUMNS[col]]


def generate_csv(file_path, delimiter, null_prob, include_header):
    headers = [col for col, enabled in ENABLED_COLUMNS.items() if enabled]

    total_rows = len(MID_CATEGORIES) + sum(len(subs) for subs in SUB_CATEGORIES.values())

    with open(file_path, 'w', newline='', encoding='utf-8') as csvfile:
        writer = csv.writer(csvfile, delimiter=delimiter, quotechar='"', quoting=csv.QUOTE_MINIMAL)

        if include_header:
            writer.writerow(headers)

        with tqdm(total=total_rows, desc="Writing Categories", unit="row") as pbar:
            # Write mid categories
            for mid_id, mid in MID_CATEGORIES.items():
                writer.writerow(build_row(
                    cat_id=mid["id"],
                    parent_id=mid["parent_id"],
                    name_prefix="Mid",
                    name=mid["name"],
                    null_prob=null_prob
                ))
                pbar.update(1)

            # Write sub categories
            for mid_id, sub_list in SUB_CATEGORIES.items():
                for sub in sub_list:
                    writer.writerow(build_row(
                        cat_id=sub["id"],
                        parent_id=sub["parent_id"],
                        name_prefix="Sub",
                        name=sub["name"],
                        null_prob=null_prob
                    ))
                    pbar.update(1)

    print(f"\n✅ Finished. Total rows written: {total_rows} → {file_path}")


def parse_args():
    parser = argparse.ArgumentParser(description="Generate Shopify-style category hierarchy.")
    parser.add_argument("--mid-count", type=int, default=50, help="Number of mid-level categories")
    parser.add_argument("--sub-per-mid", type=int, default=100, help="Number of subcategories per mid")
    parser.add_argument("--out", type=str, default="shopify_categories.csv", help="Output CSV file path")
    parser.add_argument("--delimiter", type=str, default=",", help="CSV delimiter")
    parser.add_argument("--null-prob", type=float, default=0, help="Probability that optional fields are empty")
    parser.add_argument("--no-header", action="store_true", help="Omit CSV header row")
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()

    generate_category_tree(mid_count=args.mid_count, sub_per_mid=args.sub_per_mid)

    generate_csv(
        file_path=args.out,
        delimiter=args.delimiter,
        null_prob=args.null_prob,
        include_header=not args.no_header
    )
